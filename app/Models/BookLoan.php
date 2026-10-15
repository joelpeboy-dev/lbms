<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['book_id', 'borrower_id', 'issued_by', 'returned_by', 'borrowed_at', 'due_at', 'returned_at', 'fine_amount', 'fine_paid_at', 'due_reminded_at', 'overdue_reminded_at'])]
class BookLoan extends Model
{
    protected function casts(): array
    {
        return [
            'borrowed_at' => 'datetime',
            'due_at' => 'datetime',
            'returned_at' => 'datetime',
            'fine_amount' => 'decimal:2',
            'fine_paid_at' => 'datetime',
            'due_reminded_at' => 'datetime',
            'overdue_reminded_at' => 'datetime',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function isOverdue(): bool
    {
        return $this->returned_at === null && $this->due_at->lessThan(now());
    }

    public function overdueDays(?CarbonInterface $reference = null): int
    {
        $reference = $reference ?? ($this->returned_at ?? now());

        if ($this->due_at === null || $reference->lessThanOrEqualTo($this->due_at)) {
            return 0;
        }

        return (int) $this->due_at->diffInDays($reference, false);
    }

    public function overdueFineAmount(?CarbonInterface $reference = null): float
    {
        return round($this->overdueDays($reference) * (float) config('library.fine_per_day', 15.00), 2);
    }
}
