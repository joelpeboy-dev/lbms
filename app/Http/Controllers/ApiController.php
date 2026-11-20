<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookCategory;
use App\Models\BookLoan;
use App\Models\BookTransaction;
use App\Models\LibraryAuditLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApiController extends Controller
{
    public function categories(): JsonResponse
    {
        return response()->json([
            'data' => [
                ['id' => 'book', 'name' => 'Other books'],
                ['id' => 'study', 'name' => 'Study'],
                ['id' => 'manga', 'name' => 'Manga'],
            ],
            'book_categories' => BookCategory::query()
                ->orderBy('name')
                ->get(['slug', 'name'])
                ->map(fn (BookCategory $category): array => $this->categoryData($category)),
        ]);
    }

    public function showCategory(BookCategory $bookCategory): JsonResponse
    {
        return response()->json(['data' => $this->categoryData($bookCategory)]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('book_categories', 'name')],
        ]);
        $category = BookCategory::create([
            'name' => $data['name'],
            'slug' => (Str::slug($data['name']) ?: 'category').'-'.Str::lower(Str::random(6)),
        ]);
        LibraryAuditLog::record('book_category.created', $category, [
            'name' => $category->name,
            'source' => 'api',
        ], $request->user());

        return response()->json(['data' => $this->categoryData($category)], 201);
    }

    public function updateCategory(Request $request, BookCategory $bookCategory): JsonResponse
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('book_categories', 'name')->ignore($bookCategory->id),
            ],
        ]);

        $bookCategory->update($data);
        LibraryAuditLog::record('book_category.updated', $bookCategory, [
            'changed_fields' => array_keys($bookCategory->getChanges()),
            'source' => 'api',
        ], $request->user());

        return response()->json(['data' => $this->categoryData($bookCategory)]);
    }

    public function deleteCategory(Request $request, BookCategory $bookCategory): JsonResponse
    {
        if ($bookCategory->books()->exists()) {
            return response()->json([
                'message' => 'A category assigned to books cannot be deleted.',
            ], 409);
        }

        LibraryAuditLog::record('book_category.deleted', $bookCategory, [
            'name' => $bookCategory->name,
            'source' => 'api',
        ], $request->user());
        $bookCategory->delete();

        return response()->json(['message' => 'Book category deleted successfully.']);
    }

    public function books(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(['book', 'study', 'manga'])],
            'genre' => ['nullable', Rule::exists('book_categories', 'slug')],
        ]);
        $books = Book::query()
            ->withCount(['loans as active_loans_count' => fn (Builder $query) => $query->whereNull('returned_at')->whereNull('voided_at')])
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
        $book->loadCount(['loans as active_loans_count' => fn (Builder $query) => $query->whereNull('returned_at')->whereNull('voided_at')]);

        return response()->json(['data' => $this->bookData($book)]);
    }

    public function storeBook(Request $request): JsonResponse
    {
        $data = $request->validate($this->bookValidationRules());
        $data['slug'] = Str::slug($data['title']).'-'.Str::random(6);
        $book = Book::create($data);
        LibraryAuditLog::record('book.created', $book, [
            'title' => $book->title,
            'source' => 'api',
        ], $request->user());

        return response()->json(['data' => $this->bookData($book)], 201);
    }

    public function updateBook(Request $request, Book $book): JsonResponse
    {
        $data = $request->validate($this->bookValidationRules(
            $book,
            partial: $request->isMethod('PATCH'),
        ));

        $book = DB::transaction(function () use ($book, $data, $request): Book {
            $book = Book::query()->lockForUpdate()->findOrFail($book->id);
            $activeLoans = $book->loans()->whereNull('returned_at')->whereNull('voided_at')->count();
            $totalCopies = $data['total_copies'] ?? $book->total_copies;

            if ($totalCopies < $activeLoans) {
                throw ValidationException::withMessages([
                    'total_copies' => "This title already has {$activeLoans} copies on loan.",
                ]);
            }

            $book->update($data);
            LibraryAuditLog::record('book.updated', $book, [
                'changed_fields' => array_keys($book->getChanges()),
                'source' => 'api',
            ], $request->user());

            return $book->loadCount([
                'loans as active_loans_count' => fn (Builder $query) => $query->whereNull('returned_at')->whereNull('voided_at'),
            ]);
        });

        return response()->json(['data' => $this->bookData($book)]);
    }

    public function deleteBook(Request $request, Book $book): JsonResponse
    {
        $deleted = DB::transaction(function () use ($book, $request): bool {
            $book = Book::query()->lockForUpdate()->findOrFail($book->id);

            if ($book->loans()->exists() || $book->reservations()->exists()) {
                return false;
            }

            LibraryAuditLog::record('book.deleted', $book, [
                'title' => $book->title,
                'source' => 'api',
            ], $request->user());
            $book->delete();

            return true;
        });

        if (! $deleted) {
            return response()->json([
                'message' => 'A title with borrowing or reservation history cannot be deleted.',
            ], 409);
        }

        return response()->json(['message' => 'Book deleted successfully.']);
    }

    public function loans(Request $request): JsonResponse
    {
        $user = $request->user();
        $loans = BookLoan::query()
            ->with(['book:id,title,slug', 'borrower:id,name', 'issuer:id,name', 'returner:id,name', 'voider:id,name'])
            ->when(! $this->isAdminUser($user) && $user->role === 'student', fn (Builder $query) => $query->where('borrower_id', $user->id))
            ->latest('borrowed_at')
            ->paginate(20)
            ->through(fn (BookLoan $loan) => $this->loanData($loan));

        return response()->json($loans);
    }

    public function showLoan(Request $request, BookLoan $loan): JsonResponse
    {
        $user = $request->user();
        abort_if($user->role === 'student' && $loan->borrower_id !== $user->id, 404);

        return response()->json([
            'data' => $this->loanData($loan->load(['book:id,title,slug', 'borrower:id,name', 'issuer:id,name', 'returner:id,name', 'voider:id,name'])),
        ]);
    }

    public function updateLoan(Request $request, BookLoan $loan): JsonResponse
    {
        $partial = $request->isMethod('PATCH');
        $required = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'borrower_id' => [$required, 'integer', 'exists:users,id'],
            'due_at' => [$required, 'date', 'after_or_equal:today'],
        ]);

        if ($loan->returned_at !== null || $loan->voided_at !== null) {
            throw ValidationException::withMessages([
                'loan' => 'Cannot update a completed or voided loan.',
            ]);
        }

        $loan = DB::transaction(function () use ($loan, $data, $request): BookLoan {
            $loan = BookLoan::query()->lockForUpdate()->findOrFail($loan->id);
            $update = [];

            if (isset($data['borrower_id'])) {
                $update['borrower_id'] = $data['borrower_id'];
            }
            if (isset($data['due_at'])) {
                $update['due_at'] = Carbon::parse($data['due_at'])->endOfDay();
            }

            $loan->update($update);
            if (isset($data['borrower_id'])) {
                BookTransaction::query()
                    ->where('book_loan_id', $loan->id)
                    ->update(['borrower_id' => $data['borrower_id']]);
            }

            LibraryAuditLog::record('loan.updated', $loan, [
                'changed_fields' => array_keys($loan->getChanges()),
                'source' => 'api',
            ], $request->user());

            return $loan;
        });

        return response()->json([
            'message' => 'Loan updated successfully.',
            'data' => $this->loanData($loan->fresh(['book:id,title,slug', 'borrower:id,name', 'issuer:id,name', 'returner:id,name', 'voider:id,name'])),
        ]);
    }

    public function deleteLoan(Request $request, BookLoan $loan): JsonResponse
    {
        $deleted = DB::transaction(function () use ($loan, $request): bool {
            $loan = BookLoan::query()->lockForUpdate()->findOrFail($loan->id);

            if ($loan->returned_at !== null || $loan->voided_at !== null) {
                return false;
            }
            if ($loan->transaction()->exists()) {
                $loan->transaction()->delete();
            }

            LibraryAuditLog::record('loan.deleted', $loan, [
                'source' => 'api',
            ], $request->user());
            $loan->delete();

            return true;
        });

        if (! $deleted) {
            return response()->json([
                'message' => 'Only active loans can be deleted.',
            ], 409);
        }

        return response()->json(['message' => 'Loan deleted successfully.']);
    }

    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->validate([
            'id' => ['nullable', 'integer'],
        ]);

        if (isset($filters['id'])) {
            $transaction = BookTransaction::query()
                ->with(['book:id,title,slug', 'borrower:id,name', 'performer:id,name'])
                ->findOrFail($filters['id']);

            abort_if(
                $user->role === 'student' && $transaction->borrower_id !== $user->id,
                404,
            );

            return response()->json([
                'data' => $this->transactionData($transaction),
            ]);
        }

        $transactions = BookTransaction::query()
            ->with(['book:id,title,slug', 'borrower:id,name', 'performer:id,name'])
            ->when(
                ! $this->isAdminUser($user) && $user->role === 'student',
                fn (Builder $query) => $query->where('borrower_id', $user->id),
            )
            ->latest('transaction_at')
            ->latest('id')
            ->paginate(20)
            ->through(fn (BookTransaction $transaction): array => $this->transactionData($transaction));

        return response()->json($transactions);
    }

    public function transactionDetail(int $id): JsonResponse
    {
        $user = request()->user();

        $transaction = BookTransaction::query()
            ->with(['book:id,title,slug', 'borrower:id,name', 'performer:id,name'])
            ->findOrFail($id);

        abort_if(
            $user->role === 'student' && $transaction->borrower_id !== $user->id,
            404,
        );

        return response()->json([
            'data' => $this->transactionData($transaction),
        ]);
    }

    public function createTransaction(Request $request): JsonResponse
    {
        $loanResponse = $this->createLoan($request);
        $loanId = $loanResponse->getData(true)['data']['id'];
        $transaction = BookTransaction::query()
            ->with(['book:id,title,slug', 'borrower:id,name', 'performer:id,name'])
            ->where('book_loan_id', $loanId)
            ->where('transaction_type', 'borrowed')
            ->firstOrFail();

        return response()->json(['data' => $this->transactionData($transaction)], 201);
    }

    public function updateTransaction(Request $request, ?int $id = null): JsonResponse
    {
        $id ??= $request->validate([
            'id' => ['required', 'integer'],
        ])['id'];
        $transaction = BookTransaction::query()->findOrFail($id);

        abort_unless($transaction->transaction_type === 'borrowed', 409, 'Only an active borrowing transaction can be updated.');

        $data = $request->validate([
            'due_at' => ['sometimes', 'required', 'date', 'after_or_equal:today'],
            'borrower_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
        ]);

        DB::transaction(function () use ($transaction, $data, $request): void {
            $loan = BookLoan::query()->lockForUpdate()->findOrFail($transaction->book_loan_id);

            if ($loan->returned_at !== null || $loan->voided_at !== null) {
                throw ValidationException::withMessages(['transaction' => 'Cannot update a completed or voided transaction loan.']);
            }

            $loanUpdateData = [];
            if (isset($data['due_at'])) {
                $loanUpdateData['due_at'] = Carbon::parse($data['due_at'])->endOfDay();
            }
            if (isset($data['borrower_id'])) {
                $loanUpdateData['borrower_id'] = $data['borrower_id'];
                $transaction->borrower_id = $data['borrower_id'];
            }

            if (! empty($loanUpdateData)) {
                $loan->update($loanUpdateData);
            }

            $transaction->save();

            LibraryAuditLog::record('loan.updated', $loan, [
                'transaction_id' => $transaction->id,
                'source' => 'api',
            ], $request->user());
        });

        $updatedTransaction = $transaction->fresh(['book:id,title,slug', 'borrower:id,name', 'performer:id,name']);

        return response()->json([
            'message' => 'Transaction updated successfully.',
            'data' => $this->transactionData($updatedTransaction),
        ]);
    }

    public function returnTransaction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
        ]);
        $transaction = BookTransaction::query()->findOrFail($data['id']);

        abort_unless($transaction->transaction_type === 'borrowed', 409, 'Only a borrowing transaction can be returned.');

        $this->returnLoan($request, $transaction->loan);
        $returnedTransaction = $transaction->loan->transaction()
            ->with(['book:id,title,slug', 'borrower:id,name', 'performer:id,name'])
            ->firstOrFail();

        return response()->json([
            'message' => 'Book return recorded.',
            'data' => $this->transactionData($returnedTransaction),
        ]);
    }

    public function voidTransaction(Request $request, ?int $id = null): JsonResponse
    {
        $transactionId = $id ?? $request->input('id');

        if (! $transactionId) {
            return response()->json([
                'message' => 'The id field is required.',
                'errors' => ['id' => ['The id field is required.']],
            ], 422);
        }

        $transaction = BookTransaction::query()->findOrFail($transactionId);

        abort_unless($transaction->transaction_type === 'borrowed', 409, 'Only an active borrowing transaction can be voided.');

        DB::transaction(function () use ($request, $transaction): void {
            $borrowTransaction = BookTransaction::query()
                ->lockForUpdate()
                ->findOrFail($transaction->id);

            if ($borrowTransaction->transaction_type !== 'borrowed') {
                abort(409, 'Only an active borrowing transaction can be voided.');
            }

            $loan = BookLoan::query()->lockForUpdate()->findOrFail($borrowTransaction->book_loan_id);

            if ($loan->returned_at !== null || $loan->voided_at !== null) {
                abort(409, 'Only an active borrowing transaction can be voided.');
            }

            $loan->update([
                'voided_at' => now(),
                'voided_by' => $request->user()->id,
            ]);
            LibraryAuditLog::record('loan.voided', $loan, [
                'borrow_transaction_id' => $borrowTransaction->id,
                'source' => 'internal_api',
            ], $request->user());

            $borrowTransaction->delete();
        });

        return response()->json([
            'message' => 'Borrowing transaction voided and removed.',
        ]);
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

            if ($book->condition_status !== 'available' || $book->loans()->whereNull('returned_at')->whereNull('voided_at')->count() >= $book->total_copies) {
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
            BookTransaction::create([
                'book_loan_id' => $loan->id,
                'book_id' => $book->id,
                'borrower_id' => $borrowerId,
                'performed_by' => $user->id,
                'transaction_type' => 'borrowed',
                'transaction_at' => $loan->borrowed_at,
                'details' => ['source' => 'api'],
            ]);

            return $loan;
        });

        return response()->json([
            'data' => $this->loanData($loan->load(['book:id,title,slug', 'borrower:id,name', 'issuer:id,name', 'voider:id,name'])),
        ], 201);
    }

    public function returnLoan(Request $request, BookLoan $loan): JsonResponse
    {
        DB::transaction(function () use ($request, $loan): void {
            $loan = BookLoan::query()->lockForUpdate()->findOrFail($loan->id);

            if ($loan->returned_at !== null || $loan->voided_at !== null) {
                throw ValidationException::withMessages(['loan' => 'This book has already been returned.']);
            }

            $loan->update([
                'returned_at' => now(),
                'returned_by' => $request->user()->id,
                'fine_amount' => $loan->overdueFineAmount(now()),
            ]);
            LibraryAuditLog::record('loan.returned', $loan, ['source' => 'api'], $request->user());
            BookTransaction::query()->updateOrCreate(
                ['book_loan_id' => $loan->id],
                [
                    'book_id' => $loan->book_id,
                    'borrower_id' => $loan->borrower_id,
                    'performed_by' => $request->user()->id,
                    'transaction_type' => 'returned',
                    'transaction_at' => $loan->returned_at,
                    'fine_amount' => $loan->fine_amount,
                    'details' => ['source' => 'api'],
                ],
            );
        });

        return response()->json(['message' => 'Book return recorded.']);
    }

    public function report(): JsonResponse
    {
        $availableCopies = (int) Book::query()->where('condition_status', 'available')->sum('total_copies');
        $activeLoans = BookLoan::query()->whereNull('returned_at')->whereNull('voided_at')->count();

        return response()->json([
            'data' => [
                'titles' => Book::query()->count(),
                'total_copies' => (int) Book::query()->sum('total_copies'),
                'available_copies' => max(0, $availableCopies - BookLoan::query()->whereNull('returned_at')->whereNull('voided_at')->whereHas('book', fn ($query) => $query->where('condition_status', 'available'))->count()),
                'active_loans' => $activeLoans,
                'overdue_loans' => BookLoan::query()->whereNull('returned_at')->whereNull('voided_at')->where('due_at', '<', now())->count(),
            ],
        ]);
    }

    private function bookValidationRules(?Book $book = null, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $optional = $partial ? ['sometimes', 'nullable'] : ['nullable'];
        $barcodeRule = Rule::unique('books', 'barcode');

        if ($book !== null) {
            $barcodeRule->ignore($book->id);
        }

        return [
            'category' => [$required, Rule::in(['book', 'study', 'manga'])],
            'genre' => [...$optional, Rule::exists('book_categories', 'slug')],
            'title' => [$required,   'string', 'max:255'],
            'author' => [$required, 'string', 'max:255'],
            'description' => [$required, 'string'],
            'cover_url' => [...$optional, 'url', 'max:2048'],
            'illustrations' => [...$optional, 'array'],
            'illustrations.*' => ['array:url,caption'],
            'illustrations.*.url' => ['required', 'url', 'max:2048'],
            'illustrations.*.caption' => ['nullable', 'string', 'max:255'],
            'content' => [$required, 'string'],
            'source_label' => [...$optional, 'string', 'max:255'],
            'source_url' => [...$optional, 'url', 'max:2048'],
            'total_copies' => [$required, 'integer', 'min:1', 'max:100000'],
            'barcode' => [...$optional, 'string', 'max:64', $barcodeRule],
            'condition_status' => [$required, Rule::in(['available', 'maintenance', 'damaged', 'lost'])],
        ];
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

    /**
     * @return array{id: string, name: string}
     */
    private function categoryData(BookCategory $category): array
    {
        return [
            'id' => $category->slug,
            'name' => $category->name,
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
            'voided_at' => $loan->voided_at?->toIso8601String(),
            'voided_by' => $loan->voider?->name,
            'fine_amount' => (float) $loan->fine_amount,
            'fine_paid_at' => $loan->fine_paid_at?->toIso8601String(),
            'status' => $loan->voided_at ? 'voided' : ($loan->returned_at ? 'returned' : ($loan->isOverdue() ? 'overdue' : 'borrowed')),
        ];
    }

    private function transactionData(BookTransaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'loan_id' => $transaction->book_loan_id,
            'type' => $transaction->transaction_type,
            'book' => [
                'id' => $transaction->book->id,
                'title' => $transaction->book->title,
                'slug' => $transaction->book->slug,
            ],
            'borrower' => [
                'id' => $transaction->borrower->id,
                'name' => $transaction->borrower->name,
            ],
            'performed_by' => $transaction->performer?->name,
            'transaction_at' => $transaction->transaction_at->toIso8601String(),
            'fine_amount' => (float) $transaction->fine_amount,
            'details' => $transaction->details,
        ];
    }
}
