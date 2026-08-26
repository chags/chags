<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $type
 * @property string $status
 * @property string $title
 * @property string|null $body
 * @property array<string, mixed>|null $sensitive_payload
 * @property string $audience
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $published_at
 * @property Carbon|null $expires_at
 */
class InAppMessage extends Model
{
    use HasUlids, SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['sensitive_payload'];

    protected function casts(): array
    {
        return [
            'sensitive_payload' => 'encrypted:array',
            'scheduled_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<InAppMessageRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(InAppMessageRecipient::class, 'message_id');
    }
}
