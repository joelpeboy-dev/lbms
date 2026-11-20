<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    protected $fillable = [
        'slug',
        'category',
        'genre',
        'title',
        'author',
        'description',
        'cover_url',
        'illustrations',
        'content',
        'source_label',
        'source_url',
        'total_copies',
        'barcode',
        'condition_status',
    ];

    protected function casts(): array
    {
        return [
            'illustrations' => 'array',
            'total_copies' => 'integer',
        ];
    }

    public function loans(): HasMany
    {
        return $this->hasMany(BookLoan::class);
    }

    public function genreCategory(): BelongsTo
    {
        return $this->belongsTo(BookCategory::class, 'genre', 'slug');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(BookReservation::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(BookActivity::class);
    }

    public function availableCopies(): int
    {
        if ($this->condition_status !== 'available') {
            return 0;
        }

        return max(0, $this->total_copies - $this->loans()->whereNull('returned_at')->whereNull('voided_at')->count());
    }
}
