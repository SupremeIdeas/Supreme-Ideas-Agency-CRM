<?php

namespace App\Models\Omni;

use Illuminate\Database\Eloquent\Model;

/** Naara Respond automation row (MASTER-ONLY): always read and written through its workspace. */
class OmniWebhook extends Model
{
    protected $table = 'omni_webhooks';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_at' => 'datetime', 'events' => 'array'];
    }

    public function scopeForWorkspace($q, int $workspaceId)
    {
        return $q->where('workspace_id', $workspaceId);
    }
}
