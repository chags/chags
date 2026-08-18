<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'department_id', 'position_id', 'hiring_manager_id', 'created_by', 'title', 'slug', 'description', 'requirements', 'benefits', 'image', 'workplace_type', 'employment_type', 'city', 'state', 'status', 'published_at', 'closes_at'])]
class Job extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'recruitment_jobs';

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'closes_at' => 'datetime'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function hiringManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hiring_manager_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? '/storage/'.ltrim($this->image, '/') : null;
    }
}
