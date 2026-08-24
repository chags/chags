<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'schedule_type', 'weekly_minutes', 'entry_tolerance_minutes', 'daily_tolerance_minutes', 'operational_window_minutes', 'daily_overtime_limit_minutes', 'requires_overtime_approval', 'cycle_start_date', 'active', 'created_by'])]
class WorkScheduleGroup extends Model
{
    protected function casts(): array
    {
        return ['weekly_minutes' => 'integer', 'entry_tolerance_minutes' => 'integer', 'daily_tolerance_minutes' => 'integer', 'operational_window_minutes' => 'integer', 'daily_overtime_limit_minutes' => 'integer', 'requires_overtime_approval' => 'boolean', 'cycle_start_date' => 'date', 'active' => 'boolean'];
    }

    public function days(): HasMany
    {
        return $this->hasMany(WorkScheduleDay::class)->orderBy('day_index');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WorkScheduleAssignment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
