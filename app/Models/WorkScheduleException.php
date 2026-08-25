<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'work_date', 'type', 'start_time', 'break_start_time', 'break_end_time',
    'end_time', 'expected_minutes', 'reason', 'status', 'created_by', 'cancelled_by',
    'cancelled_at', 'cancellation_reason',
])]
class WorkScheduleException extends Model
{
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'expected_minutes' => 'integer',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hourBankTransactions(): HasMany
    {
        return $this->hasMany(HourBankTransaction::class);
    }
}
