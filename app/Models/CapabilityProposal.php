<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tier 5 #14 (Provider Capability Radar, master-only). A Claude-generated,
 * evidence-linked proposal for a possible new feature based on a real
 * detected provider-capability change. Never auto-implemented — a human
 * always accepts/defers/dismisses.
 */
class CapabilityProposal extends Model
{
    public const NEW = 'new';

    public const REVIEWING = 'reviewing';

    public const ACCEPTED = 'accepted';

    public const DEFERRED = 'deferred';

    public const DISMISSED = 'dismissed';

    protected $fillable = [
        'provider',
        'source_snapshot_id',
        'raw_diff',
        'summary',
        'capability_added',
        'relevance_to_naara',
        'proposed_feature',
        'estimated_effort',
        'confidence',
        'requires_human_verification_because',
        'status',
        'reviewed_by',
        'admin_note',
        'dismissed_reason',
        'accepted_at',
        'deferred_until',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'deferred_until' => 'datetime',
        ];
    }

    public function sourceSnapshot(): BelongsTo
    {
        return $this->belongsTo(ProviderCapabilitySnapshot::class, 'source_snapshot_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** A deferred proposal that's past its deferral window re-surfaces as if new. */
    public function isDue(): bool
    {
        return $this->status !== self::DEFERRED
            || $this->deferred_until === null
            || $this->deferred_until->isPast();
    }
}
