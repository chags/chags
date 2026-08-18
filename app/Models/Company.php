<?php

namespace App\Models;

use App\Enums\CompanyUnitType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'headquarters_id', 'unit_type', 'unit_number', 'unit_name', 'name', 'trade_name',
    'cnpj', 'logo', 'address', 'address_number', 'address_complement', 'district',
    'city', 'state', 'postal_code', 'active',
])]
class Company extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'unit_type' => CompanyUnitType::class,
            'active' => 'boolean',
        ];
    }

    public function headquarters(): BelongsTo
    {
        return $this->belongsTo(self::class, 'headquarters_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(self::class, 'headquarters_id');
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? '/storage/'.ltrim($this->logo, '/') : null;
    }
}
