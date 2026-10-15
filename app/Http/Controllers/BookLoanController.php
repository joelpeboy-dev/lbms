<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookLoan;
use App\Models\LibraryAuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookLoanController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['borrowed', 'overdue', 'returned'])],
        ]);

        $loans = BookLoan::query()
            ->with(['book', 'borrower', 'issuer', 'returner'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereHas('book', fn (Builder $book) => $book->where('title', 'like', "%{$search}%"))
                        ->orWhereHas('borrower', fn (Builder $borrower) => $borrower->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(($filters['status'] ?? null) === 'returned', fn (Builder $query) => $query->whereNotNull('returned_at'))
            ->when(($filters['status'] ?? null) === 'borrowed', fn (Builder $query) => $query->whereNull('returned_at')->where('due_at', '>=', now()))
            ->when(($filters['status'] ?? null) === 'overdue', fn (Builder $query) => $query->whereNull('returned_at')->where('due_at', '<', now()))
            ->latest('borrowed_at')
            ->paginate(20)
            ->withQueryString();

        $books = Book::query()
            ->withCount(['loans as active_loans_count' => fn (Builder $query) => $query->whereNull('returned_at')])
            ->orderBy('title')
            ->get();

        return view('loans.index', [
            'loans' => $loans,
            'books' => $books,
            'borrowers' => User::query()->where('role', 'student')->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'borrower_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'student')],
            'due_at' => ['required', 'date', 'after_or_equal:today'],
        ]);

        DB::transaction(function () use ($data, $request): void {
            $book = Book::query()->lockForUpdate()->findOrFail($data['book_id']);
            $activeLoans = $book->loans()->whereNull('returned_at')->count();

            if ($book->condition_status !== 'available' || $activeLoans >= $book->total_copies) {
                throw ValidationException::withMessages([
                    'book_id' => 'There are no available copies of this title.',
                ]);
            }

            $loan = BookLoan::create([
                'book_id' => $book->id,
                'borrower_id' => $data['borrower_id'],
                'issued_by' => $request->user()->id,
                'borrowed_at' => now(),
                'due_at' => Carbon::parse($data['due_at'])->endOfDay(),
            ]);
            LibraryAuditLog::record('loan.issued', $loan, [
                'book_id' => $book->id,
                'borrower_id' => $data['borrower_id'],
            ]);
        });

        return redirect()->route('loans.index')->with('success', 'Book issued successfully.');
    }

    public function returnBook(Request $request, BookLoan $loan): RedirectResponse
    {
        DB::transaction(function () use ($request, $loan): void {
            $loan = BookLoan::query()->lockForUpdate()->findOrFail($loan->id);

            if ($loan->returned_at !== null) {
                throw ValidationException::withMessages([
                    'loan' => 'This book has already been returned.',
                ]);
            }

            $loan->update([
                'returned_at' => now(),
                'returned_by' => $request->user()->id,
                'fine_amount' => $loan->overdueFineAmount(now()),
            ]);
            LibraryAuditLog::record('loan.returned', $loan, [
                'borrower_id' => $loan->borrower_id,
                'fine_amount' => (float) $loan->fine_amount,
            ]);
        });

        return redirect()->route('loans.index')->with('success', 'Book return recorded.');
    }

    public function payFine(Request $request, BookLoan $loan): RedirectResponse
    {
        $loan = BookLoan::query()->findOrFail($loan->id);

        if ((float) $loan->fine_amount <= 0) {
            return redirect()->route('loans.index')->with('success', 'There is no outstanding fine for this loan.');
        }

        if ($loan->fine_paid_at !== null) {
            return redirect()->route('loans.index')->with('success', 'This fine has already been paid.');
        }

        $loan->update([
            'fine_paid_at' => now(),
        ]);
        LibraryAuditLog::record('loan.fine_paid', $loan, ['fine_amount' => (float) $loan->fine_amount]);

        return redirect()->route('loans.index')->with('success', 'Fine marked as paid.');
    }

    public function mine(Request $request): View
    {
        abort_unless($request->user()->role === 'student', 403);

        return view('loans.mine', [
            'loans' => $request->user()->bookLoans()
                ->with(['book', 'issuer', 'returner'])
                ->latest('borrowed_at')
                ->paginate(20),
        ]);
    }
}
