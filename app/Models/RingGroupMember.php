<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One seat inside a RingGroup (Business Suite Prompt 14 §1.4 / §4.1). */
class RingGroupMember extends Model
{
    protected $fillable = ['ring_group_id', 'wallet_group_member_id', 'priority', 'destination_number', 'ring_in_browser'];

    protected function casts(): array
    {
        return ['ring_in_browser' => 'boolean', 'priority' => 'integer'];
    }

    public function ringGroup(): BelongsTo
    {
        return $this->belongsTo(RingGroup::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(WalletGroupMember::class, 'wallet_group_member_id');
    }

    /**
     * §4.1 — where this seat's phone rings: an explicit override first,
     * otherwise the seat's own active personal Naara Line. Null when neither
     * exists (the seat is then only reachable in-browser, if enabled).
     */
    public function resolveNumber(): ?string
    {
        if ($this->destination_number) {
            return $this->destination_number;
        }

        $userId = $this->member?->user_id;
        if (! $userId) {
            return null;
        }

        return VirtualNumber::query()
            ->where('user_id', $userId)
            ->whereNull('wallet_group_id')
            ->where('provider', 'twilio')
            ->where('status', 'active')
            ->orderBy('id')
            ->value('phone_number');
    }
}
