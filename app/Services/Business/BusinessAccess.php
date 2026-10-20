<?php

namespace App\Services\Business;

use App\Models\BrandPartner;
use App\Models\BrandSubscription;
use App\Models\BrandSubscriptionPlan;
use App\Models\User;
use App\Models\WalletGroup;
use App\Support\Business\BusinessPlanFeatures;
use App\Support\Business\BusinessSettings;

/**
 * Who may use which NaaraBusiness Suite feature (MASTER-ONLY). The ONE gate, so a page, a button and a webhook can never disagree.
 *
 * A feature is allowed when (1) its module is switched on in Business Suite settings, and (2) either the admin has opened the Suite to everyone
 * (access model "open"), or the WORKSPACE OWNER is on an active Naara Pro plan that includes it. Seats (agents, viewers) never need a plan of their
 * own: they work inside the owner's. A lapsed plan pauses access without deleting a thing; renewing brings everything back.
 */
class BusinessAccess
{
    public const STATE_OPEN = 'open';       // the admin opened the Suite to everyone

    public const STATE_ACTIVE = 'active';   // on a plan right now

    public const STATE_LAPSED = 'lapsed';   // was on a plan, is not any more

    public const STATE_NONE = 'none';       // never was

    /** The plan an owner is on right now, or null when they are not Naara Pro (a lapsed or cancelled plan is not Pro). */
    public function planFor(User $owner): ?BrandSubscriptionPlan
    {
        $brand = BrandPartner::query()->where('owner_user_id', $owner->id)->with(['subscription.plan', 'plan'])->first();
        if ($brand === null || ! $brand->is_active || ! $brand->isNaaraPro()) {
            return null;
        }
        $paying = $brand->subscription?->status === BrandSubscription::ACTIVE;

        return ($paying ? $brand->subscription->plan : null) ?? $brand->plan;
    }

    /** Is this feature usable by (the workspace owned by) this person right now? */
    public function allows(User $owner, string $feature): bool
    {
        if (! BusinessPlanFeatures::live($feature)) {
            return false;
        }
        if (BusinessSettings::accessModel() === 'open') {
            return true;
        }

        return $this->planFor($owner)?->grantsFeature($feature) ?? false;
    }

    /** The same question asked about a workspace: its OWNER's plan decides. */
    public function allowsWorkspace(WalletGroup $group, string $feature): bool
    {
        $owner = $group->owner;

        return $owner !== null && $this->allows($owner, $feature);
    }

    public function mayCreateWorkspace(User $user): bool
    {
        return $this->allows($user, BusinessPlanFeatures::WORKSPACE);
    }

    /** For the upsell copy: open / active / lapsed / none. */
    public function state(User $owner): string
    {
        if (BusinessSettings::accessModel() === 'open') {
            return self::STATE_OPEN;
        }
        if ($this->planFor($owner) !== null) {
            return self::STATE_ACTIVE;
        }
        $hadPlan = BrandPartner::query()->where('owner_user_id', $owner->id)
            ->where(fn ($q) => $q->whereNotNull('pro_granted_at')->orWhereHas('subscription'))->exists();

        return $hadPlan ? self::STATE_LAPSED : self::STATE_NONE;
    }

    /** Does this member's plan include premium dashboard skins? */
    public function premiumSkins(User $user): bool
    {
        return $this->planFor($user)?->grantsPremiumSkins() ?? false;
    }

    /**
     * A workspace limit: the owner's plan may raise seats, numbers and channels above the platform default; everything else (and any
     * workspace whose owner has no plan) uses the platform default. A plan can only RAISE a limit, never lower the default below what the admin set.
     */
    public function limit(?WalletGroup $group, string $name): int
    {
        $platform = BusinessSettings::limit($name);
        if ($group === null || ! array_key_exists($name, BusinessPlanFeatures::LIMITS) || BusinessSettings::accessModel() === 'open') {
            return $platform;
        }
        $over = $group->owner !== null ? $this->planFor($group->owner)?->limitOverride($name) : null;

        return $over !== null ? max($platform, $over) : $platform;
    }
}
