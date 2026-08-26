<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppUnlockChallenge extends Model
{
    use HasUlids;

    protected $table = 'whatsapp_unlock_challenges';

    protected $guarded = [];

    protected $hidden = ['code_hash', 'phone_hash', 'request_ip_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'consumed_at' => 'immutable_datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
