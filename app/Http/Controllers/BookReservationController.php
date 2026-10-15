<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookLoan;
use App\Models\BookReservation;
use App\Models\LibraryAuditLog;
use App\Notifications\LibraryMessage;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookReservationController extends Controller
{
    public function index(): View
    {
        abort_unless(in_array(auth()->user()->role, ['admin', 'staff'], true), 403);

        return view('reservations.index', [
            'reservations' => BookReservation::query()
                ->with([
                    'book' => fn ($query) => $query->withCount([
                        'loans as active_loans_count' => fn ($loans) => $loans->whereNull('returned_at'),
                    ]),
                    'student',
                    'fulfiller',
                    'loan',
                ])
                ->where('status', 'pending')
                ->oldest('requested_at')
                ->paginate(20),
            'nextReservationByBook' => BookReservation::query()
                ->where('status', 'pending')
                ->orderBy('requested_at')
                ->orderBy('id')
                ->get(['id', 'book_id'])
                ->unique('book_id')
                ->keyBy('book_id'),
        ]);
    }

    public function store(Request $request, Book $book): RedirectResponse
    {
        abort_unless($request->user()->role === 'student', 403);

        DB::transaction(function () use ($book, $request): void {
            $book = Book::query()->lockForUpdate()->findOrFail($book->id);

            if ($book->availableCopies() > 0) {
                throw ValidationException::withMessages([
                    'reservation' => 'This book is available to borrow and cannot be reserved.',
                ]);
            }

            if ($book->condition_status !== 'available') {
                throw ValidationException::withMessages([
                    'reservation' => 'This title is marked '.$book->condition_status.' and cannot be reserved.',
                ]);
            }

            if ($request->user()->bookLoans()->where('book_id', $book->id)->whereNull('returned_at')->exists()) {
                throw ValidationException::withMessages([
                    'reservation' => 'You already have an active loan for this book.',
                ]);
            }

            $alreadyReserved = BookReservation::query()
                ->where('book_id', $book->id)
                ->where('student_id', $request->user()->id)
                ->where('status', 'pending')
                ->exists();

            if ($alreadyReserved) {
                throw ValidationException::withMessages([
                    'reservation' => 'You already have a pending reservation for this book.',
                ]);
            }

            $reservation = BookReservation::create([
                'book_id' => $book->id,
                'student_id' => $request->user()->id,
                'status' => 'pending',
                'requested_at' => now(),
            ]);
            LibraryAuditLog::record('reservation.created', $reservation, ['book_id' => $book->id]);
        });

        return redirect()->route('reservations.mine')->with('success', 'Book reservation submitted.');
    }

    public function mine(Request $request): View
    {
        abort_unless($request->user()->role === 'student', 403);

        return view('reservations.mine', [
            'reservations' => $request->user()->bookReservations()
                ->with(['book', 'fulfiller', 'loan'])
                ->latest('requested_at')
                ->paginate(20),
        ]);
    }

    public function cancel(Request $request, BookReservation $reservation): RedirectResponse
    {
        abort_unless($request->user()->role === 'student', 403);

        DB::transaction(function () use ($request, $reservation): void {
            $reservation = $request->user()->bookReservations()
                ->lockForUpdate()
                ->findOrFail($reservation->id);

            if ($reservation->status !== 'pending') {
                throw ValidationException::withMessages([
                    'reservation' => 'Only pending reservations can be cancelled.',
                ]);
            }

            $reservation->update(['status' => 'cancelled']);
            LibraryAuditLog::record('reservation.cancelled', $reservation);
        });

        return redirect()->route('reservations.mine')->with('success', 'Reservation cancelled.');
    }

    public function fulfill(Request $request, BookReservation $reservation): RedirectResponse
    {
        abort_unless(in_array($request->user()->role, ['admin', 'staff'], true), 403);

        $data = $request->validate([
            'due_at' => ['required', 'date', 'after_or_equal:today'],
        ]);

        DB::transaction(function () use ($data, $request, $reservation): void {
            $reservation = BookReservation::query()->lockForUpdate()->findOrFail($reservation->id);

            if ($reservation->status !== 'pending') {
                throw ValidationException::withMessages([
                    'reservation' => 'This reservation is no longer pending.',
                ]);
            }

            $book = Book::query()->lockForUpdate()->findOrFail($reservation->book_id);
            $nextReservationId = BookReservation::query()
                ->where('book_id', $book->id)
                ->where('status', 'pending')
                ->orderBy('requested_at')
                ->orderBy('id')
                ->value('id');

            if ((int) $nextReservationId !== $reservation->id) {
                throw ValidationException::withMessages([
                    'reservation' => 'Reservations must be issued in request order.',
                ]);
            }

            if ($book->availableCopies() < 1) {
                throw ValidationException::withMessages([
                    'reservation' => 'There are no available copies to issue for this reservation.',
                ]);
            }

            if (BookLoan::query()
                ->where('borrower_id', $reservation->student_id)
                ->where('book_id', $book->id)
                ->whereNull('returned_at')
                ->exists()) {
                throw ValidationException::withMessages([
                    'reservation' => 'This student already has an active loan for this book.',
                ]);
            }

            $loan = BookLoan::create([
                'book_id' => $book->id,
                'borrower_id' => $reservation->student_id,
                'issued_by' => $request->user()->id,
                'borrowed_at' => now(),
                'due_at' => Carbon::parse($data['due_at'])->endOfDay(),
            ]);

            $reservation->update([
                'status' => 'fulfilled',
                'fulfilled_by' => $request->user()->id,
                'loan_id' => $loan->id,
                'fulfilled_at' => now(),
            ]);
            LibraryAuditLog::record('reservation.fulfilled', $reservation, [
                'loan_id' => $loan->id,
                'student_id' => $reservation->student_id,
            ]);
        });

        $reservation = BookReservation::query()
            ->with(['student', 'book', 'loan'])
            ->findOrFail($reservation->id);
        $reservation->student->notify(new LibraryMessage(
            'Your book reservation is ready',
            $reservation->book->title.' has been issued to you. It is due on '.$reservation->loan->due_at->format('F j, Y').'.',
            route('loans.mine'),
            'View my loans',
        ));

        return redirect()->route('reservations.index')->with('success', 'Reservation issued as a book loan.');
    }
}
