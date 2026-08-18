<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['application_id', 'candidate_id', 'status', 'questionnaire_version', 'current_position', 'd_score', 'i_score', 's_score', 'c_score', 'dominant_profile', 'secondary_profile', 'result_snapshot', 'consent_at', 'ip_address', 'started_at', 'completed_at'])]
class DiscAssessment extends Model
{
    protected function casts(): array
    {
        return ['result_snapshot' => 'array', 'consent_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidate_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(DiscAnswer::class);
    }
}
