<?php

namespace App\Services\Brands;

use App\Models\BrandPartner;
use App\Models\BrandSubscription;
use App\Models\BrandSubscriptionPlan;
use App\Models\User;
use App\Services\Wallet\WalletService;
use App\Support\Auditor;
use Illuminate\Support\Facades\DB;

/**
 * Self-service brand listing lifecycle (BUILD-9 §5): subscribe (plan → first-
 * month charge → brand row + subscription), activate once setup is complete,
 * cancel, and resubscribe onto the SAME brand row. All money moves through
 * WalletService with the same discipline as everywhere else; the first-month
 * charge and the brand row are created in one transaction, so a failed charge
 * never leaves a listing behind (and vice-versa).
 */
class BrandSubscriptionService
{
    public function __construct(private readonly WalletService $wallet)
    {
    }

    /**
     * Start (or restart) a subscription. Charges the first month up-front, then
     * creates the brand (pending_setup) or reactivates the owner's existing brand
     * (skipping onboarding if setup was already complete). Throws
     * InsufficientBalanceException if the wallet can't cover the first month.
     */
    public function subscribe(User $owner, BrandSubscriptionPlan $plan): BrandSubscription
    {
        return DB::transaction(function () use ($owner, $plan) {
            $brand = BrandPartner::where('owner_user_id', $owner->id)->first();
            $setupComplete = $brand !== null && $this->isListable($brand);

            // Charge first month BEFORE creating anything — a failed debit rolls
            // the whole transaction back, so no listing exists without payment.
            $this->wallet->debit($owner, (float) $plan->price_usd_per_month, 'USD', [
                'reference' => 'brand-sub-start:'.$owner->id.':'.now()->timestamp,
                'description' => 'Brand listing — '.$plan->name.' (first month)',
            ]);

            if ($brand === null) {
                $brand = BrandPartner::create([
                    'owner_user_id' => $owner->id,
                    'brand_name' => $owner->name ?: 'My brand',
                    'listing_status' => BrandPartner::STATUS_PENDING,
                    'is_featured' => false,
                    'background_color' => '#0A6E6E',
                    'current_plan_id' => $plan->id,
                    'is_active' => true,
                    'gate_version' => BrandVisibility::newBrandVersion(),
                ]);
            } else {
                $brand->forceFill([
                    'current_plan_id' => $plan->id,
                    // Returning brand with completed setup skips straight past onboarding.
                    'listing_status' => $setupComplete ? BrandPartner::STATUS_ACTIVE : BrandPartner::STATUS_PENDING,
                ])->save();
            }

            $sub = BrandSubscription::create([
                'brand_partner_id' => $brand->id,
                'plan_id' => $plan->id,
                'status' => BrandSubscription::ACTIVE,
                'started_at' => now(),
                'next_billing_at' => now()->addMonthNoOverflow(),
                'last_charged_at' => now(),
                'grace_reminders_sent' => 0,
            ]);

            Auditor::log('brand.subscribed', 'BrandPartner', $brand->id, ['plan' => $plan->name, 'owner' => $owner->id]);

            return $sub;
        });
    }

    /** Flip a pending listing live once every required field is complete (§5.1.3). */
    public function activateIfComplete(BrandPartner $brand): bool
    {
        if ($brand->listing_status !== BrandPartner::STATUS_PENDING || ! $this->isListable($brand)) {
            return false;
        }
        // A live listing needs either an active paid subscription or an admin-granted Pro.
        $sub = $brand->subscription;
        if (! $brand->hasProGrant() && (! $sub || ! in_array($sub->status, [BrandSubscription::ACTIVE, BrandSubscription::TRIALING], true))) {
            return false;
        }
        $brand->forceFill(['listing_status' => BrandPartner::STATUS_ACTIVE])->save();
        Auditor::log('brand.activated', 'BrandPartner', $brand->id);

        return true;
    }

