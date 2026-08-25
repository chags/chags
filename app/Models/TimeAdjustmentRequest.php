<?php

namespace App\Models;

use Database\Factories\TimeAdjustmentRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'work_date', 'requested_entries', 'reason', 'status', 'reviewed_by', 'reviewed_at', 'review_notes'])]
class TimeAdjustmentRequest extends Model
{
    /** @use HasFactory<TimeAdjustmentRequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'requested_entries' => 'array',
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function hourBankTransactions(): HasMany
    {
        return $this->hasMany(HourBankTransaction::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }
}
