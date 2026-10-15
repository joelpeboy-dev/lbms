<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['user_id', 'recorded_by', 'time_in', 'time_out'])]
class Attendance extends Model
{
    public static function clockIn(User $user, ?Carbon $time = null): ?self
    {
        if (! in_array($user->role, ['staff', 'student'], true)) {
            return null;
        }

        if (self::query()->where('user_id', $user->id)->whereNull('time_out')->exists()) {
            return null;
        }

        return self::create([
            'user_id' => $user->id,
            'recorded_by' => $user->id,
            'time_in' => $time ?? now(),
        ]);
    }

    public static function clockOut(User $user, ?Carbon $time = null): ?self
    {
        $attendance = self::query()
            ->where('user_id', $user->id)
            ->whereNull('time_out')
            ->latest('time_in')
            ->first();

        if (! $attendance) {
            return null;
        }

        $attendance->update(['time_out' => $time ?? now()]);

        return $attendance;
    }

    protected function casts(): array
    {
        return [
            'time_in' => 'datetime',
            'time_out' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}