<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['application_id', 'stage_id', 'organizer_id', 'format', 'provider', 'status', 'title', 'starts_at', 'ends_at', 'timezone', 'location', 'meeting_url', 'provider_event_id', 'provider_event_url', 'public_instructions', 'internal_notes', 'candidate_response', 'candidate_responded_at', 'reschedule_reason', 'created_by', 'updated_by', 'cancelled_by', 'cancelled_at', 'cancellation_reason'])]
class InterviewSchedule extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'candidate_responded_at' => 'datetime', 'cancelled_at' => 'datetime', 'meeting_url' => 'encrypted'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(RecruitmentStage::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(InterviewParticipant::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(InterviewNotificationDelivery::class);
    }
}
