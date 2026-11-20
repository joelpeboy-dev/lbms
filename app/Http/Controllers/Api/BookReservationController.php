<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookReservationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $reservations = BookReservation::query()
            ->with(['book:id,title,slug', 'student:id,name', 'fulfiller:id,name', 'loan:id'])
            ->when(! $user->isAdmin() && $user->role !== 'staff', fn ($query) => $query->where('student_id', $user->id))
            ->latest('requested_at')
            ->paginate(20)
            ->through(fn (BookReservation $reservation): array => $this->reservationData($reservation));

        return response()->json($reservations);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'student_id' => ['required', 'integer', 'exists:users,id'],
            'status' => ['sometimes', Rule::in(['pending', 'fulfilled', 'cancelled'])],
            'requested_at' => ['sometimes', 'date'],
        ]);

        $reservation = BookReservation::create([
            ...$data,
            'status' => $data['status'] ?? 'pending',
            'requested_at' => $data['requested_at'] ?? now(),
        ]);

        return response()->json([
            'message' => 'Reservation created successfully.',
            'data' => $this->reservationData($reservation->load(['book:id,title,slug', 'student:id,name', 'fulfiller:id,name', 'loan:id'])),
        ], 201);
    }

    public function show(Request $request, BookReservation $reservation): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user->isAdmin() && $user->role !== 'staff' && $reservation->student_id !== $user->id, 404);

        return response()->json([
            'data' => $this->reservationData($reservation->load(['book:id,title,slug', 'student:id,name', 'fulfiller:id,name', 'loan:id'])),
        ]);
    }

    public function update(Request $request, BookReservation $reservation): JsonResponse
    {
        $partial = $request->isMethod('PATCH');
        $required = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'book_id' => [$required, 'integer', 'exists:books,id'],
            'student_id' => [$required, 'integer', 'exists:users,id'],
            'status' => ['sometimes', Rule::in(['pending', 'fulfilled', 'cancelled'])],
            'requested_at' => ['sometimes', 'date'],
            'fulfilled_at' => ['nullable', 'date'],
        ]);

        if (($data['status'] ?? null) === 'fulfilled' && ! isset($data['fulfilled_at'])) {
            $data['fulfilled_at'] = now();
        }
        if (($data['status'] ?? null) !== 'fulfilled') {
            $data['fulfilled_at'] = $data['fulfilled_at'] ?? null;
        }

        $reservation->update($data);

        return response()->json([
            'message' => 'Reservation updated successfully.',
            'data' => $this->reservationData($reservation->fresh(['book:id,title,slug', 'student:id,name', 'fulfiller:id,name', 'loan:id'])),
        ]);
    }

    public function destroy(BookReservation $reservation): JsonResponse
    {
        if ($reservation->loan_id !== null) {
            return response()->json([
                'message' => 'A reservation linked to a loan cannot be deleted.',
            ], 409);
        }

        $reservation->delete();

        return response()->json(['message' => 'Reservation deleted successfully.']);
    }

    private function reservationData(BookReservation $reservation): array
    {
        return [
            'id' => $reservation->id,
            'book' => $reservation->book ? ['id' => $reservation->book->id, 'title' => $reservation->book->title, 'slug' => $reservation->book->slug] : null,
            'student' => $reservation->student ? ['id' => $reservation->student->id, 'name' => $reservation->student->name] : null,
            'status' => $reservation->status,
            'requested_at' => $reservation->requested_at?->toIso8601String(),
            'fulfilled_at' => $reservation->fulfilled_at?->toIso8601String(),
            'loan_id' => $reservation->loan_id,
        ];
    }
}
