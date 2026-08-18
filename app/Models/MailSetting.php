<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['from_name', 'from_address', 'host', 'port', 'username', 'password', 'encryption', 'timeout', 'last_tested_at'])]
class MailSetting extends Model
{
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'last_tested_at' => 'datetime',
        ];
    }
}
