<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Business Suite Prompt 14 §1.3 — a named set of workspace seats that a
 * `queue` call-flow node rings. `simultaneous` rings everyone in one
 * `<Dial>`; `sequential` walks `priority` order one leg at a time.
 */
class RingGroup extends Model
{
    public const SIMULTANEOUS = 'simultaneous';

    public const SEQUENTIAL = 'sequential';

    protected $fillable = ['wallet_group_id', 'name', 'strategy'];

    public function walletGroup(): BelongsTo
    {
        return $this->belongsTo(WalletGroup::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(RingGroupMember::class)->orderBy('priority')->orderBy('id');
    }

    /**
     * The reachable ring targets, in dial order. Each entry is
     * `['number' => ?string, 'client' => ?string]`. A seat with neither a
     * usable number nor browser ringing is skipped — never dialled blind.
     *
     * @return array<int,array{number:?string,client:?string}>
     */
    public function dialTargets(): array
    {
        $targets = [];

        foreach ($this->members()->with('member')->get() as $row) {
            $seat = $row->member;
            if (! $seat || $seat->status === WalletGroupMember::STATUS_REMOVED || (int) $seat->wallet_group_id !== (int) $this->wallet_group_id) {
                continue;
            }

            $number = $row->resolveNumber();
            $client = ($row->ring_in_browser && $seat->user_id)
                ? \App\Http\Controllers\VoiceTokenController::identityFor($seat->user_id)
                : null;

            if ($number !== null || $client !== null) {
                $targets[] = ['number' => $number, 'client' => $client];
            }
        }

        return $targets;
    }
}
