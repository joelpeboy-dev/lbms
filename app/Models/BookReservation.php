<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['book_id', 'student_id', 'fulfilled_by', 'loan_id', 'status', 'requested_at', 'fulfilled_at'])]
class BookReservation extends Model
{
    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'fulfilled_at' => 'datetime',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function fulfiller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fulfilled_by');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(BookLoan::class);
    }
}
