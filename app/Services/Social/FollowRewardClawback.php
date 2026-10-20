<?php

namespace App\Services\Social;

use App\Models\CreditLedger;
use App\Models\SocialFollowClaim;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Support\Auditor;
use Illuminate\Support\Facades\DB;

/**
 * Admin clawback of a follow reward (Brand-Growth Phase 7, MASTER-ONLY). Takes back what the claim paid, capped at what the person still holds (credits
 * already spent stay spent, and the shortfall is recorded), keeps the claim row so the same follow can never be re-claimed, and writes an audit entry.
 * Idempotent: the debit has a fixed reference and a claim is clawed back at most once.
 */
class FollowRewardClawback
{
    public function __construct(private readonly CreditService $credits)
    {
    }

    /** @return array{taken: float, shortfall: float} */
    public function reverse(SocialFollowClaim $claim, User $admin, string $reason): array
    {
        return DB::transaction(function () use ($claim, $admin, $reason) {
            $claim = SocialFollowClaim::whereKey($claim->id)->lockForUpdate()->firstOrFail();
            if ($claim->clawed_back_at !== null) {
                return ['taken' => (float) $claim->clawed_back_credits, 'shortfall' => 0.0];
            }
            $user = User::findOrFail($claim->user_id);
            $source = $claim->brand_partner_handle_id ? 'brand_follow' : 'social_follow';
            $ref = $claim->brand_partner_handle_id ? "brand_follow:{$claim->user_id}:{$claim->brand_partner_handle_id}" : "social_follow:{$claim->user_id}:{$claim->handle_id}";
            $paid = (float) CreditLedger::where('user_id', $claim->user_id)->where('reference', $ref)->where('type', 'earn')->sum('amount');
            $take = round(min($paid, max(0.0, $this->credits->balance($user))), 2);
            if ($take > 0) {
                $this->credits->spend($user, $take, 'follow_clawback', 'follow_clawback:'.$claim->id, 'Follow reward reversed');
            }
            $claim->forceFill(['clawed_back_at' => now(), 'clawed_back_by' => $admin->id, 'clawback_reason' => mb_substr($reason, 0, 191), 'clawed_back_credits' => $take])->save();
            Auditor::log('follow_reward.clawed_back', 'SocialFollowClaim', $claim->id, ['user' => $claim->user_id, 'paid' => $paid, 'taken' => $take, 'by' => $admin->id, 'source' => $source]);

            return ['taken' => $take, 'shortfall' => round($paid - $take, 2)];
        });
    }
}
