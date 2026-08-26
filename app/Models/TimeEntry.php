<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'recorded_at', 'work_date', 'type', 'source', 'status', 'reason', 'notes', 'ip_address', 'created_by', 'time_adjustment_request_id', 'reviewed_by', 'reviewed_at', 'review_notes'])]
class TimeEntry extends Model
{
    /** @use HasFactory<TimeEntryFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (TimeEntry $entry): void {
            if (! $entry->work_date && $entry->recorded_at) {
                $entry->work_date = CarbonImmutable::parse($entry->recorded_at)
                    ->setTimezone(config('app.business_timezone'))
                    ->toDateString();
            }
        });
    }

    protected function casts(): array
    {
        return ['recorded_at' => 'immutable_datetime', 'work_date' => 'immutable_date', 'reviewed_at' => 'immutable_datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function adjustmentRequest(): BelongsTo
    {
        return $this->belongsTo(TimeAdjustmentRequest::class, 'time_adjustment_request_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
