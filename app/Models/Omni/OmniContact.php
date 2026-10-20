<?php

namespace App\Models\Omni;

use Illuminate\Database\Eloquent\Model;

/** A person a workspace talks to (MASTER-ONLY). One contact can hold many channel identities. */
class OmniContact extends Model
{
    protected $table = 'omni_contacts';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['tags' => 'array', 'last_seen_at' => 'datetime', 'marketing_opt_in' => 'boolean', 'opted_out_at' => 'datetime'];
    }

    public function scopeForWorkspace($q, int $workspaceId)
    {
        return $q->where('workspace_id', $workspaceId);
    }
}
