<?php

namespace App\Models\Omni;

use Illuminate\Database\Eloquent\Model;

/** Naara Respond automation row (MASTER-ONLY): always read and written through its workspace. */
class OmniBroadcastRecipient extends Model
{
    protected $table = 'omni_broadcast_recipients';

    protected $guarded = [];

    protected function casts(): array
    {
        return [];
    }

    public function scopeForWorkspace($q, int $workspaceId)
    {
        return $q->where('workspace_id', $workspaceId);
    }
}
