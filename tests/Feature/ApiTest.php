<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_tokens_are_hashed_and_can_be_revoked(): void
    {
        $user = User::factory()->create(['role' => 'student', 'password' => 'password']);
        $response = $this->postJson('/api/auth/token', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertCreated()->assertJsonPath('token_type', 'Bearer');
        $plainTextToken = $response->json('token');
        $this->assertNotEmpty($plainTextToken);
        $this->assertDatabaseHas('api_tokens', [
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainTextToken),
        ]);
        $this->assertDatabaseMissing('api_tokens', ['token_hash' => $plainTextToken]);

        $this->withToken($plainTextToken)->getJson('/api/books')->assertOk();
        $this->withToken($plainTextToken)->deleteJson('/api/auth/token')
            ->assertOk()
            ->assertJsonPath('message', 'The API token has been revoked.');
        $this->withToken($plainTextToken)->getJson('/api/books')->assertUnauthorized();
    }

    public function test_students_can_create_loans_for_themselves_but_cannot_access_staff_reports(): void
    {
        $student = User::factory()->create(['role' => 'student', 'password' => 'password']);
        $book = Book::create([
            'slug' => 'api-book',
            'category' => 'book',
            'title' => 'API Book',
            'author' => 'Author',
            'description' => 'Description.',
            'content' => 'Content.',
            'total_copies' => 1,
        ]);
        $token = $this->tokenFor($student);

        $this->withToken($token)->postJson('/api/loans', [
            'book_id' => $book->id,
            'due_at' => now()->addDays(14)->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('data.borrower.id', $student->id)
            ->assertJsonPath('data.book.title', 'API Book');

        $this->withToken($token)->getJson('/api/loans')
            ->assertOk()
            ->assertJsonPath('data.0.borrower.id', $student->id);
        $this->withToken($token)->getJson('/api/reports/loans')->assertForbidden();
        $this->assertDatabaseHas('book_loans', ['borrower_id' => $student->id, 'book_id' => $book->id]);
    }

    public function test_staff_api_can_issue_to_a_student_and_record_a_return(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'password' => 'password']);
        $student = User::factory()->create(['role' => 'student']);
        $book = Book::create([
            'slug' => 'return-api-book',
            'category' => 'study',
            'title' => 'Return API Book',
            'author' => 'Author',
            'description' => 'Description.',
            'content' => 'Content.',
        ]);
        $token = $this->tokenFor($staff);

        $response = $this->withToken($token)->postJson('/api/loans', [
            'book_id' => $book->id,
            'borrower_id' => $student->id,
            'due_at' => now()->addDays(14)->toDateString(),
        ])->assertCreated();
        $loanId = $response->json('data.id');

        $this->withToken($token)->postJson("/api/loans/{$loanId}/return")
            ->assertOk()
            ->assertJsonPath('message', 'Book return recorded.');
        $this->withToken($token)->getJson('/api/reports/loans')
            ->assertOk()
            ->assertJsonPath('data.active_loans', 0);
    }

    public function test_api_barcode_search_reports_condition_aware_availability_and_blocks_unavailable_loans(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'password' => 'password']);
        $student = User::factory()->create(['role' => 'student']);
        $book = Book::create([
            'slug' => 'maintenance-api-book',
            'category' => 'book',
            'title' => 'Maintenance API Book',
            'author' => 'Author',
            'description' => 'Description.',
            'content' => 'Content.',
            'barcode' => '9781111111111',
            'condition_status' => 'maintenance',
            'total_copies' => 3,
        ]);
        $token = $this->tokenFor($staff);

        $this->withToken($token)->getJson('/api/books?search=9781111111111')
            ->assertOk()
            ->assertJsonPath('data.0.barcode', '9781111111111')
            ->assertJsonPath('data.0.condition_status', 'maintenance')
            ->assertJsonPath('data.0.available_copies', 0);

        $this->withToken($token)->postJson('/api/loans', [
            'book_id' => $book->id,
            'borrower_id' => $student->id,
            'due_at' => now()->addDays(14)->toDateString(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['book_id']);
    }

    public function test_book_api_can_filter_and_return_book_genres(): void
    {
        $student = User::factory()->create(['role' => 'student', 'password' => 'password']);
        Book::create([
            'slug' => 'api-programming-guide',
            'category' => 'book',
            'genre' => 'programming',
            'title' => 'API Programming Guide',
            'author' => 'Catalog Author',
            'description' => 'A catalog description.',
            'content' => 'The digital text is not included.',
        ]);
        Book::create([
            'slug' => 'api-fantasy-story',
            'category' => 'book',
            'genre' => 'fantasy',
            'title' => 'API Fantasy Story',
            'author' => 'Another Author',
            'description' => 'Another catalog description.',
            'content' => 'The digital text is not included.',
        ]);
        $token = $this->tokenFor($student);

        $this->withToken($token)->getJson('/api/books?genre=programming')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'API Programming Guide')
            ->assertJsonPath('data.0.genre', 'programming')
            ->assertJsonMissing(['title' => 'API Fantasy Story']);
    }

    public function test_staff_can_complete_book_catalog_crud_through_the_api(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'password' => 'password']);
        $token = $this->tokenFor($staff);
        $payload = [
            'slug' => 'client-controlled-slug',
            'category' => 'book',
            'genre' => 'fantasy',
            'title' => 'API Created Book',
            'author' => 'Catalog Author',
            'description' => 'A book created through the API.',
            'cover_url' => 'https://example.test/cover.jpg',
            'illustrations' => [
                ['url' => 'https://example.test/illustration.jpg', 'caption' => 'A scene'],
            ],
            'content' => 'The story text.',
            'total_copies' => 2,
            'barcode' => '9781234567890',
            'condition_status' => 'available',
        ];

        $created = $this->withToken($token)->postJson('/api/books', $payload)
            ->assertCreated()
            ->assertJsonPath('data.title', 'API Created Book')
            ->assertJsonPath('data.genre', 'fantasy')
            ->assertJsonPath('data.available_copies', 2);
        $bookId = $created->json('data.id');
        $slug = $created->json('data.slug');

        $this->assertNotSame('client-controlled-slug', $slug);
        $this->assertDatabaseHas('books', [
            'id' => $bookId,
            'slug' => $slug,
            'title' => 'API Created Book',
            'barcode' => '9781234567890',
        ]);

        $this->withToken($token)->getJson('/api/books')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'API Created Book');
        $this->withToken($token)->getJson("/api/books/{$slug}")
            ->assertOk()
            ->assertJsonPath('data.title', 'API Created Book');

        $this->withToken($token)->patchJson("/api/books/{$slug}", [
            'title' => 'API Updated Book',
            'total_copies' => 3,
            'barcode' => '9781234567890',
        ])->assertOk()
            ->assertJsonPath('data.title', 'API Updated Book')
            ->assertJsonPath('data.total_copies', 3);
        $this->assertDatabaseHas('books', [
            'id' => $bookId,
            'title' => 'API Updated Book',
            'total_copies' => 3,
        ]);

        $this->withToken($token)->deleteJson("/api/books/{$slug}")
            ->assertOk()
            ->assertJsonPath('message', 'Book deleted successfully.');
        $this->assertDatabaseMissing('books', ['id' => $bookId]);
        $this->withToken($token)->getJson("/api/books/{$slug}")->assertNotFound();
        $this->assertDatabaseHas('library_audit_logs', [
            'action' => 'book.created',
            'subject_id' => $bookId,
        ]);
        $this->assertDatabaseHas('library_audit_logs', [
            'action' => 'book.updated',
            'subject_id' => $bookId,
        ]);
        $this->assertDatabaseHas('library_audit_logs', [
            'action' => 'book.deleted',
            'subject_id' => $bookId,
        ]);
    }

    public function test_book_api_returns_401_when_a_create_request_has_no_token(): void
    {
        $this->postJson('/api/books')->assertUnauthorized();
        $this->assertDatabaseCount('books', 0);
    }

    public function test_student_tokens_receive_403_for_book_catalog_mutations(): void
    {
        $student = User::factory()->create(['role' => 'student', 'password' => 'password']);
        $book = Book::create([
            'slug' => 'student-api-book',
            'category' => 'book',
            'title' => 'Student API Book',
            'author' => 'Author',
            'description' => 'Description.',
            'content' => 'Content.',
        ]);
        $token = $this->tokenFor($student);
        $payload = [
            'category' => 'book',
            'title' => 'Unauthorized Book',
            'author' => 'Author',
            'description' => 'Description.',
            'content' => 'Content.',
            'total_copies' => 1,
            'condition_status' => 'available',
        ];

        $this->withToken($token)->postJson('/api/books', $payload)->assertForbidden();
        $this->withToken($token)->putJson("/api/books/{$book->slug}", $payload)->assertForbidden();
        $this->withToken($token)->patchJson("/api/books/{$book->slug}", ['title' => 'Changed'])->assertForbidden();
        $this->withToken($token)->deleteJson("/api/books/{$book->slug}")->assertForbidden();

        $this->assertDatabaseCount('books', 1);
        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => 'Student API Book']);
    }

    public function test_book_api_returns_422_for_missing_required_create_fields(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'password' => 'password']);
        $token = $this->tokenFor($staff);

        $this->withToken($token)->postJson('/api/books', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'category',
                'title',
                'author',
                'description',
                'content',
                'total_copies',
                'condition_status',
            ]);
        $this->assertDatabaseCount('books', 0);
    }

    public function test_book_api_returns_422_for_duplicate_barcodes(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'password' => 'password']);
        Book::create([
            'slug' => 'existing-barcode-book',
            'category' => 'book',
            'title' => 'Existing Barcode Book',
            'author' => 'Author',
            'description' => 'Description.',
            'content' => 'Content.',
            'barcode' => '9781234567890',
        ]);
        $token = $this->tokenFor($staff);

        $this->withToken($token)->postJson('/api/books', [
            'category' => 'book',
            'title' => 'Duplicate Barcode Book',
            'author' => 'Author',
            'description' => 'Description.',
            'content' => 'Content.',
            'total_copies' => 1,
            'barcode' => '9781234567890',
            'condition_status' => 'available',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['barcode']);
        $this->assertDatabaseCount('books', 1);
    }

    public function test_book_api_returns_422_when_copy_count_would_be_less_than_active_loans(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'password' => 'password']);
        $firstStudent = User::factory()->create(['role' => 'student']);
        $secondStudent = User::factory()->create(['role' => 'student']);
        $book = Book::create([
            'slug' => 'fully-loaned-api-book',
            'category' => 'book',
            'title' => 'Fully Loaned API Book',
            'author' => 'Author',
            'description' => 'Description.',
            'content' => 'Content.',
            'total_copies' => 2,
        ]);
        $token = $this->tokenFor($staff);

        foreach ([$firstStudent, $secondStudent] as $student) {
            $this->withToken($token)->postJson('/api/loans', [
                'book_id' => $book->id,
                'borrower_id' => $student->id,
                'due_at' => now()->addDays(14)->toDateString(),
            ])->assertCreated();
        }

        $this->withToken($token)->patchJson("/api/books/{$book->slug}", ['total_copies' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('errors.total_copies.0', 'This title already has 2 copies on loan.');
        $this->assertDatabaseHas('books', ['id' => $book->id, 'total_copies' => 2]);
    }

    public function test_book_api_returns_409_when_a_book_has_loans_or_reservations(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'password' => 'password']);
        $student = User::factory()->create(['role' => 'student']);
        $loanedBook = Book::create([
            'slug' => 'loaned-api-book',
            'category' => 'book',
            'title' => 'Loaned API Book',
            'author' => 'Author',
            'description' => 'Description.',
            'content' => 'Content.',
        ]);
        $reservedBook = Book::create([
            'slug' => 'reserved-api-book',
            'category' => 'book',
            'title' => 'Reserved API Book',
            'author' => 'Author',
            'description' => 'Description.',
            'content' => 'Content.',
        ]);
        BookReservation::create([
            'book_id' => $reservedBook->id,
            'student_id' => $student->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);
        $token = $this->tokenFor($staff);
        $this->withToken($token)->postJson('/api/loans', [
            'book_id' => $loanedBook->id,
            'borrower_id' => $student->id,
            'due_at' => now()->addDays(14)->toDateString(),
        ])->assertCreated();

        $this->withToken($token)->deleteJson("/api/books/{$loanedBook->slug}")
            ->assertConflict()
            ->assertJsonPath('message', 'A title with borrowing or reservation history cannot be deleted.');
        $this->withToken($token)->deleteJson("/api/books/{$reservedBook->slug}")
            ->assertConflict()
            ->assertJsonPath('message', 'A title with borrowing or reservation history cannot be deleted.');
        $this->assertModelExists($loanedBook);
        $this->assertModelExists($reservedBook);
    }

    private function tokenFor(User $user): string
    {
        return $this->postJson('/api/auth/token', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertCreated()->json('token');
    }
}
