<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['disc_question_id', 'code', 'text', 'dimension', 'weight', 'display_order'])]
class DiscOption extends Model
{
    protected $hidden = ['dimension', 'weight'];

    public function question(): BelongsTo
    {
        return $this->belongsTo(DiscQuestion::class, 'disc_question_id');
    }
}
