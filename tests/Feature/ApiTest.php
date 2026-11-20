<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_tokens_are_hashed_and_can_be_revoked(): void
    {
        $user = User::factory()->create(['role' => 'student', 'password' => 'password']);
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonPath('token_type', 'Bearer');
        $plainTextToken = $response->json('token');
        $this->assertNotEmpty($plainTextToken);
        [$tokenId, $tokenSecret] = explode('|', $plainTextToken, 2);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $tokenId,
            'tokenable_id' => $user->id,
            'token' => hash('sha256', $tokenSecret),
        ]);

        $this->withToken($plainTextToken)->getJson('/api/books')->assertOk();
        $this->withToken($plainTextToken)->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'The API token has been revoked.');
        Auth::forgetGuards();
        $this->withToken($plainTextToken)->getJson('/api/books')->assertUnauthorized();
    }

    public function test_registration_creates_a_student_and_returns_a_sanctum_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'New Student',
            'email' => 'new.student@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertCreated()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.role', 'student')
            ->assertJsonPath('user.is_admin', false);
        $this->assertDatabaseHas('users', [
            'email' => 'new.student@example.com',
            'role' => 'student',
            'is_admin' => false,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $response->json('user.id'),
        ]);

        $this->withToken($response->json('token'))->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', 'new.student@example.com')
            ->assertJsonMissingPath('data.password');
    }

    public function test_api_login_returns_401_for_invalid_credentials_and_422_for_invalid_input(): void
    {
        $this->postJson('/api/login', [
            'email' => 'missing@example.com',
            'password' => 'incorrect',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'The supplied credentials are invalid.');

        $this->postJson('/api/login', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_unauthenticated_api_requests_without_an_accept_header_return_json_401(): void
    {
        $this->get('/api/books')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
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
        $this->assertDatabaseHas('book_transactions', [
            'book_loan_id' => $loanId,
            'transaction_type' => 'returned',
        ]);
        $this->assertDatabaseCount('book_transactions', 1);
        $this->assertDatabaseMissing('book_transactions', [
            'book_loan_id' => $loanId,
            'transaction_type' => 'borrowed',
        ]);
    }

    public function test_internal_api_can_issue_return_and_list_borrowing_transactions(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $student = User::factory()->create(['role' => 'student']);
        $book = Book::create([
            'slug' => 'internal-transaction-book',
            'category' => 'book',
            'title' => 'Internal Transaction Book',
            'author' => 'Author',
            'description' => 'Description.',
            'content' => 'Content.',
        ]);
        $token = $this->tokenFor($staff);

        $this->getJson('/api/internal/transactions')->assertUnauthorized();

        $borrow = $this->withToken($token)->postJson('/api/internal/transactions', [
            'book_id' => $book->id,
            'borrower_id' => $student->id,
            'due_at' => now()->addDays(14)->toDateString(),
        ])->assertCreated();
        $transactionId = $borrow->json('data.id');
        $loanId = $borrow->json('data.loan_id');

        $this->withToken($token)->getJson('/api/internal/transactions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'borrowed')
            ->assertJsonPath('data.0.loan_id', $loanId)
            ->assertJsonPath('data.0.id', $transactionId);

        $this->withToken($token)->getJson("/api/internal/transactions?id={$transactionId}")
            ->assertOk()
            ->assertJsonPath('data.type', 'borrowed');

        $this->withToken($token)->patchJson('/api/internal/transactions', [
            'id' => $transactionId,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Book return recorded.')
            ->assertJsonPath('data.type', 'returned')
            ->assertJsonPath('data.id', $transactionId);

        $this->withToken($token)->getJson('/api/internal/transactions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'returned')
            ->assertJsonPath('data.0.id', $transactionId);
        $this->assertDatabaseCount('book_transactions', 1);
    }

    public function test_internal_delete_voids_active_borrow_and_removes_its_status_record(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $student = User::factory()->create(['role' => 'student']);
        $book = Book::create([
            'slug' => 'internal-void-book',
            'category' => 'book',
            'title' => 'Internal Void Book',
            'author' => 'Author',
            'description' => 'Description.',
            'content' => 'Content.',
        ]);
        $token = $this->tokenFor($staff);

        $borrow = $this->withToken($token)->postJson('/api/internal/transactions', [
            'book_id' => $book->id,
            'borrower_id' => $student->id,
            'due_at' => now()->addDays(14)->toDateString(),
        ])->assertCreated();
        $transactionId = $borrow->json('data.id');
        $loanId = $borrow->json('data.loan_id');

        $this->withToken($token)->deleteJson('/api/internal/transactions', [
            'id' => $transactionId,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Borrowing transaction voided and removed.');

        $this->assertDatabaseHas('book_loans', [
            'id' => $loanId,
            'voided_by' => $staff->id,
        ]);
        $this->assertDatabaseMissing('book_transactions', ['id' => $transactionId]);
        $this->assertDatabaseCount('book_transactions', 0);
        $this->withToken($token)->getJson('/api/internal/transactions')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->withToken($token)->deleteJson('/api/internal/transactions', [
            'id' => $transactionId,
        ])
            ->assertNotFound();
    }

    public function test_internal_return_and_delete_require_an_id_on_the_shared_endpoint(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $token = $this->tokenFor($staff);

        $this->withToken($token)->patchJson('/api/internal/transactions')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id');
        $this->withToken($token)->deleteJson('/api/internal/transactions')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id');
    }

    public function test_internal_borrowing_api_requires_a_staff_or_admin_user(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $token = $this->tokenFor($student);

        $this->withToken($token)
            ->getJson('/api/internal/transactions')
            ->assertForbidden();
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

    public function test_internal_user_endpoint_requires_a_valid_sanctum_token(): void
    {
        $user = User::factory()->create([
            'email' => 'internal.admin@example.com',
            'role' => 'admin',
            'is_admin' => true,
            'password' => 'password',
        ]);

        $token = $this->tokenFor($user);

        $this->getJson('/api/internal/users')->assertUnauthorized();
        $this->withHeader('X-API-KEY', 'internal-secret-key')->getJson('/api/internal/users')
            ->assertUnauthorized();

        $this->withToken($token)->getJson('/api/internal/users')
            ->assertOk()
            ->assertJsonPath('data.0.email', $user->email);
    }

    public function test_internal_user_endpoint_rejects_an_invalid_bearer_token(): void
    {
        $this->withToken('invalid-token')->getJson('/api/internal/users')->assertUnauthorized();
    }

    public function test_admin_users_bypass_student_loan_scope_and_view_all_loans(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true, 'password' => 'password']);
        $firstStudent = User::factory()->create(['role' => 'student']);
        $secondStudent = User::factory()->create(['role' => 'student']);
        $firstBook = Book::create([
            'slug' => 'admin-loan-book-one',
            'category' => 'book',
            'title' => 'Admin Loan Book One',
            'author' => 'Author',
            'description' => 'Description.',
            'content' => 'Content.',
        ]);
        $secondBook = Book::create([
            'slug' => 'admin-loan-book-two',
            'category' => 'book',
            'title' => 'Admin Loan Book Two',
            'author' => 'Author',
            'description' => 'Description.',
            'content' => 'Content.',
        ]);

        $adminToken = $this->tokenFor($admin);
        $this->withToken($adminToken)->postJson('/api/loans', [
            'book_id' => $firstBook->id,
            'borrower_id' => $firstStudent->id,
            'due_at' => now()->addDays(14)->toDateString(),
        ])->assertCreated();
        $this->withToken($adminToken)->postJson('/api/loans', [
            'book_id' => $secondBook->id,
            'borrower_id' => $secondStudent->id,
            'due_at' => now()->addDays(14)->toDateString(),
        ])->assertCreated();

        $this->withToken($adminToken)->getJson('/api/loans')
            ->assertOk()
            ->assertJsonCount(2, 'data');
        $this->withToken($adminToken)->getJson('/api/transactions')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $studentToken = $this->tokenFor($firstStudent);
        $this->withToken($studentToken)->getJson('/api/transactions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.borrower.id', $firstStudent->id);
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

    public function test_staff_can_complete_reservation_crud_through_the_api(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'password' => 'password']);
        $student = User::factory()->create(['role' => 'student']);
        $book = Book::create([
            'slug' => 'reservation-crud-book',
            'category' => 'book',
            'title' => 'Reservation CRUD Book',
            'author' => 'Author',
            'description' => 'Description.',
            'content' => 'Content.',
        ]);
        $token = $this->tokenFor($staff);

        $created = $this->withToken($token)->postJson('/api/reservations', [
            'book_id' => $book->id,
            'student_id' => $student->id,
            'status' => 'pending',
        ])->assertCreated();
        $reservationId = $created->json('data.id');

        $this->withToken($token)->getJson("/api/reservations/{$reservationId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');
        $this->withToken($token)->patchJson("/api/reservations/{$reservationId}", [
            'status' => 'fulfilled',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'fulfilled');
        $this->assertDatabaseHas('book_reservations', [
            'id' => $reservationId,
            'status' => 'fulfilled',
        ]);

        $this->withToken($token)->deleteJson("/api/reservations/{$reservationId}")
            ->assertOk()
            ->assertJsonPath('message', 'Reservation deleted successfully.');
        $this->assertDatabaseMissing('book_reservations', ['id' => $reservationId]);
        $this->withToken($token)->getJson("/api/reservations/{$reservationId}")
            ->assertNotFound();
    }

    public function test_staff_can_complete_attendance_crud_through_the_api(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'password' => 'password']);
        $student = User::factory()->create(['role' => 'student']);
        $token = $this->tokenFor($staff);

        $created = $this->withToken($token)->postJson('/api/attendances', [
            'user_id' => $student->id,
            'time_in' => now()->subHour()->toIso8601String(),
        ])->assertCreated();
        $attendanceId = $created->json('data.id');

        $this->withToken($token)->getJson("/api/attendances/{$attendanceId}")
            ->assertOk()
            ->assertJsonPath('data.user.id', $student->id);
        $this->withToken($token)->patchJson("/api/attendances/{$attendanceId}", [
            'time_out' => now()->toIso8601String(),
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $attendanceId);
        $this->assertDatabaseHas('attendances', [
            'id' => $attendanceId,
            'user_id' => $student->id,
        ]);

        $this->withToken($token)->deleteJson("/api/attendances/{$attendanceId}")
            ->assertOk()
            ->assertJsonPath('message', 'Attendance record deleted successfully.');
        $this->assertDatabaseMissing('attendances', ['id' => $attendanceId]);
        $this->withToken($token)->getJson("/api/attendances/{$attendanceId}")
            ->assertNotFound();
    }

    public function test_staff_can_update_and_delete_an_active_loan_through_the_api(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'password' => 'password']);
        $firstStudent = User::factory()->create(['role' => 'student']);
        $secondStudent = User::factory()->create(['role' => 'student']);
        $book = Book::create([
            'slug' => 'loan-crud-book',
            'category' => 'book',
            'title' => 'Loan CRUD Book',
            'author' => 'Author',
            'description' => 'Description.',
            'content' => 'Content.',
        ]);
        $token = $this->tokenFor($staff);

        $created = $this->withToken($token)->postJson('/api/loans', [
            'book_id' => $book->id,
            'borrower_id' => $firstStudent->id,
            'due_at' => now()->addDays(14)->toDateString(),
        ])->assertCreated();
        $loanId = $created->json('data.id');

        $this->withToken($token)->getJson("/api/loans/{$loanId}")
            ->assertOk()
            ->assertJsonPath('data.borrower.id', $firstStudent->id);
        $this->withToken($token)->patchJson("/api/loans/{$loanId}", [
            'borrower_id' => $secondStudent->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.borrower.id', $secondStudent->id);
        $this->assertDatabaseHas('book_loans', [
            'id' => $loanId,
            'borrower_id' => $secondStudent->id,
        ]);

        $this->withToken($token)->deleteJson("/api/loans/{$loanId}")
            ->assertOk()
            ->assertJsonPath('message', 'Loan deleted successfully.');
        $this->assertDatabaseMissing('book_loans', ['id' => $loanId]);
        $this->withToken($token)->getJson("/api/loans/{$loanId}")
            ->assertNotFound();
    }

    private function tokenFor(User $user): string
    {
        $token = $this->postJson('/api/auth/token', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->json('token');

        Auth::forgetGuards();

        return $token;
    }
}
