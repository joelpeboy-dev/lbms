<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $attendances = Attendance::query()
            ->with(['user:id,name,role', 'recorder:id,name'])
            ->when(! $user->isAdmin() && $user->role !== 'staff', fn ($query) => $query->where('user_id', $user->id))
            ->latest('time_in')
            ->paginate(20)
            ->through(fn (Attendance $attendance): array => $this->attendanceData($attendance));

        return response()->json($attendances);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereIn('role', ['staff', 'student'])],
            'time_in' => ['required', 'date'],
            'time_out' => ['nullable', 'date', 'after_or_equal:time_in'],
        ]);

        if (isset($data['time_out']) === false && Attendance::query()->where('user_id', $data['user_id'])->whereNull('time_out')->exists()) {
            return response()->json([
                'message' => 'The user already has an active attendance record.',
                'errors' => ['user_id' => ['The user already has an active attendance record.']],
            ], 422);
        }

        $attendance = Attendance::create([
            ...$data,
            'recorded_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Attendance record created successfully.',
            'data' => $this->attendanceData($attendance->load(['user:id,name,role', 'recorder:id,name'])),
        ], 201);
    }

    public function show(Request $request, Attendance $attendance): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user->isAdmin() && $user->role !== 'staff' && $attendance->user_id !== $user->id, 404);

        return response()->json([
            'data' => $this->attendanceData($attendance->load(['user:id,name,role', 'recorder:id,name'])),
        ]);
    }

    public function update(Request $request, Attendance $attendance): JsonResponse
    {
        $partial = $request->isMethod('PATCH');
        $required = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'user_id' => [$required, 'integer', Rule::exists('users', 'id')->whereIn('role', ['staff', 'student'])],
            'time_in' => [$required, 'date'],
            'time_out' => ['nullable', 'date'],
        ]);

        $timeIn = isset($data['time_in']) ? Carbon::parse($data['time_in']) : $attendance->time_in;
        if (array_key_exists('time_out', $data) && $data['time_out'] !== null && $timeIn !== null && Carbon::parse($data['time_out'])->lessThan($timeIn)) {
            throw ValidationException::withMessages([
                'time_out' => 'The time out must be at or after the time in.',
            ]);
        }

        $attendance->update($data);

        return response()->json([
            'message' => 'Attendance record updated successfully.',
            'data' => $this->attendanceData($attendance->fresh(['user:id,name,role', 'recorder:id,name'])),
        ]);
    }

    public function destroy(Attendance $attendance): JsonResponse
    {
        $attendance->delete();

        return response()->json(['message' => 'Attendance record deleted successfully.']);
    }

    private function attendanceData(Attendance $attendance): array
    {
        return [
            'id' => $attendance->id,
            'user' => $attendance->user ? ['id' => $attendance->user->id, 'name' => $attendance->user->name, 'role' => $attendance->user->role] : null,
            'recorded_by' => $attendance->recorder ? ['id' => $attendance->recorder->id, 'name' => $attendance->recorder->name] : null,
            'time_in' => $attendance->time_in?->toIso8601String(),
            'time_out' => $attendance->time_out?->toIso8601String(),
        ];
    }
}
