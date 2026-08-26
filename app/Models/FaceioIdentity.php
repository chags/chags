<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FaceioIdentity extends Model
{
    use HasUlids, SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['facial_id_encrypted', 'facial_id_hash'];

    protected function casts(): array
    {
        return ['facial_id_encrypted' => 'encrypted', 'enrolled_at' => 'immutable_datetime'];
    }
}
