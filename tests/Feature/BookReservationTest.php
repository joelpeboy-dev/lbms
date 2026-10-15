<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookLoan;
use App\Models\BookReservation;
use App\Models\User;
use App\Notifications\LibraryMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BookReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_reserve_an_unavailable_book_but_not_duplicate_or_available_titles(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $borrower = User::factory()->create(['role' => 'student']);
        $unavailableBook = $this->createBook('unavailable-book');
        $availableBook = $this->createBook('available-book');
        $this->createLoan($unavailableBook, $borrower);

        $this->actingAs($student)->post(route('reservations.store', $unavailableBook->slug))
            ->assertRedirect(route('reservations.mine'))
            ->assertSessionHas('success', 'Book reservation submitted.');

        $reservation = BookReservation::query()->firstOrFail();
        $this->assertSame('pending', $reservation->status);
        $this->assertSame($student->id, $reservation->student_id);

        $this->post(route('reservations.store', $unavailableBook->slug))
            ->assertSessionHasErrors('reservation');
        $this->post(route('reservations.store', $availableBook->slug))
            ->assertSessionHasErrors('reservation');
        $this->assertDatabaseCount('book_reservations', 1);
    }

    public function test_student_cannot_reserve_another_copy_while_they_have_an_active_loan(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $otherBorrower = User::factory()->create(['role' => 'student']);
        $book = $this->createBook('already-borrowed-book', 2);
        $this->createLoan($book, $student);
        $this->createLoan($book, $otherBorrower);

        $this->actingAs($student)->post(route('reservations.store', $book->slug))
            ->assertSessionHasErrors('reservation');

        $this->assertDatabaseCount('book_reservations', 0);
    }

    public function test_staff_issue_pending_reservations_as_loans_in_request_order(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $originalBorrower = User::factory()->create(['role' => 'student']);
        $firstStudent = User::factory()->create(['role' => 'student']);
        $secondStudent = User::factory()->create(['role' => 'student']);
        $book = $this->createBook('queued-book');
        $originalLoan = $this->createLoan($book, $originalBorrower);
        Notification::fake([LibraryMessage::class]);

        $this->actingAs($firstStudent)->post(route('reservations.store', $book->slug))->assertSessionHas('success');
        $firstReservation = BookReservation::query()->where('student_id', $firstStudent->id)->firstOrFail();
        $this->actingAs($secondStudent)->post(route('reservations.store', $book->slug))->assertSessionHas('success');
        $secondReservation = BookReservation::query()->where('student_id', $secondStudent->id)->firstOrFail();

        $this->actingAs($staff)->post(route('loans.return', $originalLoan))->assertSessionHas('success');

        $this->post(route('reservations.fulfill', $secondReservation), ['due_at' => now()->addDays(14)->toDateString()])
            ->assertSessionHasErrors('reservation');
        $this->assertDatabaseCount('book_loans', 1);
        $this->assertSame('pending', $secondReservation->fresh()->status);

        $this->post(route('reservations.fulfill', $firstReservation), ['due_at' => now()->addDays(14)->toDateString()])
            ->assertRedirect(route('reservations.index'))
            ->assertSessionHas('success', 'Reservation issued as a book loan.');

        $this->assertDatabaseCount('book_loans', 2);
        $this->assertDatabaseHas('book_loans', [
            'id' => $firstReservation->fresh()->loan_id,
            'book_id' => $book->id,
            'borrower_id' => $firstStudent->id,
            'issued_by' => $staff->id,
        ]);
        $this->assertSame('fulfilled', $firstReservation->fresh()->status);
        $this->assertSame('pending', $secondReservation->fresh()->status);
        Notification::assertSentTo($firstStudent, LibraryMessage::class);
    }

    public function test_students_can_only_view_and_cancel_their_own_pending_reservations(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $staff = User::factory()->create(['role' => 'staff']);
        $book = $this->createBook('private-reservation');
        $reservation = BookReservation::create([
            'book_id' => $book->id,
            'student_id' => $otherStudent->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $this->actingAs($student)->get(route('reservations.mine'))->assertOk()->assertDontSee($book->title);
        $this->post(route('reservations.cancel', $reservation))->assertNotFound();
        $this->actingAs($student)->get(route('reservations.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('reservations.mine'))->assertForbidden();
        $this->assertSame('pending', $reservation->fresh()->status);

        $this->actingAs($otherStudent)->post(route('reservations.cancel', $reservation))
            ->assertRedirect(route('reservations.mine'))
            ->assertSessionHas('success', 'Reservation cancelled.');
        $this->assertSame('cancelled', $reservation->fresh()->status);
    }

    private function createBook(string $slug, int $totalCopies = 1): Book
    {
        return Book::create([
            'slug' => $slug,
            'category' => 'book',
            'title' => str($slug)->replace('-', ' ')->title()->toString(),
            'author' => 'Library Author',
            'description' => 'A description.',
            'content' => 'Book content.',
            'total_copies' => $totalCopies,
        ]);
    }

    private function createLoan(Book $book, User $borrower): BookLoan
    {
        return BookLoan::create([
            'book_id' => $book->id,
            'borrower_id' => $borrower->id,
            'borrowed_at' => now(),
            'due_at' => now()->addDays(14),
        ]);
    }
}
