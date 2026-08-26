<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $message_id
 * @property int $user_id
 * @property Carbon|null $read_at
 * @property Carbon|null $dismissed_at
 * @property-read InAppMessage $message
 */
class InAppMessageRecipient extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'read_at' => 'immutable_datetime',
            'dismissed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<InAppMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(InAppMessage::class, 'message_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
