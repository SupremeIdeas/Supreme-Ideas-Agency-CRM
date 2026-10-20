<?php

namespace App\Models\Omni;

use Illuminate\Database\Eloquent\Model;

/** Naara Respond automation row (MASTER-ONLY): always read and written through its workspace. */
class OmniFlowRun extends Model
{
    protected $table = 'omni_flow_runs';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['trace' => 'array'];
    }

    public function scopeForWorkspace($q, int $workspaceId)
    {
        return $q->where('workspace_id', $workspaceId);
    }
}
