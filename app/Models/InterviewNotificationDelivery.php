<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['interview_schedule_id', 'recipient', 'channel', 'type', 'status', 'provider_id', 'attempts', 'error', 'sent_at'])]
class InterviewNotificationDelivery extends Model
{
    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(InterviewSchedule::class, 'interview_schedule_id');
    }
}
