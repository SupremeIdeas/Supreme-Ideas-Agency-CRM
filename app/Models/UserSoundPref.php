<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSoundPref extends Model
{
    protected $fillable = ['user_id', 'tones', 'sound_enabled', 'vibrate_enabled'];

    protected function casts(): array
    {
        return ['tones' => 'array', 'sound_enabled' => 'boolean', 'vibrate_enabled' => 'boolean'];
    }
}
