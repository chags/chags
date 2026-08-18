<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['job_id', 'candidate_id', 'current_stage_id', 'status', 'source', 'cover_letter', 'resume_path', 'resume_original_name', 'resume_mime_type', 'resume_size', 'privacy_consent_at', 'privacy_consent_version', 'privacy_consent_ip', 'applied_at', 'withdrawn_at', 'rejected_at', 'rejection_message', 'rejection_internal_notes', 'hired_at'])]
class Application extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['privacy_consent_at' => 'datetime', 'applied_at' => 'datetime', 'withdrawn_at' => 'datetime', 'rejected_at' => 'datetime', 'hired_at' => 'datetime'];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_id');
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(RecruitmentStage::class, 'current_stage_id');
    }

    public function curriculum(): HasOne
    {
        return $this->hasOne(Curriculum::class);
    }

    public function stageHistories(): HasMany
    {
        return $this->hasMany(ApplicationStageHistory::class);
    }

    public function discAssessment(): HasOne
    {
        return $this->hasOne(DiscAssessment::class);
    }

    public function interviewSchedules(): HasMany
    {
        return $this->hasMany(InterviewSchedule::class);
    }
}
