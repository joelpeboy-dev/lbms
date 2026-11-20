<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookCategory;
use App\Models\LibraryAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'in:book,study,manga'],
            'genre' => ['nullable', Rule::exists('book_categories', 'slug')],
        ]);

        return view('books.index', [
            'books' => Book::query()
                ->withCount(['loans as active_loans_count' => fn (Builder $query) => $query->whereNull('returned_at')->whereNull('voided_at')])
                ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('title', 'like', "%{$search}%")
                            ->orWhere('author', 'like', "%{$search}%")
                            ->orWhere('genre', 'like', "%{$search}%")
                            ->orWhere('barcode', $search);
                    });
                })
                ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
                ->when($filters['genre'] ?? null, fn (Builder $query, string $genre) => $query->where('genre', $genre))
                ->orderBy('title')
                ->paginate(20)
                ->withQueryString(),
            'filters' => $filters,
            'genres' => BookCategory::query()->orderBy('name')->pluck('name', 'slug')->all(),
        ]);
    }

    public function create(): View
    {
        return view('books.create', [
            'genres' => BookCategory::query()->orderBy('name')->pluck('name', 'slug')->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedBook($request);
        $data['slug'] = str($data['title'])->slug().'-'.str()->random(6);
        $data['illustrations'] = $this->illustrations($data['illustrations'] ?? '');
        $book = Book::create($data);
        LibraryAuditLog::record('book.created', $book, ['title' => $book->title]);

        return redirect()->route('books.index')->with('success', 'Book added for students.');
    }

    public function edit(Book $book): View
    {
        return view('books.edit', [
            'book' => $book,
            'genres' => BookCategory::query()->orderBy('name')->pluck('name', 'slug')->all(),
        ]);
    }

    public function update(Request $request, Book $book): RedirectResponse
    {
        $data = $this->validatedBook($request, $book);

        DB::transaction(function () use ($book, $data): void {
            $book = Book::query()->lockForUpdate()->findOrFail($book->id);
            $activeLoans = $book->loans()->whereNull('returned_at')->whereNull('voided_at')->count();

            if ($data['total_copies'] < $activeLoans) {
                throw ValidationException::withMessages([
                    'total_copies' => "This title already has {$activeLoans} copies on loan.",
                ]);
            }

            $data['illustrations'] = $this->illustrations($data['illustrations'] ?? '');
            $book->update($data);
            LibraryAuditLog::record('book.updated', $book, ['changed_fields' => array_keys($book->getChanges())]);
        });

        return redirect()->route('books.index')->with('success', 'Book updated successfully.');
    }

    public function destroy(Book $book): RedirectResponse
    {
        DB::transaction(function () use ($book): void {
            $book = Book::query()->lockForUpdate()->findOrFail($book->id);

            if ($book->loans()->exists() || $book->reservations()->exists()) {
                throw ValidationException::withMessages([
                    'book' => 'A title with borrowing or reservation history cannot be deleted.',
                ]);
            }

            LibraryAuditLog::record('book.deleted', $book, ['title' => $book->title]);
            $book->delete();
        });

        return redirect()->route('books.index')->with('success', 'Book deleted successfully.');
    }

    private function validatedBook(Request $request, ?Book $book = null): array
    {
        $barcodeRule = Rule::unique('books', 'barcode');

        if ($book !== null) {
            $barcodeRule->ignore($book->id);
        }

        return $request->validate([
            'category' => ['required', 'in:book,study,manga'],
            'genre' => ['nullable', Rule::exists('book_categories', 'slug')],
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'cover_url' => ['nullable', 'url', 'max:2048'],
            'illustrations' => ['nullable', 'string'],
            'content' => ['required', 'string'],
            'source_label' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'total_copies' => ['required', 'integer', 'min:1', 'max:100000'],
            'barcode' => ['nullable', 'string', 'max:64', $barcodeRule],
            'condition_status' => ['required', Rule::in(['available', 'maintenance', 'damaged', 'lost'])],
        ]);
    }

    private function illustrations(string $illustrations): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $illustrations))
            ->map(fn (string $url): string => trim($url))
            ->filter(fn (string $url): bool => filter_var($url, FILTER_VALIDATE_URL) !== false)
            ->values()
            ->map(fn (string $url): array => ['url' => $url, 'caption' => 'Story illustration'])
            ->all();
    }
}
