<?php

namespace App\Models\JourneyEngine;

use Illuminate\Database\Eloquent\Model;

class JourneyProfile extends Model
{
    protected $fillable = ['user_id', 'stages', 'signals_cache', 'country', 'locale', 'device_class'];

    protected function casts(): array
    {
        return ['stages' => 'array', 'signals_cache' => 'array'];
    }
}
