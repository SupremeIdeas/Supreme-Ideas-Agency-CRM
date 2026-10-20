<?php

namespace App\Models\Omni;

use Illuminate\Database\Eloquent\Model;

/** Naara Respond automation row (MASTER-ONLY): always read and written through its workspace. */
class OmniKbChunk extends Model
{
    protected $table = 'omni_kb_chunks';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [];
    }

    public function scopeForWorkspace($q, int $workspaceId)
    {
        return $q->where('workspace_id', $workspaceId);
    }
}
