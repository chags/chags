<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'provider',
    'enabled',
    'is_default',
    'base_url',
    'model',
    'api_key',
    'organization',
    'timeout',
    'max_output_tokens',
    'temperature',
    'last_tested_at',
    'last_test_succeeded',
])]
class AiProviderSetting extends Model
{
    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'enabled' => 'boolean',
            'is_default' => 'boolean',
            'temperature' => 'decimal:2',
            'last_tested_at' => 'datetime',
            'last_test_succeeded' => 'boolean',
        ];
    }
}
