<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'professional_summary', 'linkedin_url', 'portfolio_url', 'city', 'state', 'availability', 'talent_pool_consent_at', 'talent_pool_expires_at', 'anonymized_at'])]
class CandidateProfile extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['talent_pool_consent_at' => 'datetime', 'talent_pool_expires_at' => 'datetime', 'anonymized_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
