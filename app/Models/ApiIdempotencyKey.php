<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiIdempotencyKey extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['response_body' => 'array', 'expires_at' => 'immutable_datetime'];
    }
}
