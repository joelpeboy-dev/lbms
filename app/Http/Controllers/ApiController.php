<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\Book;
use App\Models\BookLoan;
use App\Models\LibraryAuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApiController extends Controller
{
    public function issueToken(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:100'],
        ]);
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'The supplied credentials are invalid.'], 401);
        }

        $plainTextToken = Str::random(80);
        $expiresAt = now()->addDays(30);
        ApiToken::create([
            'user_id' => $user->id,
            'name' => $credentials['name'] ?? 'API access',
            'token_hash' => hash('sha256', $plainTextToken),
            'expires_at' => $expiresAt,
        ]);

        return response()->json([
            'token_type' => 'Bearer',
            'token' => $plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
        ], 201);
    }

    public function revokeToken(Request $request): JsonResponse
    {
        $request->attributes->get('api_token')->delete();

        return response()->json(['message' => 'The API token has been revoked.']);
    }

    public function categories(): JsonResponse
    {
        return response()->json([
            'data' => [
                ['id' => 'book', 'name' => 'Other books'],
                ['id' => 'study', 'name' => 'Study'],
                ['id' => 'manga', 'name' => 'Manga'],
            ],
        ]);
    }

    public function books(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(['book', 'study', 'manga'])],
            'genre' => ['nullable', Rule::in(array_keys(config('library.genres')))],
        ]);
        $books = Book::query()
            ->withCount(['loans as active_loans_count' => fn (Builder $query) => $query->whereNull('returned_at')])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('title', 'like', "%{$search}%")
                ->orWhere('author', 'like', "%{$search}%")
                ->orWhere('genre', 'like', "%{$search}%")
                ->orWhere('barcode', $search)))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($filters['genre'] ?? null, fn (Builder $query, string $genre) => $query->where('genre', $genre))
            ->orderBy('title')
            ->paginate(20)
            ->through(fn (Book $book) => $this->bookData($book));

        return response()->json($books);
    }

    public function book(Book $book): JsonResponse
    {
        $book->loadCount(['loans as active_loans_count' => fn (Builder $query) => $query->whereNull('returned_at')]);

        return response()->json(['data' => $this->bookData($book)]);
    }

    public function loans(Request $request): JsonResponse
    {
        $user = $request->user();
        $loans = BookLoan::query()
            ->with(['book:id,title,slug', 'borrower:id,name', 'issuer:id,name', 'returner:id,name'])
            ->when($user->role === 'student', fn (Builder $query) => $query->where('borrower_id', $user->id))
            ->latest('borrowed_at')
            ->paginate(20)
            ->through(fn (BookLoan $loan) => $this->loanData($loan));

        return response()->json($loans);
    }

    public function createLoan(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'borrower_id' => [
                Rule::requiredIf($user->role !== 'student'),
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', 'student'),
            ],
            'due_at' => ['required', 'date', 'after_or_equal:today'],
        ]);
        $borrowerId = $user->role === 'student' ? $user->id : $data['borrower_id'];

        $loan = DB::transaction(function () use ($data, $borrowerId, $user): BookLoan {
            $book = Book::query()->lockForUpdate()->findOrFail($data['book_id']);

            if ($book->condition_status !== 'available' || $book->loans()->whereNull('returned_at')->count() >= $book->total_copies) {
                throw ValidationException::withMessages(['book_id' => 'There are no available copies of this title.']);
            }

            $loan = BookLoan::create([
                'book_id' => $book->id,
                'borrower_id' => $borrowerId,
                'issued_by' => $user->id,
                'borrowed_at' => now(),
                'due_at' => Carbon::parse($data['due_at'])->endOfDay(),
            ]);
            LibraryAuditLog::record('loan.issued', $loan, [
                'book_id' => $book->id,
                'borrower_id' => $borrowerId,
                'source' => 'api',
            ], $user);

            return $loan;
        });

        return response()->json([
            'data' => $this->loanData($loan->load(['book:id,title,slug', 'borrower:id,name', 'issuer:id,name'])),
        ], 201);
    }

    public function returnLoan(Request $request, BookLoan $loan): JsonResponse
    {
        DB::transaction(function () use ($request, $loan): void {
            $loan = BookLoan::query()->lockForUpdate()->findOrFail($loan->id);

            if ($loan->returned_at !== null) {
                throw ValidationException::withMessages(['loan' => 'This book has already been returned.']);
            }

            $loan->update([
                'returned_at' => now(),
                'returned_by' => $request->user()->id,
                'fine_amount' => $loan->overdueFineAmount(now()),
            ]);
            LibraryAuditLog::record('loan.returned', $loan, ['source' => 'api'], $request->user());
        });

        return response()->json(['message' => 'Book return recorded.']);
    }

    public function report(): JsonResponse
    {
        $availableCopies = (int) Book::query()->where('condition_status', 'available')->sum('total_copies');
        $activeLoans = BookLoan::query()->whereNull('returned_at')->count();

        return response()->json([
            'data' => [
                'titles' => Book::query()->count(),
                'total_copies' => (int) Book::query()->sum('total_copies'),
                'available_copies' => max(0, $availableCopies - BookLoan::query()->whereNull('returned_at')->whereHas('book', fn ($query) => $query->where('condition_status', 'available'))->count()),
                'active_loans' => $activeLoans,
                'overdue_loans' => BookLoan::query()->whereNull('returned_at')->where('due_at', '<', now())->count(),
            ],
        ]);
    }

    private function bookData(Book $book): array
    {
        return [
            'id' => $book->id,
            'slug' => $book->slug,
            'title' => $book->title,
            'author' => $book->author,
            'category' => $book->category,
            'genre' => $book->genre,
            'description' => $book->description,
            'cover_url' => $book->cover_url,
            'barcode' => $book->barcode,
            'condition_status' => $book->condition_status,
            'total_copies' => $book->total_copies,
            'available_copies' => $book->condition_status === 'available'
                ? max(0, $book->total_copies - ($book->active_loans_count ?? 0))
                : 0,
        ];
    }

    private function loanData(BookLoan $loan): array
    {
        return [
            'id' => $loan->id,
            'book' => ['id' => $loan->book->id, 'title' => $loan->book->title, 'slug' => $loan->book->slug],
            'borrower' => ['id' => $loan->borrower->id, 'name' => $loan->borrower->name],
            'issued_by' => $loan->issuer?->name,
            'returned_by' => $loan->returner?->name,
            'borrowed_at' => $loan->borrowed_at->toIso8601String(),
            'due_at' => $loan->due_at->toIso8601String(),
            'returned_at' => $loan->returned_at?->toIso8601String(),
            'fine_amount' => (float) $loan->fine_amount,
            'fine_paid_at' => $loan->fine_paid_at?->toIso8601String(),
            'status' => $loan->returned_at ? 'returned' : ($loan->isOverdue() ? 'overdue' : 'borrowed'),
        ];
    }
}
