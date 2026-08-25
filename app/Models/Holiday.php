<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'name', 'holiday_date', 'scope', 'state', 'city', 'starts_at', 'ends_at', 'active', 'created_by'])]
class Holiday extends Model
{
    protected function casts(): array
    {
        return ['holiday_date' => 'date', 'active' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
