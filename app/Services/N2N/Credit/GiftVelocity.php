<?php

namespace App\Services\N2N\Credit;

use App\Models\N2N\CreditGift;
use App\Models\N2N\UsageLedger;
use App\Models\Setting;
use App\Support\Auditor;

/**
 * Abuse monitors for credit gifting (N2N, MASTER-ONLY): gift rings, a new account receiving from many senders, repeated refunds. It FLAGS
 * for a human; it never blocks or confiscates on its own. Thresholds are admin settings.
 */
class GiftVelocity
{
    /** @return list<array{kind: string, user_id: int, detail: string}> */
    public function scan(bool $log = true): array
    {
        $day = now()->subDay();
        $flags = [];
        $maxSent = max(2, (int) Setting::getValue('n2n.velocity_gifts_per_sender_day', 8));
        $maxSenders = max(2, (int) Setting::getValue('n2n.velocity_senders_per_receiver_day', 5));
        $maxRefunds = max(2, (int) Setting::getValue('n2n.velocity_refunds_per_sender_day', 4));

        foreach (UsageLedger::query()->where('type', 'share_out')->where('created_at', '>=', $day)->selectRaw('user_id, count(*) n')->groupBy('user_id')->having('n', '>=', $maxSent)->get() as $r) {
            $flags[] = ['kind' => 'many_shares_out', 'user_id' => (int) $r->user_id, 'detail' => "{$r->n} shares in 24h"];
        }
        foreach (UsageLedger::query()->where('type', 'share_in')->where('created_at', '>=', $day)->selectRaw('user_id, count(distinct counterparty_user_id) n')->groupBy('user_id')->having('n', '>=', $maxSenders)->get() as $r) {
            $flags[] = ['kind' => 'many_senders_in', 'user_id' => (int) $r->user_id, 'detail' => "{$r->n} different senders in 24h"];
        }
        foreach (CreditGift::query()->where('status', CreditGift::REFUNDED)->where('refunded_at', '>=', $day)->selectRaw('sender_id, count(*) n')->groupBy('sender_id')->having('n', '>=', $maxRefunds)->get() as $r) {
            $flags[] = ['kind' => 'repeated_gift_refunds', 'user_id' => (int) $r->sender_id, 'detail' => "{$r->n} expired gifts in 24h"];
        }
        foreach ($log ? $flags : [] as $f) {
            Auditor::log('n2n.credit_velocity_flag', \App\Models\User::class, $f['user_id'], $f);
        }

        return $flags;
    }
}
