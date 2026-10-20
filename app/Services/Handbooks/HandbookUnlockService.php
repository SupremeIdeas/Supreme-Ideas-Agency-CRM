<?php

namespace App\Services\Handbooks;

use App\Models\BrandPartner;
use App\Models\Handbook;
use App\Models\HandbookEntitlement;
use App\Models\HandbookProSlot;
use App\Models\User;
use App\Services\N2N\Media\NaaraPro;
use App\Support\Auditor;
use Illuminate\Database\UniqueConstraintViolationException;
use App\Support\Handbooks\HandbookSettings;
use Illuminate\Support\Facades\DB;

/**
 * The ways a handbook becomes Unlocked (read in-app, no download) without paying for it (MASTER-ONLY): a Naara eSIM for its country, a Naara Pro slot, or an admin grant.
 * Owned is never downgraded, an unlock is idempotent, and Pro slot unlocks are LOCKED (not deleted) when Pro lapses and restored when it comes back.
 */
class HandbookUnlockService
{
    /** Create an Unlocked entitlement unless the person already has one. Returns it, or null when they already own the book. */
    public function unlock(User $user, Handbook $b, string $source, ?string $ref = null): ?HandbookEntitlement
    {
        $existing = HandbookEntitlement::query()->where('user_id', $user->id)->where('handbook_id', $b->id)->first();
        if ($existing !== null) {
            return $existing->level === HandbookEntitlement::OWNED ? null : $existing;
        }
        try {
            return HandbookEntitlement::query()->create(['user_id' => $user->id, 'handbook_id' => $b->id, 'level' => HandbookEntitlement::UNLOCKED, 'source' => $source, 'source_ref' => $ref, 'price_paid_usd' => 0]);
        } catch (UniqueConstraintViolationException) {
            return HandbookEntitlement::query()->where('user_id', $user->id)->where('handbook_id', $b->id)->first();   // a concurrent request made it
        }
    }

    /** The country's flagship (series slot S1), published only. */
    public function flagshipFor(string $countryCode): ?Handbook
    {
        return Handbook::query()->published()->where('country_code', strtoupper($countryCode))->where('series_slot', 'S1')->orderBy('sort_order')->orderBy('id')->first();
    }

    public function adminGrant(User $user, Handbook $b, User $by): HandbookEntitlement
    {
        $e = HandbookEntitlement::query()->updateOrCreate(['user_id' => $user->id, 'handbook_id' => $b->id], ['level' => HandbookEntitlement::OWNED, 'source' => 'admin', 'source_ref' => 'admin:'.$by->id, 'price_paid_usd' => 0, 'locked_at' => null]);
        Auditor::log('handbook.admin_grant', Handbook::class, $b->id, ['user' => $user->id, 'by' => $by->id]);

        return $e;
    }

    // ---- Naara Pro slots -------------------------------------------------------------------------------------------------------------------------

    /** The current Pro term key: the brand subscription (a new one after a lapse is a new term) or a lifetime grant. Null when the person is not Pro. */
    public function proTerm(User $user): ?string
    {
        if (! NaaraPro::active($user)) {
            return null;
        }
        $brand = BrandPartner::query()->where('owner_user_id', $user->id)->with('subscription')->first();
        if (! $brand) {
            return null;
        }

        return $brand->subscription && in_array($brand->subscription->status, ['active', 'trialing'], true) ? 'sub:'.$brand->subscription->id : 'grant:'.$brand->id;
    }

    public function slotsUsed(User $user, ?string $term = null): int
    {
        $term ??= $this->proTerm($user);

        return $term === null ? 0 : HandbookProSlot::query()->where('user_id', $user->id)->where('pro_term', $term)->count();
    }

    public function slotsLeft(User $user): int
    {
        $term = $this->proTerm($user);

        return $term === null || ! HandbookSettings::get('pro_unlock_enabled') ? 0 : max(0, HandbookSettings::proSlots() - $this->slotsUsed($user, $term));
    }

    /** Spend one Pro slot on a handbook. @throws HandbookException */
    public function useSlot(User $user, Handbook $b): HandbookEntitlement
    {
        if ($b->status !== Handbook::PUBLISHED) {
            throw new HandbookException('That handbook is not available.');
        }
        if (! HandbookSettings::get('pro_unlock_enabled') || ! $b->unlock_via_pro) {
            throw new HandbookException('This handbook cannot be unlocked with a Pro slot.');
        }
        $term = $this->proTerm($user) ?? throw new HandbookException('Free unlocks are a Naara Pro benefit.');

        return DB::transaction(function () use ($user, $b, $term) {
            // Serialise a person's slot spending so two tabs cannot take a fourth.
            User::query()->whereKey($user->id)->lockForUpdate()->first();
            $existing = HandbookEntitlement::query()->where('user_id', $user->id)->where('handbook_id', $b->id)->first();
            if ($existing !== null && $existing->locked_at === null) {
                throw new HandbookException($existing->level === HandbookEntitlement::OWNED ? 'You already own this handbook.' : 'You already have this handbook unlocked.');
            }
            if ($this->slotsUsed($user, $term) >= HandbookSettings::proSlots()) {
                throw new HandbookException('You have used all '.HandbookSettings::proSlots().' free unlocks for this Pro term.');
            }
            HandbookProSlot::query()->create(['user_id' => $user->id, 'pro_term' => $term, 'handbook_id' => $b->id, 'used_at' => now()]);
            if ($existing !== null) {   // a previously locked unlock being re-chosen
                $existing->forceFill(['locked_at' => null])->save();

                return $existing;
            }

            return HandbookEntitlement::query()->create(['user_id' => $user->id, 'handbook_id' => $b->id, 'level' => HandbookEntitlement::UNLOCKED, 'source' => 'pro_slot', 'source_ref' => $term, 'price_paid_usd' => 0]);
        });
    }

    /** Lock this person's Pro-slot unlocks when Pro lapsed, restore them when it is back. Idempotent; called lazily and by the daily job. */
    public function syncProLocks(User $user): void
    {
        $slots = HandbookEntitlement::query()->where('user_id', $user->id)->where('source', 'pro_slot')->where('level', HandbookEntitlement::UNLOCKED);
        $term = $this->proTerm($user);
        if ($term === null) {
            (clone $slots)->whereNull('locked_at')->update(['locked_at' => now()]);

            return;
        }
        // Only the CURRENT term's picks are restored; an older term's picks stay locked.
        (clone $slots)->where('source_ref', $term)->whereNotNull('locked_at')->update(['locked_at' => null]);
        (clone $slots)->where('source_ref', '!=', $term)->whereNull('locked_at')->update(['locked_at' => now()]);
    }
}
