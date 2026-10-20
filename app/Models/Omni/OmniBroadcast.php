<?php

namespace App\Models\Omni;

use Illuminate\Database\Eloquent\Model;

/** Naara Respond automation row (MASTER-ONLY): always read and written through its workspace. */
class OmniBroadcast extends Model
{
    protected $table = 'omni_broadcasts';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'segment' => 'array', 'stats' => 'array'];
    }

    public function scopeForWorkspace($q, int $workspaceId)
    {
        return $q->where('workspace_id', $workspaceId);
    }
}
