<?php

namespace App\Models;

use Database\Factories\VacationPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'accrual_start', 'accrual_end', 'entitled_days', 'used_days', 'scheduled_start', 'scheduled_end', 'status'])]
class VacationPeriod extends Model
{
    /** @use HasFactory<VacationPeriodFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'accrual_start' => 'date',
            'accrual_end' => 'date',
            'entitled_days' => 'integer',
            'used_days' => 'integer',
            'scheduled_start' => 'date',
            'scheduled_end' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getAvailableDaysAttribute(): int
    {
        return max(0, $this->entitled_days - $this->used_days);
    }
}
