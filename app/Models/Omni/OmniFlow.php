<?php

namespace App\Models\Omni;

use Illuminate\Database\Eloquent\Model;

/** Naara Respond automation row (MASTER-ONLY): always read and written through its workspace. */
class OmniFlow extends Model
{
    protected $table = 'omni_flows';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['steps' => 'array'];
    }

    public function scopeForWorkspace($q, int $workspaceId)
    {
        return $q->where('workspace_id', $workspaceId);
    }
}
