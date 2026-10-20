<?php

namespace App\Models\Omni;

use Illuminate\Database\Eloquent\Model;

/** A private note on a conversation, visible to the team only and never sent to the customer (MASTER-ONLY). */
class OmniNote extends Model
{
    protected $table = 'omni_notes';

    protected $guarded = [];

    public function scopeForWorkspace($q, int $workspaceId)
    {
        return $q->where('workspace_id', $workspaceId);
    }
}
