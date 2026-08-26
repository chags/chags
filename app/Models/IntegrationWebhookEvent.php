<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationWebhookEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['received_at' => 'immutable_datetime', 'processed_at' => 'immutable_datetime'];
    }
}
