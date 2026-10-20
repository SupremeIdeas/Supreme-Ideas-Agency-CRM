<?php

namespace App\Services\Business\AiVoice;

use App\Exceptions\InsufficientBalanceException;
use App\Models\AiVoiceAgentSession;
use App\Models\WalletGroup;
use App\Services\Pricing\PricingEngine;
use App\Services\Wallet\WalletService;
use App\Support\Business\BusinessSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Agent minutes are paid the way VoiceDialerService pays human-dialled minutes (MASTER-ONLY, Business Suite Prompt 17): quote the retail per minute live,
 * HOLD a funded block with an atomic wallet debit before the call connects, then on hangup bill the minutes used and refund the rest. Settlement is
 * idempotent (row lock plus settled_at), so the server closing and Twilio's action callback can both call it. The workspace owner's wallet pays.
 * The price is PricingEngine's (supplier cost is an admin input, markup and MarginGuard applied); only the retail is ever stored on the session.
 */
class AgentBilling
{
    public function __construct(private WalletService $wallet, private PricingEngine $pricing)
    {
    }

    public function retailPerMinute(): float
    {
        return $this->pricing->calculateCallAddonRetail(BusinessSettings::cost('agent_per_min'), 'agent');
    }

    /**
     * Hold the funded block and open the session row. Returns null when the owner cannot fund even one minute (nothing is charged).
     *
     * @param  array<string,mixed>  $attrs  call_flow_id, call_flow_node_id, virtual_number_id, call_reference, from_number
     */
    public function begin(WalletGroup $group, array $attrs): ?AiVoiceAgentSession
    {
        $owner = $group->owner;
        $retail = $this->retailPerMinute();
        $balance = round((float) ($owner->wallet?->usd_balance ?? 0), 4);
        $funded = $retail > 0 ? (int) floor($balance / $retail + 1e-9) : 0;
        $funded = min($funded, BusinessSettings::aiInt('max_minutes'));
        if ($funded < 1) {
            return null;
        }

        $hold = round($funded * $retail, 4);
        $ref = 'ai-hold:'.$group->id.':'.Str::uuid();
        try {
            $this->wallet->debit($owner, $hold, 'USD', ['reference' => $ref, 'description' => 'AI voice agent authorization ('.$funded.' min)']);
        } catch (InsufficientBalanceException) {
            return null;
        }

        return AiVoiceAgentSession::create($attrs + [
            'wallet_group_id' => $group->id, 'status' => AiVoiceAgentSession::IN_PROGRESS, 'started_at' => now(),
            'retail_per_min' => $retail, 'minutes_authorized' => $funded, 'amount_held' => $hold, 'hold_reference' => $ref,
        ]);
    }

    /** Bill what was used and refund the rest. Safe to call any number of times. */
    public function settle(AiVoiceAgentSession $session, int $durationSeconds): AiVoiceAgentSession
    {
        return DB::transaction(function () use ($session, $durationSeconds) {
            $locked = AiVoiceAgentSession::whereKey($session->getKey())->lockForUpdate()->first();
            if ($locked->settled_at !== null) {
                return $locked;
            }

            $billed = $durationSeconds > 0 ? min(max(1, (int) ceil($durationSeconds / 60)), (int) $locked->minutes_authorized) : 0;
            $charged = round($billed * (float) $locked->retail_per_min, 4);
            $refund = round((float) $locked->amount_held - $charged, 4);

            if ($refund > 0) {
                $this->wallet->refund($locked->walletGroup->owner, $refund, 'USD', [
                    'reference' => 'ai-refund:'.$locked->id, 'description' => 'Unused AI voice agent minutes refunded', 'notify' => false,
                ]);
            }

            $locked->update([
                'minutes_billed' => $billed, 'amount_charged' => $charged, 'refunded' => max(0, $refund),
                'duration_seconds' => $durationSeconds, 'settled_at' => now(), 'ended_at' => $locked->ended_at ?? now(),
            ]);

            return $locked;
        });
    }
}
