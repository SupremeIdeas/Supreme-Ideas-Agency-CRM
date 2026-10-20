<?php

namespace App\Services\Handbooks;

use App\Models\Handbook;
use App\Models\HandbookEntitlement;
use App\Models\User;
use App\Support\Handbooks\HandbookPricing;
use App\Support\Handbooks\HandbookSettings;

/** Who may read, download or buy what (MASTER-ONLY). The single place the access matrix lives. */
class HandbookAccess
{
    /** @return 'owned'|'unlocked'|null  (a Pro-slot unlock whose Pro term lapsed is locked and counts as nothing until renewal) */
    public function levelFor(?User $user, Handbook $b): ?string
    {
        if (! $user) {
            return null;
        }
        $e = HandbookEntitlement::query()->where('user_id', $user->id)->where('handbook_id', $b->id)->first();

        return $e === null || $e->locked_at !== null ? null : $e->level;
    }

    public function canRead(?User $user, Handbook $b): bool
    {
        return $b->status === Handbook::PUBLISHED && $this->levelFor($user, $b) !== null;
    }

    /** Download (EPUB and PDF) is for Owned only. Unlocked is read-in-app. */
    public function canDownload(?User $user, Handbook $b): bool
    {
        return $b->status === Handbook::PUBLISHED && $this->levelFor($user, $b) === HandbookEntitlement::OWNED;
    }

    /**
     * What this viewer is offered on the book page.
     *
     * @return array{level:?string, read:bool, buy:?float, upgrade:?float, slot:bool, slots_left:int, signed_in:bool}
     */
    public function offers(?User $user, Handbook $b): array
    {
        $level = $this->levelFor($user, $b);
        $slots = $user ? app(HandbookUnlockService::class)->slotsLeft($user) : 0;

        $canBuy = HandbookSettings::get('purchase_enabled') && $b->allow_purchase;

        return [
            'level' => $level,
            'read' => $level !== null,
            'buy' => $level === null && $canBuy ? HandbookPricing::fullUsd($b->list_price_usd) : null,
            'upgrade' => $level === HandbookEntitlement::UNLOCKED && $canBuy && HandbookSettings::get('upgrade_enabled') ? HandbookPricing::upgradeUsd($b->list_price_usd) : null,
            'slot' => $user !== null && $level === null && $slots > 0 && $b->unlock_via_pro,
            'slots_left' => $slots,
            'signed_in' => $user !== null,
        ];
    }
}
