<?php

namespace App\Services\N2N\Credit;

use App\Models\BrandSubscription;

/**
 * Grants usage credit when a brand subscription starts or renews (N2N, MASTER-ONLY). Idempotent by `sub:{id}:{period}`, so a re-run of the
 * billing command can never grant twice. No expiry by default: a standing liability the owner must decide on (see PROGRESS.md).
 */
class SubscriptionGrant
{
    public function saved(BrandSubscription $sub): void
    {
        if ($sub->status !== BrandSubscription::ACTIVE || ! ($sub->wasRecentlyCreated || $sub->wasChanged('last_charged_at'))) {
            return;
        }
        $grant = Money::cents($sub->plan?->n2n_credit_grant ?? 0);
        $owner = $sub->brandPartner?->owner_user_id;
        if ($grant <= 0 || $owner === null) {
            return;
        }
        $period = optional($sub->last_charged_at ?? $sub->started_at)->timestamp ?? 0;
        app(UsageLedgerService::class)->apply([[$owner, 'shareable', $grant, 'grant', 'sub:'.$sub->id.':'.$period, null, ['plan' => $sub->plan_id]]]);
    }
}
