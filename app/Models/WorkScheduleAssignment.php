<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['work_schedule_group_id', 'user_id', 'valid_from', 'valid_until', 'active', 'assigned_by'])]
class WorkScheduleAssignment extends Model
{
    protected function casts(): array
    {
        return ['valid_from' => 'date', 'valid_until' => 'date', 'active' => 'boolean'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(WorkScheduleGroup::class, 'work_schedule_group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
