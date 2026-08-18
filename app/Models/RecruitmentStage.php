<?php

namespace App\Models;

use Database\Factories\RecruitmentStageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'name', 'position', 'type', 'active', 'candidate_visible', 'public_name', 'public_description', 'candidate_action'])]
class RecruitmentStage extends Model
{
    /** @use HasFactory<RecruitmentStageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['active' => 'boolean', 'candidate_visible' => 'boolean'];
    }

    public function interviewSchedules(): HasMany
    {
        return $this->hasMany(InterviewSchedule::class, 'stage_id');
    }
}
