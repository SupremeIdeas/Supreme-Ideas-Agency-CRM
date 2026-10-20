<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerifyRun extends Model
{
    public $timestamps = false;

    protected $fillable = ['area', 'check', 'status', 'detail', 'evidence', 'trigger', 'ran_at'];

    protected function casts(): array
    {
        return ['evidence' => 'array', 'ran_at' => 'datetime'];
    }
}
