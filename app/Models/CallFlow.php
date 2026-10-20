<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/**
 * Business Suite Prompt 14 §1.1 — the IVR/queue tree answering ONE
 * org-owned Twilio number. Exists only for a number whose
 * `wallet_group_id` matches this flow's own (enforced in `saving`, not
 * just by FK) and which Twilio actually serves — Telnyx lines are refused
 * here rather than silently mis-routed.
 */
class CallFlow extends Model
{
    public const ACTIVE = 'active';

    public const PAUSED = 'paused';

    protected $fillable = ['wallet_group_id', 'virtual_number_id', 'root_node_id', 'status'];

    protected static function booted(): void
    {
        static::saving(function (CallFlow $flow) {
            $number = VirtualNumber::find($flow->virtual_number_id);

            if (! $number || (int) $number->wallet_group_id !== (int) $flow->wallet_group_id) {
                throw ValidationException::withMessages([
                    'virtual_number_id' => 'That number does not belong to this workspace.',
                ]);
            }

            if (! $number->supportsCallFlows()) {
                throw ValidationException::withMessages([
                    'virtual_number_id' => 'Call flows are only available on lines that support business voice.',
                ]);
            }

            if ($flow->root_node_id !== null
                && ! CallFlowNode::where('id', $flow->root_node_id)->where('call_flow_id', $flow->id)->exists()) {
                throw ValidationException::withMessages([
                    'root_node_id' => 'The starting step must belong to this call flow.',
                ]);
            }
        });
    }

    public function walletGroup(): BelongsTo
    {
        return $this->belongsTo(WalletGroup::class);
    }

    public function virtualNumber(): BelongsTo
    {
        return $this->belongsTo(VirtualNumber::class);
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(CallFlowNode::class);
    }

    public function rootNode(): ?CallFlowNode
    {
        return $this->root_node_id ? $this->nodes()->find($this->root_node_id) : null;
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }
}
