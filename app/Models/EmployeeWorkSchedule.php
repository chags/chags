<?php

namespace App\Models;

use Database\Factories\EmployeeWorkScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'name', 'weekdays', 'start_time', 'break_start_time', 'break_end_time', 'end_time', 'daily_minutes', 'weekly_minutes', 'valid_from', 'valid_until', 'active'])]
class EmployeeWorkSchedule extends Model
{
    /** @use HasFactory<EmployeeWorkScheduleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
            'daily_minutes' => 'integer',
            'weekly_minutes' => 'integer',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
