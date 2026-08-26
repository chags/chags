<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiDevice extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $hidden = ['public_key', 'key_fingerprint', 'signing_digest', 'last_ip'];

    protected function casts(): array
    {
        return [
            'biometric_available' => 'boolean',
            'security_patch' => 'date',
            'face_verified_at' => 'immutable_datetime',
            'first_seen_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
