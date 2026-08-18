<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['disc_assessment_id', 'disc_question_id', 'disc_option_id', 'answered_at'])]
class DiscAnswer extends Model
{
    protected function casts(): array
    {
        return ['answered_at' => 'datetime'];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(DiscAssessment::class, 'disc_assessment_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(DiscOption::class, 'disc_option_id');
    }
}
