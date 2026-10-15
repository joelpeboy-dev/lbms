<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookActivity;
use App\Models\BookLoan;
use App\Models\LibraryAuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use PDO;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LibraryFeaturesController extends Controller
{
    public function report(): View
    {
        $books = Book::query()->get(['total_copies', 'condition_status']);

        return view('reports.index', [
            'metrics' => [
                'titles' => $books->count(),
                'copies' => (int) $books->sum('total_copies'),
                'available_copies' => max(0, (int) $books->sum(fn (Book $book): int => $book->condition_status === 'available' ? $book->total_copies : 0)
                    - BookLoan::query()->whereNull('returned_at')->whereHas('book', fn ($query) => $query->where('condition_status', 'available'))->count()),
                'active_loans' => BookLoan::query()->whereNull('returned_at')->count(),
                'overdue_loans' => BookLoan::query()->whereNull('returned_at')->where('due_at', '<', now())->count(),
                'lost_titles' => $books->where('condition_status', 'lost')->count(),
            ],
            'popularBooks' => Book::query()
                ->withCount('loans')
                ->orderByDesc('loans_count')
                ->orderBy('title')
                ->limit(10)
                ->get(),
            'recentLoans' => BookLoan::query()
                ->with(['book', 'borrower'])
                ->latest('borrowed_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function exportBooks(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Title', 'Author', 'Category', 'Genre', 'Barcode', 'Condition', 'Total copies']);

            Book::query()->orderBy('title')->chunk(500, function ($books) use ($output): void {
                foreach ($books as $book) {
                    fputcsv($output, [
                        $this->spreadsheetSafe($book->title),
                        $this->spreadsheetSafe($book->author),
                        $book->category,
                        $book->genre,
                        $this->spreadsheetSafe($book->barcode),
                        $book->condition_status,
                        $book->total_copies,
                    ]);
                }
            });

            fclose($output);
        }, 'library-books.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportLoans(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Book', 'Borrower', 'Borrowed at', 'Due at', 'Returned at', 'Status', 'Fine', 'Fine paid']);

            BookLoan::query()->with(['book', 'borrower'])->orderByDesc('borrowed_at')->chunk(500, function ($loans) use ($output): void {
                foreach ($loans as $loan) {
                    fputcsv($output, [
                        $this->spreadsheetSafe($loan->book->title),
                        $this->spreadsheetSafe($loan->borrower->name),
                        $loan->borrowed_at->toDateTimeString(),
                        $loan->due_at->toDateTimeString(),
                        $loan->returned_at?->toDateTimeString(),
                        $loan->returned_at ? 'returned' : ($loan->due_at->isPast() ? 'overdue' : 'borrowed'),
                        number_format((float) $loan->fine_amount, 2, '.', ''),
                        $loan->fine_paid_at?->toDateTimeString(),
                    ]);
                }
            });

            fclose($output);
        }, 'library-loans.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function readingHistory(Request $request): View
    {
        abort_unless($request->user()->role === 'student', 403);

        $user = $request->user();
        $recent = BookActivity::query()
            ->with('book')
            ->where('user_id', $user->id)
            ->where('type', 'viewed')
            ->latest()
            ->paginate(20);
        $viewedIds = BookActivity::query()
            ->where('user_id', $user->id)
            ->where('type', 'viewed')
            ->distinct()
            ->pluck('book_id');
        $genres = Book::query()->whereIn('id', $viewedIds)->whereNotNull('genre')->distinct()->pluck('genre');
        $favorites = BookActivity::query()
            ->with('book')
            ->where('user_id', $user->id)
            ->where('type', 'favorite')
            ->latest()
            ->get();

        $recommendations = Book::query()
            ->whereNotIn('id', $viewedIds->all() ?: [0])
            ->when($genres->isNotEmpty(), fn ($query) => $query->whereIn('genre', $genres))
            ->withCount('loans')
            ->orderByDesc('loans_count')
            ->orderBy('title')
            ->limit(8)
            ->get();

        return view('student.history', compact('recent', 'favorites', 'recommendations'));
    }

    public function auditLog(): View
    {
        return view('admin.audit-log', [
            'logs' => LibraryAuditLog::query()->with('user')->latest()->paginate(50),
        ]);
    }

    public function backups(): View
    {
        $this->ensureSQLite();

        $files = collect(Storage::disk('local')->files('library-backups'))
            ->filter(fn (string $path): bool => preg_match('/^library-backups\\/library-\\d{8}T\\d{6}-[a-zA-Z0-9]+\\.sqlite$/', $path) === 1)
            ->map(fn (string $path): array => [
                'name' => basename($path),
                'size' => Storage::disk('local')->size($path),
                'updated_at' => Storage::disk('local')->lastModified($path),
            ])
            ->sortByDesc('updated_at')
            ->values();

        return view('admin.backups', compact('files'));
    }

    public function createBackup(): RedirectResponse
    {
        $path = $this->createDatabaseSnapshot();
        LibraryAuditLog::record('database.backup_created', null, ['filename' => basename($path)]);

        return redirect()->route('admin.backups')->with('success', 'A private database backup was created.');
    }

    public function downloadBackup(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'regex:/^library-\\d{8}T\\d{6}-[a-zA-Z0-9]+\\.sqlite$/'],
        ]);
        $path = 'library-backups/'.$data['file'];

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $data['file']);
    }

    public function restoreBackup(Request $request): RedirectResponse
    {
        $this->ensureSQLite();

        $data = $request->validate([
            'backup' => ['required', 'file', 'max:512000'],
        ]);
        $uploadedPath = $data['backup']->getRealPath();
        $this->validateSQLiteBackup($uploadedPath);

        $databasePath = DB::connection()->getConfig('database');
        abort_if($databasePath === ':memory:' || str_starts_with($databasePath, 'file:'), 422, 'The active database path cannot be safely restored.');
        $databasePath = preg_match('/^(?:[a-zA-Z]:[\\\\\\/]|[\\\\\\/]{1,2})/', $databasePath) === 1
            ? $databasePath
            : base_path($databasePath);
        abort_unless(is_file($databasePath) && ! is_link($databasePath), 422, 'The active database file is unavailable for safe restore.');

        $restoreStage = $databasePath.'.restore-'.str()->random(12);
        $safetyCopy = $this->createDatabaseSnapshot();

        if (! copy($uploadedPath, $restoreStage)) {
            $stageRemoved = ! is_file($restoreStage) || unlink($restoreStage);
            if (! $stageRemoved) {
                throw new RuntimeException('The validated backup could not be staged and its partial temporary file could not be removed.');
            }
            throw new RuntimeException('The validated backup could not be staged for restore.');
        }

        if (Artisan::call('down') !== 0) {
            $this->removeRestoreStage($restoreStage);
            throw new RuntimeException('The application could not enter maintenance mode, so the database was not restored.');
        }
        try {
            DB::purge();
            foreach ([$databasePath.'-wal', $databasePath.'-shm'] as $sidecar) {
                if (is_file($sidecar) && ! unlink($sidecar)) {
                    throw new RuntimeException('SQLite sidecar files could not be cleared safely.');
                }
            }

            if (! copy($restoreStage, $databasePath)) {
                $safetyRestored = copy($safetyCopy, $databasePath);
                throw new RuntimeException($safetyRestored
                    ? 'The database could not be replaced; the safety backup was restored.'
                    : 'The database could not be replaced and the automatic safety backup could not be copied back.');
            }
        } finally {
            try {
                DB::reconnect();
            } finally {
                try {
                    $this->removeRestoreStage($restoreStage);
                } finally {
                    if (Artisan::call('up') !== 0) {
                        throw new RuntimeException('The application could not leave maintenance mode after the restore operation.');
                    }
                }
            }
        }

        DB::connection()->getPdo()->query('SELECT 1');
        LibraryAuditLog::record('database.restored', null, ['safety_backup' => basename($safetyCopy)]);

        return redirect()->route('admin.backups')->with('success', 'The database was restored. A safety backup of the previous database was retained.');
    }

    private function createDatabaseSnapshot(): string
    {
        $this->ensureSQLite();

        $databasePath = DB::connection()->getConfig('database');
        abort_if($databasePath === ':memory:' || str_starts_with($databasePath, 'file:'), 422, 'The active database path cannot be backed up safely.');

        $name = 'library-'.now()->format('Ymd\\THis').'-'.str()->random(8).'.sqlite';
        $relativePath = 'library-backups/'.$name;
        Storage::disk('local')->makeDirectory('library-backups');
        $absolutePath = Storage::disk('local')->path($relativePath);
        $quotedPath = DB::connection()->getPdo()->quote($absolutePath);
        DB::statement("VACUUM INTO {$quotedPath}");

        return $absolutePath;
    }

    private function validateSQLiteBackup(string $path): void
    {
        $handle = fopen($path, 'rb');
        $header = $handle === false ? false : fread($handle, 16);
        if ($handle !== false) {
            fclose($handle);
        }

        abort_unless($header === "SQLite format 3\0", 422, 'Upload a valid SQLite database backup.');

        $pdo = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $integrity = $pdo->query('PRAGMA integrity_check')->fetchColumn();
        abort_unless($integrity === 'ok', 422, 'The uploaded SQLite database failed its integrity check.');

        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
        foreach (['migrations', 'users', 'books', 'book_loans', 'book_reservations', 'book_activities', 'library_audit_logs'] as $requiredTable) {
            abort_unless(in_array($requiredTable, $tables, true), 422, 'The backup is missing required library tables.');
        }

        abort_unless(
            $pdo->query("SELECT 1 FROM migrations WHERE migration = '2026_10_08_080000_add_library_operations_features'")->fetchColumn() !== false,
            422,
            'The backup is from a different schema version and cannot be restored here.',
        );
        $bookColumns = $pdo->query("PRAGMA table_info('books')")->fetchAll(PDO::FETCH_COLUMN, 1);
        $loanColumns = $pdo->query("PRAGMA table_info('book_loans')")->fetchAll(PDO::FETCH_COLUMN, 1);
        abort_unless(
            in_array('barcode', $bookColumns, true)
                && in_array('condition_status', $bookColumns, true)
                && in_array('due_reminded_at', $loanColumns, true)
                && in_array('overdue_reminded_at', $loanColumns, true),
            422,
            'The backup does not have the current library schema.',
        );
        abort_unless($pdo->query('PRAGMA foreign_key_check')->fetchColumn() === false, 422, 'The backup contains invalid foreign-key references.');
    }

    private function ensureSQLite(): void
    {
        abort_unless(DB::connection()->getDriverName() === 'sqlite', 422, 'Database backup and restore are currently supported only for SQLite.');
    }

    private function removeRestoreStage(string $path): void
    {
        if (is_file($path) && ! unlink($path)) {
            throw new RuntimeException('The temporary database restore file could not be removed.');
        }
    }

    private function spreadsheetSafe(?string $value): ?string
    {
        return $value !== null && preg_match('/^[=+\\-@\\t\\r]/', $value) === 1 ? "'".$value : $value;
    }
}
