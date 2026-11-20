<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LibraryAuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index(): JsonResponse
    {
        $students = User::query()
            ->where('role', 'student')
            ->orderBy('name')
            ->paginate(20)
            ->through(fn (User $student): array => $this->studentData($student));

        return response()->json($students);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->studentValidationRules());
        $student = DB::transaction(function () use ($data, $request): User {
            $student = User::create([
                ...$data,
                'name' => trim($data['first_name'].' '.$data['last_name']),
                'role' => 'student',
                'password' => Str::random(64),
            ]);
            LibraryAuditLog::record('user.created', $student, [
                'role' => 'student',
                'source' => 'api',
            ], $request->user());

            return $student;
        });

        return response()->json([
            'message' => 'Student created successfully.',
            'data' => $this->studentData($student),
        ], 201);
    }

    public function show(User $student): JsonResponse
    {
        $student = $this->findStudentOrFail($student);

        return response()->json([
            'data' => $this->studentData($student),
        ]);
    }

    public function update(Request $request, User $student): JsonResponse
    {
        $student = $this->findStudentOrFail($student);
        $data = $request->validate($this->studentValidationRules(
            $student,
            partial: $request->isMethod('PATCH'),
        ));

        if ($data === []) {
            return response()->json([
                'message' => 'Provide at least one student field to update.',
            ], 422);
        }

        if (array_key_exists('first_name', $data) || array_key_exists('last_name', $data)) {
            $existingNameParts = preg_split('/\s+/', trim($student->name), 2) ?: [];
            $data['first_name'] = $data['first_name'] ?? $student->first_name ?? ($existingNameParts[0] ?? '');
            $data['last_name'] = $data['last_name'] ?? $student->last_name ?? ($existingNameParts[1] ?? '');
            $data['name'] = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));
        }

        $student = DB::transaction(function () use ($student, $data, $request): User {
            $student = User::query()
                ->where('role', 'student')
                ->lockForUpdate()
                ->findOrFail($student->id);
            $student->update($data);
            LibraryAuditLog::record('user.updated', $student, [
                'changed_fields' => array_keys($student->getChanges()),
                'source' => 'api',
            ], $request->user());

            return $student;
        });

        return response()->json([
            'message' => 'Student updated successfully.',
            'data' => $this->studentData($student),
        ]);
    }

    public function destroy(Request $request, User $student): JsonResponse
    {
        $student = $this->findStudentOrFail($student);
        $deleted = DB::transaction(function () use ($student, $request): bool {
            $student = User::query()
                ->where('role', 'student')
                ->lockForUpdate()
                ->findOrFail($student->id);

            if ($student->bookLoans()->exists() || $student->bookReservations()->exists()) {
                return false;
            }

            LibraryAuditLog::record('user.deleted', $student, [
                'role' => 'student',
                'name' => $student->name,
                'source' => 'api',
            ], $request->user());
            $student->tokens()->delete();
            $student->delete();

            return true;
        });

        if (! $deleted) {
            return response()->json([
                'message' => 'A student with loan or reservation history cannot be deleted.',
            ], 409);
        }

        return response()->json(['message' => 'Student deleted successfully.']);
    }

    private function studentValidationRules(?User $student = null, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $studentIdRule = Rule::unique('users', 'student_id');
        $emailRule = Rule::unique('users', 'email');

        if ($student !== null) {
            $studentIdRule->ignore($student->id);
            $emailRule->ignore($student->id);
        }

        return [
            'student_id' => [$required, 'string', 'max:50', $studentIdRule],
            'first_name' => [$required, 'string', 'max:100'],
            'last_name' => [$required, 'string', 'max:100'],
            'email' => [$required, 'email', 'max:255', $emailRule],
            'course' => [$required, 'string', 'max:150'],
            'year_level' => [$required, 'integer', 'between:1,6'],
        ];
    }

    private function findStudentOrFail(User $user): User
    {
        return User::query()
            ->where('role', 'student')
            ->findOrFail($user->id);
    }

    /**
     * @return array{id: int, student_id: string|null, first_name: string|null, last_name: string|null, email: string, course: string|null, year_level: int|null}
     */
    private function studentData(User $student): array
    {
        return [
            'id' => $student->id,
            'student_id' => $student->student_id,
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'email' => $student->email,
            'course' => $student->course,
            'year_level' => $student->year_level,
        ];
    }
}
