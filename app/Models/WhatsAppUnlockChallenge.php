<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int|null $user_id
 * @property string $device_installation_id
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 */
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
