<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookLoan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookLoanTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_issue_and_return_a_book_without_overdrawing_stock(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $student = User::factory()->create(['role' => 'student']);
        $book = Book::create([
            'slug' => 'one-copy',
            'category' => 'book',
            'title' => 'One Copy',
            'author' => 'Author',
            'description' => 'A description.',
            'content' => 'Book content.',
            'total_copies' => 1,
        ]);

        $issue = [
            'book_id' => $book->id,
            'borrower_id' => $student->id,
            'due_at' => now()->addDays(14)->toDateString(),
        ];

        $this->actingAs($staff)->post('/loans', $issue)
            ->assertRedirect(route('loans.index'))
            ->assertSessionHas('success');
        $loan = BookLoan::query()->firstOrFail();
        $this->assertSame('23:59:59', $loan->due_at->format('H:i:s'));
        $this->assertSame(0, $book->fresh()->availableCopies());

        $this->post('/loans', $issue)
            ->assertSessionHasErrors('book_id');
        $this->assertDatabaseCount('book_loans', 1);

        $this->post(route('loans.return', $loan))
            ->assertRedirect(route('loans.index'))
            ->assertSessionHas('success');
        $this->assertSame(1, $book->fresh()->availableCopies());

        $this->post('/loans', $issue)->assertSessionHas('success');
        $this->assertDatabaseCount('book_loans', 2);
    }

    public function test_only_staff_and_admin_can_manage_loans_and_students_only_see_their_own_records(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $book = Book::create([
            'slug' => 'student-copy',
            'category' => 'book',
            'title' => 'Student Copy',
            'author' => 'Author',
            'description' => 'A description.',
            'content' => 'Book content.',
        ]);
        BookLoan::create([
            'book_id' => $book->id,
            'borrower_id' => $student->id,
            'borrowed_at' => now(),
            'due_at' => now()->addDays(14),
        ]);
        BookLoan::create([
            'book_id' => $book->id,
            'borrower_id' => $otherStudent->id,
            'borrowed_at' => now(),
            'due_at' => now()->addDays(14),
        ]);

        $this->actingAs($student)->get('/loans')->assertForbidden();
        $this->actingAs($student)->get('/my-loans')
            ->assertOk()
            ->assertSee('Student Copy')
            ->assertDontSee($otherStudent->name);
    }

    public function test_books_with_loan_history_cannot_be_deleted(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $student = User::factory()->create(['role' => 'student']);
        $book = Book::create([
            'slug' => 'protected-book',
            'category' => 'book',
            'title' => 'Protected Book',
            'author' => 'Author',
            'description' => 'A description.',
            'content' => 'Book content.',
        ]);
        BookLoan::create([
            'book_id' => $book->id,
            'borrower_id' => $student->id,
            'borrowed_at' => now(),
            'due_at' => now()->addDays(14),
            'returned_at' => now(),
        ]);

        $this->actingAs($staff)->delete(route('books.destroy', $book))
            ->assertSessionHasErrors('book');
        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    public function test_students_can_search_the_catalog_and_view_copy_availability(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        Book::create([
            'slug' => 'searchable-title',
            'category' => 'study',
            'title' => 'Searchable Title',
            'author' => 'Library Author',
            'description' => 'An introductory guide.',
            'content' => 'Study content.',
            'total_copies' => 3,
        ]);
        Book::create([
            'slug' => 'other-title',
            'category' => 'manga',
            'title' => 'Another Title',
            'author' => 'Different Author',
            'description' => 'Another entry.',
            'content' => 'Manga content.',
        ]);

        $this->actingAs($student)->get('/reading?search=Searchable')
            ->assertOk()
            ->assertSee('Searchable Title')
            ->assertSee('3 of 3 physical copies available')
            ->assertDontSee('Another Title');
    }

    public function test_overdue_returns_create_and_track_fines(): void
    {
        config()->set('library.fine_per_day', 25.00);

        $staff = User::factory()->create(['role' => 'staff']);
        $student = User::factory()->create(['role' => 'student']);
        $book = Book::create([
            'slug' => 'fine-book',
            'category' => 'book',
            'title' => 'Fine Book',
            'author' => 'Author',
            'description' => 'A description.',
            'content' => 'Book content.',
        ]);

        $loan = BookLoan::create([
            'book_id' => $book->id,
            'borrower_id' => $student->id,
            'issued_by' => $staff->id,
            'borrowed_at' => now()->subDays(4),
            'due_at' => now()->subDays(1),
        ]);

        $this->actingAs($staff)->post(route('loans.return', $loan))
            ->assertRedirect(route('loans.index'))
            ->assertSessionHas('success');

        $this->assertSame(25.0, (float) $loan->fresh()->fine_amount);

        $this->actingAs($staff)->post(route('loans.fine.pay', $loan))
            ->assertRedirect(route('loans.index'))
            ->assertSessionHas('success');

        $this->assertNotNull($loan->fresh()->fine_paid_at);
    }
}