    /**
     * Admin grants complimentary Naara Pro: the user can list their brand without paying and earns the verified badge. No wallet movement and
     * no BrandSubscription (so billing never touches it). The plan decides the limits (handles, videos, follower guarantee); a brand that already
     * pays keeps its own plan. Idempotent — granting again refreshes the note and the term (no expiry = lifetime).
     */
    public function grantPro(User $owner, BrandSubscriptionPlan $plan, User $admin, ?string $note = null, ?\DateTimeInterface $expiresAt = null): BrandPartner
    {
        return DB::transaction(function () use ($owner, $plan, $admin, $note, $expiresAt) {
            $brand = BrandPartner::where('owner_user_id', $owner->id)->lockForUpdate()->first();
            $created = $brand === null;
            if ($created) {
                $brand = BrandPartner::create([
                    'owner_user_id' => $owner->id,
                    'brand_name' => $owner->name ?: 'My brand',
                    'listing_status' => BrandPartner::STATUS_PENDING,
                    'is_featured' => false,
                    'background_color' => '#0A6E6E',
                    'current_plan_id' => $plan->id,
                    'is_active' => true,
                    'gate_version' => BrandVisibility::newBrandVersion(),
                ]);
            }

            $paying = $brand->subscription?->status === BrandSubscription::ACTIVE;
            if (class_exists(\App\Services\Brands\BrandTrialService::class)) {
                app(\App\Services\Brands\BrandTrialService::class)->stopForGrant($brand);   // a trial must not turn into a charge on top of a grant
            }
            $brand->forceFill([
                'current_plan_id' => $paying ? $brand->current_plan_id : $plan->id,
                'pro_granted_at' => $brand->pro_granted_at ?? now(),
                'pro_grant_expires_at' => $expiresAt,   // null = lifetime; granting again replaces the term
                'pro_granted_by' => $brand->pro_granted_by ?? $admin->id,
                'pro_grant_note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 191) : $brand->pro_grant_note,
            ]);
            // A returning/disabled brand with completed setup goes straight back live; a new one starts onboarding.
            if ($brand->listing_status === BrandPartner::STATUS_DISABLED || $brand->listing_status === BrandPartner::STATUS_PAUSED) {
                $brand->listing_status = $this->isListable($brand) ? BrandPartner::STATUS_ACTIVE : BrandPartner::STATUS_PENDING;
            } elseif ($created === false && $brand->listing_status === BrandPartner::STATUS_PENDING && $this->isListable($brand)) {
                $brand->listing_status = BrandPartner::STATUS_ACTIVE;
            }
            $brand->save();

            Auditor::log('brand.pro_granted', 'BrandPartner', $brand->id, ['owner' => $owner->id, 'plan' => $plan->name, 'by' => $admin->id, 'expires' => $expiresAt?->format('Y-m-d') ?? 'lifetime']);

            return $brand;
        });
    }

    /** Remove an admin grant. The badge goes with it unless the brand pays; an unpaid brand's listing is switched off. */
    public function revokePro(BrandPartner $brand, User $admin): void
    {
        DB::transaction(function () use ($brand, $admin) {
            $brand->forceFill(['pro_granted_at' => null, 'pro_grant_expires_at' => null, 'pro_granted_by' => null, 'pro_grant_note' => null]);
            if (! in_array($brand->subscription?->status, [BrandSubscription::ACTIVE, BrandSubscription::TRIALING], true)) {
                $brand->listing_status = BrandPartner::STATUS_DISABLED;
            }
            $brand->save();
            Auditor::log('brand.pro_revoked', 'BrandPartner', $brand->id, ['by' => $admin->id]);
        });
    }

    /** Business-initiated cancellation (§5.3) — distinct from a billing pause. */
    public function cancel(BrandPartner $brand): void
    {
        DB::transaction(function () use ($brand) {
            if ($sub = $brand->subscription) {
                $sub->forceFill(['status' => BrandSubscription::CANCELLED, 'cancelled_at' => now()])->save();
            }
            $brand->forceFill(['listing_status' => BrandPartner::STATUS_DISABLED])->save();
            Auditor::log('brand.cancelled', 'BrandPartner', $brand->id);
        });
    }

    /** Every required onboarding field present (§5.1.2). */
    public function setupComplete(BrandPartner $brand): bool
    {
        return BrandVisibility::passes($brand);   // version-aware content gate (Brand-Growth Phase 3)
    }

    /**
     * May this brand be shown in the directory once it is entitled? The content gate must pass, and a brand created under the soft gate must also have been
     * PUBLISHED by its owner (a deliberate button, so nothing appears before they are ready). Older brands (gate 1) keep the automatic behaviour.
     */
    public function isListable(BrandPartner $brand): bool
    {
        return $this->setupComplete($brand) && ((int) $brand->gate_version < BrandVisibility::GATE_V2 || $brand->published_at !== null);
    }
}
