<?php

namespace App\Models\Omni;

use Illuminate\Database\Eloquent\Model;

/** Naara Respond automation row (MASTER-ONLY): always read and written through its workspace. */
class OmniKbSource extends Model
{
    protected $table = 'omni_kb_sources';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['fetched_at' => 'datetime', 'checked_at' => 'datetime', 'facts' => 'array', 'notes' => 'array'];
    }

    public function scopeForWorkspace($q, int $workspaceId)
    {
        return $q->where('workspace_id', $workspaceId);
    }
}
