<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['actor_id', 'impersonator_id', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip_address'])]
class HrAuditEvent extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];
    }
}
