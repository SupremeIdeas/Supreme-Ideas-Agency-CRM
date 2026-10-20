<?php

namespace App\Models\Omni;

use Illuminate\Database\Eloquent\Model;

/** One message in a conversation (MASTER-ONLY). Status only ever moves forward: queued, sent, delivered, read (failed is terminal). */
class OmniMessage extends Model
{
    public const RANK = ['received' => 0, 'queued' => 1, 'sent' => 2, 'delivered' => 3, 'read' => 4];

    protected $table = 'omni_messages';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['media' => 'array', 'template' => 'array', 'sent_at' => 'datetime'];
    }

    public function scopeForWorkspace($q, int $workspaceId)
    {
        return $q->where('workspace_id', $workspaceId);
    }
}
