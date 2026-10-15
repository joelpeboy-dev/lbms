<?php

namespace Tests\Feature;

use App\Models\Book;
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

    private function tokenFor(User $user): string
    {
        return $this->postJson('/api/auth/token', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertCreated()->json('token');
    }
}
