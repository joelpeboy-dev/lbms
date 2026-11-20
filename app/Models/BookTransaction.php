<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['book_loan_id', 'book_id', 'borrower_id', 'performed_by', 'transaction_type', 'transaction_at', 'fine_amount', 'details'])]
class BookTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'transaction_at' => 'datetime',
            'fine_amount' => 'decimal:2',
            'details' => 'array',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(BookLoan::class, 'book_loan_id');
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
