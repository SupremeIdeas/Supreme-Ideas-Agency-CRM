<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One Claude call's measured usage (MASTER-ONLY). Holds numbers only: never a prompt or a reply. */
class AiUsageLog extends Model
{
    protected $table = 'ai_usage_log';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['cost_usd' => 'decimal:6', 'created_at' => 'datetime'];
}
