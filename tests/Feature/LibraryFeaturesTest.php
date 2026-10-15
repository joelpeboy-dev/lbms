<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookActivity;
use App\Models\BookLoan;
use App\Models\LibraryAuditLog;
use App\Models\User;
use App\Notifications\LibraryMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LibraryFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_students_and_staff_can_search_by_scanned_barcode(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $staff = User::factory()->create(['role' => 'staff']);
        $book = $this->createBook('barcode-title', ['barcode' => '9781234567890']);
        $this->createBook('another-title', ['barcode' => '9780000000000']);

        $this->actingAs($student)->get(route('reading.index', ['search' => '9781234567890']))
            ->assertOk()
            ->assertSee($book->title)
            ->assertDontSee('Another Title');

        $this->actingAs($staff)->get(route('books.index', ['search' => '9781234567890']))
            ->assertOk()
            ->assertSee($book->title)
            ->assertDontSee('Another Title');

        $this->get(route('loans.index'))
            ->assertOk()
            ->assertSee('id="loan-barcode"', false)
            ->assertSee('data-barcode="9781234567890"', false);
    }

    public function test_books_marked_unavailable_cannot_be_issued_and_show_no_available_copies(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $student = User::factory()->create(['role' => 'student']);
        $book = $this->createBook('damaged-title', ['condition_status' => 'damaged']);

        $this->actingAs($staff)->post(route('loans.store'), [
            'book_id' => $book->id,
            'borrower_id' => $student->id,
            'due_at' => now()->addDays(7)->toDateString(),
        ])->assertSessionHasErrors([
            'book_id' => 'There are no available copies of this title.',
        ]);

        $this->assertSame(0, $book->fresh()->availableCopies());
        $this->assertDatabaseCount('book_loans', 0);
    }

    public function test_scheduled_reminders_notify_each_borrower_only_once(): void
    {
        $this->freezeTime();
        $dueSoonStudent = User::factory()->create(['role' => 'student']);
        $overdueStudent = User::factory()->create(['role' => 'student']);
        $dueSoonBook = $this->createBook('due-soon');
        $overdueBook = $this->createBook('overdue');

        BookLoan::create([
            'book_id' => $dueSoonBook->id,
            'borrower_id' => $dueSoonStudent->id,
            'borrowed_at' => now()->subDays(2),
            'due_at' => now()->addHours(8),
        ]);
        BookLoan::create([
            'book_id' => $overdueBook->id,
            'borrower_id' => $overdueStudent->id,
            'borrowed_at' => now()->subDays(5),
            'due_at' => now()->subDay(),
        ]);

        Notification::fake([LibraryMessage::class]);

        $this->artisan('library:send-loan-reminders')->assertExitCode(0);
        $this->artisan('library:send-loan-reminders')->assertExitCode(0);

        Notification::assertSentTo($dueSoonStudent, LibraryMessage::class, fn (LibraryMessage $notification): bool => str_contains($notification->toMail($dueSoonStudent)->subject, 'due soon'));
        Notification::assertSentTo($overdueStudent, LibraryMessage::class, fn (LibraryMessage $notification): bool => str_contains($notification->toMail($overdueStudent)->subject, 'Overdue'));
        $this->assertSame(1, BookLoan::query()->whereNotNull('due_reminded_at')->count());
        $this->assertSame(1, BookLoan::query()->whereNotNull('overdue_reminded_at')->count());
    }

    public function test_students_see_recent_reading_and_recommendations_from_their_genres(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $readBook = $this->createBook('read-fantasy', ['genre' => 'fantasy']);
        $recommendedBook = $this->createBook('recommended-fantasy', ['genre' => 'fantasy']);
        $otherGenreBook = $this->createBook('other-genre', ['genre' => 'programming']);
        BookActivity::create(['user_id' => $student->id, 'book_id' => $readBook->id, 'type' => 'viewed']);

        $this->actingAs($student)->get(route('reading.history'))
            ->assertOk()
            ->assertSee($readBook->title)
            ->assertSee($recommendedBook->title)
            ->assertDontSee($otherGenreBook->title);

        $this->actingAs(User::factory()->create(['role' => 'staff']))->get(route('reading.history'))
            ->assertForbidden();
    }

    public function test_reports_are_available_to_staff_and_csv_exports_are_downloads(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $book = $this->createBook('report-book');

        $this->actingAs($staff)->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Library reports')
            ->assertSee($book->title);

        $this->get(route('reports.books'))->assertDownload('library-books.csv');
        $this->get(route('reports.loans'))->assertDownload('library-loans.csv');
    }

    public function test_admin_activity_log_is_role_protected_and_restore_rejects_non_sqlite_files(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->get(route('admin.audit-log'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.backups'))->assertForbidden();

        $this->actingAs($admin)->get(route('admin.audit-log'))->assertOk();
        $this->post(route('admin.backups.restore'), [
            'backup' => UploadedFile::fake()->createWithContent('backup.sqlite', 'not a sqlite database'),
        ])->assertUnprocessable();

        $this->assertSame(0, LibraryAuditLog::query()->count());
    }

    private function createBook(string $slug, array $attributes = []): Book
    {
        return Book::create(array_merge([
            'slug' => $slug,
            'category' => 'book',
            'genre' => 'fantasy',
            'title' => str($slug)->replace('-', ' ')->title()->toString(),
            'author' => 'Library Author',
            'description' => 'A description.',
            'content' => 'Book content.',
            'total_copies' => 2,
        ], $attributes));
    }
}
