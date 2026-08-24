<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['work_schedule_group_id', 'day_index', 'label', 'is_workday', 'start_time', 'break_start_time', 'break_end_time', 'end_time', 'expected_minutes'])]
class WorkScheduleDay extends Model
{
    protected function casts(): array
    {
        return ['day_index' => 'integer', 'is_workday' => 'boolean', 'expected_minutes' => 'integer'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(WorkScheduleGroup::class, 'work_schedule_group_id');
    }
}
