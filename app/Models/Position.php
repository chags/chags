<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'department_id', 'title', 'level', 'code', 'description', 'active'])]
class Position extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function getDisplayNameAttribute(): string
    {
        $levels = ['intern' => 'Estagiário', 'junior' => 'Júnior', 'mid' => 'Pleno', 'senior' => 'Sênior', 'specialist' => 'Especialista', 'lead' => 'Líder', 'manager' => 'Gerente'];

        return trim($this->title.' '.($levels[$this->level] ?? ''));
    }
}
