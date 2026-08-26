<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaceioSession extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $hidden = ['opaque_payload_hash', 'facial_id_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
