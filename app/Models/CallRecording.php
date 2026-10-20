<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One recorded call on a workspace number (MASTER-ONLY, Business Suite Prompt 15). The audio lives on the PRIVATE disk and is only ever played
 * through RecordingAudioController (workspace owner/admin or the agent on the call, every play audited). Provider and cost never leave the server.
 */
class CallRecording extends Model
{
    public const RECEIVED = 'received';

    public const STORED = 'stored';

    public const UNFUNDED = 'unfunded';

    public const FAILED = 'failed';

    public const PURGED = 'purged';

    protected $fillable = [
        'wallet_group_id', 'user_id', 'virtual_number_id', 'call_flow_id', 'call_reference', 'provider', 'source', 'provider_recording_sid',
        'from_number', 'to_number', 'duration_seconds', 'storage_path', 'status', 'transcript_status', 'intelligence_status',
        'consent', 'billing', 'expires_at', 'purged_at',
    ];

    protected $hidden = ['provider', 'provider_recording_sid', 'billing', 'storage_path'];

    protected function casts(): array
    {
        return [
            'consent' => 'array',
            'billing' => 'array',
            'expires_at' => 'datetime',
            'purged_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    public function walletGroup(): BelongsTo
    {
        return $this->belongsTo(WalletGroup::class);
    }

    public function virtualNumber(): BelongsTo
    {
        return $this->belongsTo(VirtualNumber::class);
    }

    public function transcript(): HasOne
    {
        return $this->hasOne(CallTranscript::class);
    }

    public function intelligence(): HasOne
    {
        return $this->hasOne(CallIntelligenceSummary::class);
    }

    public function isPlayable(): bool
    {
        return $this->status === self::STORED && $this->storage_path !== null && $this->purged_at === null;
    }
}
