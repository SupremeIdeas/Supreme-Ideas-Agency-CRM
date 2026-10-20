<?php

namespace App\Services\N2N\Calls;

use App\Models\N2N\Block;
use App\Models\User;
use App\Services\N2N\Media\NaaraPro;
use App\Services\N2N\Messaging\Privacy;

/**
 * Who may ring whom (N2N Batch 10, MASTER-ONLY): ONE policy. Default: connections and Naara Pro brands. Blocked, not-allowed and
 * do-not-disturb all answer the same way for the caller (the call "rings" and is never delivered), so none of them is an oracle.
 */
class CallPolicy
{
    public static function deliverable(User $caller, User $callee): bool
    {
        if (! $callee->is_active || Block::query()->where(fn ($q) => $q->where('blocker_id', $callee->id)->where('blocked_id', $caller->id))->orWhere(fn ($q) => $q->where('blocker_id', $caller->id)->where('blocked_id', $callee->id))->exists()) {
            return false;
        }
        if ($callee->n2n_dnd_until !== null && now()->lt($callee->n2n_dnd_until)) {
            return false;
        }
        $who = (string) ($callee->n2n_who_can_call ?: 'connections');

        return match ($who) {
            'nobody' => false,
            'everyone' => true,
            default => Privacy::connected($caller->id, $callee->id) || NaaraPro::active($callee),   // a Pro brand can be called by a customer
        };
    }
}
