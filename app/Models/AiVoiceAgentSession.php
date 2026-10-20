<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One call handled by the AI voice agent (MASTER-ONLY, Business Suite Prompt 17). The transcript and the tools the agent used are appended during the
 * call; the funded-block money fields mirror VoiceCall (hold, settle, refund) so billing is the same pattern a human-dialled minute already uses.
 * Supplier masking: provider rate is never stored here and `retail_per_min` is the only price a workspace ever sees.
 */
class AiVoiceAgentSession extends Model
{
    public const IN_PROGRESS = 'in_progress';

    public const HANDED_OFF = 'handed_off';

    public const ENDED = 'ended';

    protected $fillable = [
        'wallet_group_id', 'virtual_number_id', 'call_flow_id', 'call_flow_node_id', 'call_reference', 'from_number', 'status', 'outcome',
        'handoff_reason', 'summary', 'transcript', 'tool_calls', 'callback', 'started_at', 'ended_at', 'duration_seconds',
        'retail_per_min', 'minutes_authorized', 'amount_held', 'hold_reference', 'minutes_billed', 'amount_charged', 'refunded', 'settled_at', 'recording_id',
    ];

    protected function casts(): array
    {
        return [
            'transcript' => 'array', 'tool_calls' => 'array', 'callback' => 'array',
            'started_at' => 'datetime', 'ended_at' => 'datetime', 'settled_at' => 'datetime',
            'retail_per_min' => 'decimal:4', 'amount_held' => 'decimal:4', 'amount_charged' => 'decimal:4', 'refunded' => 'decimal:4',
            'minutes_authorized' => 'integer', 'minutes_billed' => 'integer', 'duration_seconds' => 'integer',
        ];
    }

    public function walletGroup(): BelongsTo
    {
        return $this->belongsTo(WalletGroup::class);
    }

    public function callFlow(): BelongsTo
    {
        return $this->belongsTo(CallFlow::class);
    }

    /** The funded ceiling in seconds: the call is wound up before the money runs out. */
    public function fundedSeconds(): int
    {
        return max(0, (int) $this->minutes_authorized) * 60;
    }
}
