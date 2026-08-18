<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'application_id', 'ai_provider_setting_id', 'status', 'score', 'summary',
    'extracted_data', 'matched_requirements', 'missing_requirements', 'model',
    'attempts', 'error_message', 'processed_at', 'last_attempted_at',
    'extraction_status', 'evaluation_status', 'extracted_text',
    'extraction_error', 'evaluation_error', 'recommendation', 'opinion',
    'strengths', 'concerns', 'extraction_attempts', 'evaluation_attempts',
    'extracted_at', 'evaluated_at',
])]
class Curriculum extends Model
{
    protected function casts(): array
    {
        return [
            'extracted_data' => 'array',
            'matched_requirements' => 'array',
            'missing_requirements' => 'array',
            'processed_at' => 'datetime',
            'last_attempted_at' => 'datetime',
            'strengths' => 'array',
            'concerns' => 'array',
            'extracted_at' => 'datetime',
            'evaluated_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function aiProvider(): BelongsTo
    {
        return $this->belongsTo(AiProviderSetting::class, 'ai_provider_setting_id');
    }
}
