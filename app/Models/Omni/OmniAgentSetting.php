<?php

namespace App\Models\Omni;

use Illuminate\Database\Eloquent\Model;

/** Naara Respond automation row (MASTER-ONLY): always read and written through its workspace. */
class OmniAgentSetting extends Model
{
    protected $table = 'omni_agent_settings';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['handoff_words' => 'array'];
    }

    public function scopeForWorkspace($q, int $workspaceId)
    {
        return $q->where('workspace_id', $workspaceId);
    }
}
