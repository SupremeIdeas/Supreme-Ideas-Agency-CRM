<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutProbeRun extends Model
{
    public $timestamps = false;

    protected $fillable = ['provider', 'tier', 'status', 'latency_ms', 'summary', 'checks', 'mismatches', 'ran_by', 'created_at'];

    protected function casts(): array
    {
        return ['checks' => 'array', 'mismatches' => 'array', 'created_at' => 'datetime'];
    }
}
