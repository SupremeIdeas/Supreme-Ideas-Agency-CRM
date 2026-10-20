<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tier 5 #14 (Provider Capability Radar, master-only). One fetched snapshot
 * of a provider's changelog/spec/response-shape at a point in time.
 */
class ProviderCapabilitySnapshot extends Model
{
    protected $fillable = [
        'provider',
        'source_type',
        'content_hash',
        'raw_content',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'fetched_at' => 'datetime',
        ];
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(CapabilityProposal::class, 'source_snapshot_id');
    }
}
