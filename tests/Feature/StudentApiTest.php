<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookLoan;
use App\Models\BookReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class StudentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_complete_student_crud_and_created_accounts_receive_a_random_password(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'password' => 'password']);
        $token = $this->tokenFor($staff);
        $payload = [
            'student_id' => '2026-0001',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan.delacruz@example.test',
            'course' => 'BS Information Technology',
            'year_level' => 2,
            'role' => 'admin',
            'password' => 'client-supplied-password',
        ];

        $created = $this->withToken($token)->postJson('/api/students', $payload)
            ->assertCreated()
            ->assertJsonPath('message', 'Student created successfully.')
            ->assertJsonPath('data.student_id', '2026-0001')
            ->assertJsonPath('data.first_name', 'Juan')
            ->assertJsonPath('data.last_name', 'Dela Cruz')
            ->assertJsonMissingPath('data.password');
        $studentId = $created->json('data.id');
        $student = User::query()->findOrFail($studentId);

        $this->assertSame('student', $student->role);
        $this->assertSame('Juan Dela Cruz', $student->name);
        $this->assertNotSame('client-supplied-password', $student->password);
        $this->assertDatabaseHas('users', [
            'id' => $studentId,
            'student_id' => '2026-0001',
            'email' => 'juan.delacruz@example.test',
            'course' => 'BS Information Technology',
            'year_level' => 2,
            'role' => 'student',
        ]);

        $this->withToken($token)->getJson('/api/students')
            ->assertOk()
            ->assertJsonPath('data.0.id', $studentId);
        $this->withToken($token)->getJson("/api/students/{$studentId}")
            ->assertOk()
            ->assertJsonPath('data.email', 'juan.delacruz@example.test');

        $this->withToken($token)->patchJson("/api/students/{$studentId}", [
            'first_name' => 'Jane',
            'course' => 'BS Computer Science',
            'role' => 'admin',
            'password' => 'changed-password',
        ])->assertOk()
            ->assertJsonPath('message', 'Student updated successfully.')
            ->assertJsonPath('data.first_name', 'Jane')
            ->assertJsonPath('data.last_name', 'Dela Cruz')
            ->assertJsonPath('data.course', 'BS Computer Science')
            ->assertJsonMissingPath('data.password');
        $student->refresh();
        $this->assertSame('Jane Dela Cruz', $student->name);
        $this->assertSame('student', $student->role);
        $this->assertDatabaseHas('users', [
            'id' => $studentId,
            'first_name' => 'Jane',
            'last_name' => 'Dela Cruz',
            'course' => 'BS Computer Science',
        ]);

        $this->withToken($token)->deleteJson("/api/students/{$studentId}")
            ->assertOk()
            ->assertJsonPath('message', 'Student deleted successfully.');
        $this->assertDatabaseMissing('users', ['id' => $studentId]);
        $this->withToken($token)->getJson("/api/students/{$studentId}")->assertNotFound();
        $this->assertDatabaseHas('library_audit_logs', [
            'action' => 'user.created',
            'subject_id' => $studentId,
        ]);
        $this->assertDatabaseHas('library_audit_logs', [
            'action' => 'user.updated',
            'subject_id' => $studentId,
        ]);
        $this->assertDatabaseHas('library_audit_logs', [
            'action' => 'user.deleted',
            'subject_id' => $studentId,
        ]);
    }

    public function test_student_api_returns_401_when_a_request_has_no_token(): void
    {
        $this->getJson('/api/students')->assertUnauthorized();
        $this->postJson('/api/students')->assertUnauthorized();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_student_api_returns_403_for_student_tokens_and_404_for_non_student_ids(): void
    {
        $student = User::factory()->create(['role' => 'student', 'password' => 'password']);
        $staff = User::factory()->create(['role' => 'staff', 'password' => 'password']);
        $studentToken = $this->tokenFor($student);
        $staffToken = $this->tokenFor($staff);

        $this->withToken($studentToken)->getJson('/api/students')->assertForbidden();
        Auth::forgetGuards();
        $this->withToken($staffToken)->getJson("/api/students/{$staff->id}")->assertNotFound();
    }

    public function test_student_api_returns_422_for_invalid_fields_and_duplicate_student_ids(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'password' => 'password']);
        $existingStudent = User::factory()->create([
            'role' => 'student',
            'student_id' => '2026-0001',
            'password' => 'password',
        ]);
        $token = $this->tokenFor($staff);

        $this->withToken($token)->postJson('/api/students', [
            'student_id' => '',
            'first_name' => '',
            'last_name' => '',
            'email' => 'not-an-email',
            'course' => '',
            'year_level' => 7,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'student_id',
                'first_name',
                'last_name',
                'email',
                'course',
                'year_level',
            ]);

        $this->withToken($token)->postJson('/api/students', [
            'student_id' => '2026-0001',
            'first_name' => 'Duplicate',
            'last_name' => 'Student',
            'email' => 'new.student@example.test',
            'course' => 'BS Information Technology',
            'year_level' => 1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['student_id']);

        $this->assertDatabaseCount('users', 2);
        $this->assertModelExists($existingStudent);
    }

    public function test_student_api_returns_422_for_an_empty_patch(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'password' => 'password']);
        $student = User::factory()->create(['role' => 'student']);
        $token = $this->tokenFor($staff);

        $this->withToken($token)->patchJson("/api/students/{$student->id}", [])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Provide at least one student field to update.');
        $this->assertDatabaseHas('users', ['id' => $student->id]);
    }

    public function test_student_api_returns_409_when_a_student_has_loans_or_reservations(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'password' => 'password']);
        $loanStudent = User::factory()->create(['role' => 'student']);
        $reservationStudent = User::factory()->create(['role' => 'student']);
        $book = Book::create([
            'slug' => 'student-delete-api-book',
            'category' => 'book',
            'title' => 'Student Delete API Book',
            'author' => 'Author',
            'description' => 'Description.',
            'content' => 'Content.',
        ]);
        BookLoan::create([
            'book_id' => $book->id,
            'borrower_id' => $loanStudent->id,
            'issued_by' => $staff->id,
            'borrowed_at' => now(),
            'due_at' => now()->addDays(14),
        ]);
        BookReservation::create([
            'book_id' => $book->id,
            'student_id' => $reservationStudent->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);
        $token = $this->tokenFor($staff);

        $this->withToken($token)->deleteJson("/api/students/{$loanStudent->id}")
            ->assertConflict()
            ->assertJsonPath('message', 'A student with loan or reservation history cannot be deleted.');
        $this->withToken($token)->deleteJson("/api/students/{$reservationStudent->id}")
            ->assertConflict()
            ->assertJsonPath('message', 'A student with loan or reservation history cannot be deleted.');
        $this->assertModelExists($loanStudent);
        $this->assertModelExists($reservationStudent);
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
