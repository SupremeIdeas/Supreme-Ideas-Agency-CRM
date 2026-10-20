<?php

namespace App\Models\JourneyEngine;

use Illuminate\Database\Eloquent\Model;

/** What an admin changed on one shipped Journey rule (J3). */
class JourneyRuleOverride extends Model
{
    protected $fillable = ['rule_key', 'enabled', 'priority', 'intents', 'copy', 'updated_by'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'intents' => 'array', 'copy' => 'array'];
    }
}
