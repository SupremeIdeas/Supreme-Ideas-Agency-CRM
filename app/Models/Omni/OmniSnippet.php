<?php

namespace App\Models\Omni;

use Illuminate\Database\Eloquent\Model;

/** A saved reply the team inserts into the composer (MASTER-ONLY). */
class OmniSnippet extends Model
{
    protected $table = 'omni_snippets';

    protected $guarded = [];

    public function scopeForWorkspace($q, int $workspaceId)
    {
        return $q->where('workspace_id', $workspaceId);
    }
}
