<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'position', 'prompt', 'active', 'version'])]
class DiscQuestion extends Model
{
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function options(): HasMany
    {
        return $this->hasMany(DiscOption::class)->orderBy('display_order');
    }
}
