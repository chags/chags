<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class InAppMessageAuditEvent extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $guarded = [];
}
