<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** One logged realtime event (N2N, MASTER-ONLY). Append-only; pruned by TTL. */
class RealtimeEvent extends Model
{
    public $timestamps = false;

    protected $table = 'n2n_realtime_events';

    protected $fillable = ['user_id', 'type', 'payload', 'created_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'created_at' => 'datetime'];
    }

    /** The one envelope both drivers deliver. */
    public function envelope(): array
    {
        return ['id' => $this->id, 'type' => $this->type, 'payload' => (object) ($this->payload ?? []), 'at' => $this->created_at?->toIso8601String()];
    }
}
