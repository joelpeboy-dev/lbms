<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'is_admin', 'profile_photo_path', 'student_id', 'first_name', 'last_name', 'course', 'year_level', 'google_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public function isAdmin(): bool
    {
        return (bool) ($this->is_admin ?? false) || $this->role === 'admin';
    }

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function bookLoans(): HasMany
    {
        return $this->hasMany(BookLoan::class, 'borrower_id');
    }

    public function bookReservations(): HasMany
    {
        return $this->hasMany(BookReservation::class, 'student_id');
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function performedBookTransactions(): HasMany
    {
        return $this->hasMany(BookTransaction::class, 'performed_by');
    }

    public function bookTransactionsAsBorrower(): HasMany
    {
        return $this->hasMany(BookTransaction::class, 'borrower_id');
    }

    public function recordedAttendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'recorded_by');
    }

    public function bookActivities(): HasMany
    {
        return $this->hasMany(BookActivity::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(LibraryAuditLog::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'year_level' => 'integer',
        ];
    }
}
