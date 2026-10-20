<?php

namespace App\Services\Updater;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\LicenseActivationException;
use App\Jobs\AlertAdminJob;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhiteLabelInstance;
use App\Models\WhiteLabelLicensePayment;
use App\Models\WhiteLabelLicensePlan;
use App\Notifications\WhiteLabelActivatedNotification;
use App\Notifications\WhiteLabelApprovalFailedNotification;
use App\Notifications\WhiteLabelApprovalNeedsRetryNotification;
use App\Notifications\WhiteLabelRequestNeedsReviewNotification;
use App\Notifications\WhiteLabelRequestSubmittedNotification;
use App\Notifications\WhiteLabelUpgradeDeadlineSetNotification;
use App\Notifications\WhiteLabelUpgradeMissedDeadlineNotification;
use App\Notifications\WhiteLabelUpgradeProposedNotification;
use App\Notifications\WhiteLabelUpgradeSucceededNotification;
use App\Services\Platform\PlatformEarningsService;
use App\Services\Wallet\WalletService;
use App\Support\Auditor;
use App\Support\Mailer;
use App\Support\ThemeAddonCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * License Authority for the white-label program (Batch 6). The single place that
 * issues, exchanges, and revokes the two credentials a subscriber holds — kept
 * structurally parallel to ApiClientService (the Developer API's token authority)
 * so the token discipline is identical: the plaintext Sanctum token is returned
 * exactly ONCE at mint/rotation and never stored (only its last four, for
 * display), and any state change that should cut access deletes every live token.
 *
 * The credential split is the whole design:
 *   - the LICENSE KEY is the durable enrolment credential (issued by an admin,
 *     tied to a tier). It is not a bearer token; it only ever buys ONE thing —
 *     the right to mint an API token via activateWithKey().
 *   - the SANCTUM TOKEN is the operational bearer credential the fork calls the
 *     distribution API with. Rotatable and revocable independently of the key.
 *
 * Because a valid key mints access, activation is the security-sensitive seam:
 * it must reject a revoked key and any non-active instance, and must not reveal
 * which failure occurred (LicenseActivationException handles that).
 */
/**
 * BOUNDARY: master-only. This class issues/oversees/distributes across
 * white-label instances. It must NEVER exist in a white-label build
 * (normal or Extended) — a child is a consumer + a leaf puller, never a
 * hub. See docs/architecture/WHITE-LABEL-LICENSE-BOUNDARY.md. CI fitness
 * tests in the child repos fail the build if this reappears there.
 */
class WhiteLabelLicenseService
{
    public function __construct(
        private readonly WalletService $wallet = new WalletService,
        private readonly PlatformEarningsService $earnings = new PlatformEarningsService,
    ) {}

    /**
     * Self-serve registration REQUEST: records a would-be subscriber as a pending
     * instance with no key and no token. An admin reviews it and issues a license
     * (issueLicense) — mirrors Merchant's pending → active review trail. Idempotent
     * on slug is NOT assumed here; the caller validates uniqueness first.
     *
     * Prompt 21-EXT §2/§6 — a Merchant-V2 self-service request additionally carries
     * `merchant_id`, `license_plan_id` (the chosen carousel card), `requested_tier`
     * (derived from that plan's own `tier`, never typed separately), and the
     * hosting preference/disclaimer fields. The resell-status flag for the
     * requested tier is checked here too — server-side, never trusting that the
     * carousel already hid a closed tier (defense in depth, same principle this
     * codebase applies everywhere else).
     *
     * Owner request (2026-09-15) — a self-service request may also carry an
     * optional `theme_addon` (App\Support\ThemeAddonCatalog key). Its price
     * is snapshotted onto the instance NOW (theme_addon_price_usd), never
     * recomputed later, so a subsequent admin price retune never changes
     * what an already-pending request will be charged. An invalid/unknown
     * key degrades to None ($0) rather than erroring the whole request.
     *
     * @throws \RuntimeException when the requested tier's resell status is closed.
     */
    public function register(array $details): WhiteLabelInstance
    {
        $requestedTier = $details['requested_tier'] ?? null;
        if ($requestedTier !== null && ! WhiteLabelLicensePlan::resellOpenForTier($this->normalizeTier($requestedTier))) {
            throw new \RuntimeException('resell_closed');
        }

        $themeAddon = (string) ($details['theme_addon'] ?? ThemeAddonCatalog::NONE);
        $themeAddon = ThemeAddonCatalog::isValid($themeAddon) ? $themeAddon : ThemeAddonCatalog::NONE;
        $themeAddonPrice = ThemeAddonCatalog::priceFor($themeAddon);

        $instance = WhiteLabelInstance::create([
            'brand_name' => $this->cleanName($details['brand_name'] ?? 'White-label brand'),
            'slug' => $details['slug'] ?? Str::slug(($details['brand_name'] ?? 'brand').'-'.Str::random(6)),
            'contact_email' => $details['contact_email'] ?? '',
            'owner_user_id' => $details['owner_user_id'] ?? null,
            'merchant_id' => $details['merchant_id'] ?? null,
            'license_plan_id' => $details['license_plan_id'] ?? null,
            'acquisition_method' => $details['acquisition_method'] ?? WhiteLabelInstance::ACQUISITION_ADMIN_PROVISIONED,
            'requested_tier' => $requestedTier,
            'hosting_preference' => $details['hosting_preference'] ?? null,
            'hosting_disclaimer_acknowledged_at' => ! empty($details['hosting_disclaimer_acknowledged']) ? now() : null,
            'registration_note' => isset($details['note']) ? Str::limit((string) $details['note'], 2000, '') : null,
            'theme_addon' => $themeAddon === ThemeAddonCatalog::NONE ? null : $themeAddon,
            'theme_addon_price_usd' => $themeAddon === ThemeAddonCatalog::NONE ? null : $themeAddonPrice,
            'status' => WhiteLabelInstance::PENDING,
        ]);

        Auditor::log('white_label.registered', WhiteLabelInstance::class, $instance->id, [
            'brand' => $instance->brand_name,
            'slug' => $instance->slug,
            'acquisition_method' => $instance->acquisition_method,
        ]);

        // Content/billing/hot-menu blueprint Phase F1 — only a merchant's own
        // self-service request waits on admin approval; an admin-provisioned
        // instance is issued a license in the very same call by the caller
        // (Admin\WhiteLabelRegistry::issueNewLicense()), so there is nothing
        // pending to notify anyone about.
        if ($instance->acquisition_method === WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE) {
            if ($instance->owner) {
                Mailer::notify($instance->owner, new WhiteLabelRequestSubmittedNotification($instance->brand_name));
            }
            $this->notifyAdmins(new WhiteLabelRequestNeedsReviewNotification($instance->brand_name));
        }

        return $instance;
    }

    /**
     * Prompt 21-EXT §3.1 — admin sets (or confirms, pre-filled from the chosen
     * plan) the price on a pending self-service request. Deliberately does NOT
     * issue a license: the instance stays PENDING until the merchant actually
     * pays via payAndActivate(). Purely a review-trail/pricing action — no
     * money moves here.
     */
    public function priceForPayment(WhiteLabelInstance $instance, float $priceUsd, ?int $reviewerId = null): WhiteLabelInstance
    {
        $instance->forceFill([
            'price_usd' => round($priceUsd, 2),
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ])->save();

        Auditor::log('white_label.priced_for_payment', WhiteLabelInstance::class, $instance->id, [
            'brand' => $instance->brand_name,
            'price_usd' => $instance->price_usd,
        ]);

        return $instance;
    }

    /**
     * Issue (or re-issue) a license on an instance: generate a fresh unique key,
     * set the tier, mark it active, and stamp the review trail. This is what an
     * admin does after a purchase/approval. Re-issuing generates a NEW key and
     * kills any token minted from the old one, so a leaked key can be replaced
     * without deleting the instance's history.
     *
     * Note: issuing a license does NOT mint an API token — the deployed fork does
     * that itself by exchanging the key at activateWithKey(). The key is what you
     * hand the buyer.
     */
    public function issueLicense(WhiteLabelInstance $instance, string $tier, ?int $reviewerId = null): WhiteLabelInstance
    {
        $tier = $this->normalizeTier($tier);

        // A brand-new key invalidates anything minted from a prior one.
        $instance->tokens()->delete();

        $instance->forceFill([
            'tier' => $tier,
            // Batch 8: a freshly-issued license starts at the level its tier
            // implies — Extended fully unlocked, Normal locked-down until paid.
            'entitlement_level' => WhiteLabelInstance::defaultLevelForTier($tier),
            'license_key' => $this->generateUniqueKey(),
            'api_token_last_four' => null,
            'license_issued_at' => now(),
            'license_revoked_at' => null,
            'status' => WhiteLabelInstance::ACTIVE,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ])->save();

        Auditor::log('white_label.license_issued', WhiteLabelInstance::class, $instance->id, [
            'brand' => $instance->brand_name,
            'tier' => $tier,
            'entitlement_level' => $instance->entitlement_level,
        ]);

        return $instance;
    }

    /**
     * Prompt 21-EXT §3.3 — upgrade a LIVE instance's tier (normal → extended)
     * without re-issuing its license key or touching its Sanctum tokens.
     * Deliberately NOT `issueLicense()`: that method regenerates the key and
     * deletes every live token on every call, which is correct for a genuinely
     * fresh license but would break an already-deployed fork's working
     * connection at the exact moment its owner finishes paying off the balance
     * to Extended. Modeled on setEntitlementLevel()'s existing non-destructive
     * shape — a tier/entitlement change without touching credentials is
     * already a normal, supported operation in this codebase.
     */
    public function upgradeTier(WhiteLabelInstance $instance, string $newTier): WhiteLabelInstance
    {
        $newTier = $this->normalizeTier($newTier);

        $instance->forceFill([
            'tier' => $newTier,
            'entitlement_level' => WhiteLabelInstance::defaultLevelForTier($newTier),
        ])->save();

        Auditor::log('white_label.tier_upgraded', WhiteLabelInstance::class, $instance->id, [
            'brand' => $instance->brand_name,
            'tier' => $newTier,
            'entitlement_level' => $instance->entitlement_level,
        ]);

        return $instance;
    }

    /**
     * Content/billing/hot-menu blueprint Phase F1 — approve a priced
     * self-service request and activate it in one step: super-admin-only,
     * and the ONLY thing that ever charges a self-service instance (the
     * merchant no longer has a "pay now" of their own — requesting a
     * license never charges anything by itself, see register()). Only
     * reachable once an admin has set price_usd (Prompt 21 §4.2) — never
     * charges an unpriced, unreviewed request.
     *
     * Reuses WalletService::charge() exactly (money-safety rule 1.2's
     * refund-on-failure applies unmodified) against the REQUESTING
     * MERCHANT's own wallet (never the approving admin's) — inside the
     * closure, issues the license exactly as the admin-provisioned path
     * already does, records the payment ledger row, and credits the
     * platform earnings bucket for the full amount (a flat product sale, no
     * underlying cost to net out).
     *
     * If the charge fails for insufficient funds, this is NOT re-thrown as a
     * blind failure: the instance moves to APPROVED_AWAITING_FUNDS (clearly
     * distinguishable from "never yet approved"), the merchant is told
     * exactly how much more is needed, and the admin is told to re-trigger
     * this same call once the merchant tops up — never "redo the whole
     * approval from scratch". Any OTHER failure still propagates untouched.
     *
     * Owner request (2026-09-15) — if the instance carries a theme_addon
     * (chosen at request time, priced then), it is billed in this SAME
     * atomic wallet charge as one combined total — never a second, separate
     * checkout — but recorded as its own WhiteLabelLicensePayment ledger row
     * (KIND_THEME_ADDON) so the license price and the add-on price stay
     * independently auditable. A request with no add-on (the default —
     * "just set up my platform, no custom theme") is billed exactly as
     * before, with zero extra cost and zero extra ledger rows.
     *
     * @throws LicenseActivationException when not priced or already licensed.
     */
    public function approveAndActivate(WhiteLabelInstance $instance, ?int $reviewerId = null): WhiteLabelInstance
    {
        if ($instance->price_usd === null) {
            throw new LicenseActivationException('not_priced');
        }
        if ($instance->hasLiveLicense()) {
            throw new LicenseActivationException('already_licensed');
        }

        $payer = $instance->owner;
        if ($payer === null) {
            throw new LicenseActivationException('no_payer');
        }

        $licenseAmount = (float) $instance->price_usd;
        $addonAmount = (float) ($instance->theme_addon_price_usd ?? 0);
        $totalAmount = round($licenseAmount + $addonAmount, 2);
        $tier = $this->normalizeTier($instance->requested_tier ?? WhiteLabelInstance::TIER_NORMAL);
        $ref = 'wl-license:'.$instance->id.':initial:'.now()->timestamp;

        try {
            $activated = $this->wallet->charge($payer, $totalAmount, 'USD', function () use ($instance, $tier, $reviewerId, $licenseAmount, $addonAmount, $ref) {
                $this->issueLicense($instance, $tier, $reviewerId);
                $instance->forceFill(['payment_reference' => $ref])->save();

                WhiteLabelLicensePayment::create([
                    'white_label_instance_id' => $instance->id,
                    'amount_usd' => $licenseAmount,
                    'kind' => WhiteLabelLicensePayment::KIND_INITIAL,
                    'payment_reference' => $ref,
                ]);
                $this->earnings->accrue($licenseAmount, 'white_label_license', 'plat-earn:'.$ref, 'White-label license — '.$instance->brand_name);

                if ($addonAmount > 0) {
                    $addonRef = $ref.':theme-addon';
                    WhiteLabelLicensePayment::create([
                        'white_label_instance_id' => $instance->id,
                        'amount_usd' => $addonAmount,
                        'kind' => WhiteLabelLicensePayment::KIND_THEME_ADDON,
                        'payment_reference' => $addonRef,
                    ]);
                    $this->earnings->accrue($addonAmount, 'white_label_theme_addon', 'plat-earn:'.$addonRef, 'Custom theme add-on ('.ThemeAddonCatalog::labelFor($instance->theme_addon).') — '.$instance->brand_name);
                }

                $this->checkResellAutoClose();

                return $instance->fresh();
            }, ['reference' => $ref, 'description' => 'White-label license: '.$instance->brand_name]);
        } catch (InsufficientBalanceException $e) {
            $instance->forceFill(['status' => WhiteLabelInstance::APPROVED_AWAITING_FUNDS])->save();

            Auditor::log('white_label.approval_awaiting_funds', WhiteLabelInstance::class, $instance->id, [
                'brand' => $instance->brand_name,
                'shortfall' => round($e->requested - $e->available, 2),
            ]);

            Mailer::notify($payer, new WhiteLabelApprovalFailedNotification($instance->brand_name, round($e->requested - $e->available, 2)));
            $this->notifyAdmins(new WhiteLabelApprovalNeedsRetryNotification($instance->brand_name));

            throw $e;
        }

        Mailer::notify($payer, new WhiteLabelActivatedNotification($instance->brand_name));

        return $activated;
    }

    /**
     * Prompt 21-EXT §3.3 — the balance-completion payment for a `normal`-tier
     * self-service instance. A SECOND, independent WalletService::charge()
     * from the first — same refund-on-failure guarantee. On success, upgrades
     * the tier via upgradeTier() (non-destructive — see that method's own
     * docblock), never issueLicense() (which would kill the fork's live
     * token). Amount is always the true remaining balance against the active
     * Extended plan's current price, never a stale/typed figure.
     *
     * @throws \App\Exceptions\InsufficientBalanceException
     */
    public function payBalanceAndUpgrade(WhiteLabelInstance $instance, User $payer, float $amount): WhiteLabelInstance
    {
        if (! $instance->hasLiveLicense() || $instance->tier !== WhiteLabelInstance::TIER_NORMAL) {
            throw new LicenseActivationException('not_upgradeable');
        }

        $ref = 'wl-license:'.$instance->id.':balance:'.now()->timestamp;

        return $this->wallet->charge($payer, $amount, 'USD', function () use ($instance, $amount, $ref) {
            $this->upgradeTier($instance, WhiteLabelInstance::TIER_EXTENDED);
            $this->clearUpgradeProposal($instance);

            WhiteLabelLicensePayment::create([
                'white_label_instance_id' => $instance->id,
                'amount_usd' => $amount,
                'kind' => WhiteLabelLicensePayment::KIND_BALANCE_COMPLETION,
                'payment_reference' => $ref,
            ]);
            $this->earnings->accrue($amount, 'white_label_license', 'plat-earn:'.$ref, 'White-label license balance — '.$instance->brand_name);
            $this->checkResellAutoClose();

            return $instance->fresh();
        }, ['reference' => $ref, 'description' => 'White-label license balance: '.$instance->brand_name]);
    }

    /**
     * Upgrade to Extended after the balance arrived OUTSIDE the wallet (a reviewed bank or stablecoin transfer, Funnel Phase 7). Same effects as payBalanceAndUpgrade()
     * (non-destructive tier change, proposal cleared, balance payment recorded, platform earning accrued) with no wallet debit. Idempotent per reference.
     */
    public function completeBalanceExternally(WhiteLabelInstance $instance, float $amountUsd, float $netBalanceUsd, string $ref): WhiteLabelInstance
    {
        if (WhiteLabelLicensePayment::query()->where('payment_reference', $ref)->exists()) {
            return $instance->fresh();   // already applied: never twice
        }
        if (! $instance->hasLiveLicense() || $instance->tier !== WhiteLabelInstance::TIER_NORMAL) {
            throw new LicenseActivationException('not_upgradeable');
        }

        return DB::transaction(function () use ($instance, $amountUsd, $netBalanceUsd, $ref) {
            $this->upgradeTier($instance, WhiteLabelInstance::TIER_EXTENDED);
            $this->clearUpgradeProposal($instance);
            WhiteLabelLicensePayment::create(['white_label_instance_id' => $instance->id, 'amount_usd' => $amountUsd, 'kind' => WhiteLabelLicensePayment::KIND_BALANCE_COMPLETION, 'payment_reference' => $ref]);
            $this->earnings->accrue(min($amountUsd, $netBalanceUsd), 'white_label_license', 'plat-earn:'.$ref, 'White-label license balance — '.$instance->brand_name);
            $this->checkResellAutoClose();

            return $instance->fresh();
        });
    }

    /**
     * Content/billing/hot-menu blueprint Phase F2 — the merchant's side of
     * the Basic-to-Extended INSTALLMENT path (as opposed to
     * payBalanceAndUpgrade()'s immediate lump-sum payment). Records the
     * proposal and its balance, but the proposed timeframe is NOT the
     * enforced deadline — an admin still has to confirm one via
     * setUpgradeDeadline() before anything is ever charged. Notifies every
     * admin that a proposal needs a deadline.
     */
    public function proposeUpgrade(WhiteLabelInstance $instance, int $proposedDays): WhiteLabelInstance
    {
        $balance = $instance->balanceToExtended();
        if ($balance === null || $balance <= 0) {
            throw new LicenseActivationException('not_upgradeable');
        }

        $extended = WhiteLabelLicensePlan::where('tier', WhiteLabelInstance::TIER_EXTENDED)
            ->where('is_active', true)->orderBy('price_usd')->first();

        $instance->forceFill([
            'upgrade_target_plan_id' => $extended?->id,
            'upgrade_balance_due' => $balance,
            'upgrade_proposed_days' => max(1, $proposedDays),
            'upgrade_deadline_at' => null,
            'upgrade_notice_sent_at' => null,
        ])->save();

        Auditor::log('white_label.upgrade_proposed', WhiteLabelInstance::class, $instance->id, [
            'brand' => $instance->brand_name, 'balance_due' => $balance, 'proposed_days' => $proposedDays,
        ]);

        $this->notifyAdmins(new WhiteLabelUpgradeProposedNotification($instance->brand_name, max(1, $proposedDays), $balance));

        return $instance;
    }

    /**
     * Admin confirms the ACTUAL enforced deadline for a proposed installment
     * upgrade — the merchant's proposed timeframe is never unilaterally
     * binding (consistent with admin retaining control over financial terms
     * throughout this engagement's design). Notifies the merchant.
     */
    public function setUpgradeDeadline(WhiteLabelInstance $instance, \DateTimeInterface $deadline, ?int $adminId = null): WhiteLabelInstance
    {
        if (! $instance->hasPendingUpgradeProposal()) {
            throw new LicenseActivationException('no_upgrade_proposal');
        }

        $instance->forceFill(['upgrade_deadline_at' => $deadline, 'upgrade_notice_sent_at' => null])->save();

        Auditor::log('white_label.upgrade_deadline_set', WhiteLabelInstance::class, $instance->id, [
            'brand' => $instance->brand_name, 'deadline' => $deadline, 'set_by' => $adminId,
        ]);

        if ($instance->owner) {
            Mailer::notify($instance->owner, new WhiteLabelUpgradeDeadlineSetNotification(
                $instance->brand_name, (float) $instance->upgrade_balance_due, $deadline,
            ));
        }

        return $instance;
    }

    /** Clear a pending/confirmed installment proposal — called on success (upgraded via any path) or a missed deadline. */
    private function clearUpgradeProposal(WhiteLabelInstance $instance): void
    {
        $instance->forceFill([
            'upgrade_target_plan_id' => null,
            'upgrade_balance_due' => null,
            'upgrade_proposed_days' => null,
            'upgrade_deadline_at' => null,
            'upgrade_notice_sent_at' => null,
        ])->save();
    }

    /**
     * Content/billing/hot-menu blueprint Phase F2 — attempt the confirmed
     * installment charge (called by the scheduled command once due). On
     * success, upgrades to Extended exactly like payBalanceAndUpgrade().
     * On insufficient funds, owner decision: revert to standard Basic terms
     * (clear the proposal, no suspension, no auto-extension) rather than
     * silently leaving it stuck or guessing at a grace period.
     *
     * @throws InsufficientBalanceException
     */
    public function attemptScheduledUpgradeCharge(WhiteLabelInstance $instance): WhiteLabelInstance
    {
        $payer = $instance->owner;
        $amount = (float) $instance->upgrade_balance_due;

        if ($payer === null || $amount <= 0 || ! $instance->hasLiveLicense() || $instance->tier !== WhiteLabelInstance::TIER_NORMAL) {
            throw new LicenseActivationException('not_upgradeable');
        }

        $ref = 'wl-license:'.$instance->id.':installment:'.Carbon::parse($instance->upgrade_deadline_at)->format('Y-m-d');

        try {
            $result = $this->wallet->charge($payer, $amount, 'USD', function () use ($instance, $amount, $ref) {
                $this->upgradeTier($instance, WhiteLabelInstance::TIER_EXTENDED);
                $this->clearUpgradeProposal($instance);

                WhiteLabelLicensePayment::create([
                    'white_label_instance_id' => $instance->id,
                    'amount_usd' => $amount,
                    'kind' => WhiteLabelLicensePayment::KIND_BALANCE_COMPLETION,
                    'payment_reference' => $ref,
                ]);
                $this->earnings->accrue($amount, 'white_label_license', 'plat-earn:'.$ref, 'White-label license installment — '.$instance->brand_name);
                $this->checkResellAutoClose();

                return $instance->fresh();
            }, ['reference' => $ref, 'description' => 'White-label license installment: '.$instance->brand_name]);
        } catch (InsufficientBalanceException $e) {
            $this->clearUpgradeProposal($instance);

            Auditor::log('white_label.upgrade_deadline_missed', WhiteLabelInstance::class, $instance->id, ['brand' => $instance->brand_name]);
            Mailer::notify($payer, new WhiteLabelUpgradeMissedDeadlineNotification($instance->brand_name));

            throw $e;
        }

        Mailer::notify($payer, new WhiteLabelUpgradeSucceededNotification($instance->brand_name));

        return $result;
    }

    /**
     * Raise (or lower) a live instance's feature-entitlement level (Batch 8) —
     * the operator's "mark this Normal fork as paid" action, moving it from
     * `basic` to `standard` (or any admin-chosen level). Purely a feature-lock
     * change: it never touches the token, the key, or the package tier, so the
     * fork keeps working and simply unlocks more features on its next check-in.
     */
    public function setEntitlementLevel(WhiteLabelInstance $instance, string $level): WhiteLabelInstance
    {
        $level = in_array($level, WhiteLabelInstance::LEVELS, true) ? $level : WhiteLabelInstance::LEVEL_BASIC;

        $instance->forceFill(['entitlement_level' => $level])->save();

        Auditor::log('white_label.entitlement_level_set', WhiteLabelInstance::class, $instance->id, [
            'brand' => $instance->brand_name,
            'entitlement_level' => $level,
        ]);

        return $instance;
    }

    /**
     * Exchange a license key for a fresh API token (the deployed fork's activate
     * call). Validates the key against a live license on an ACTIVE instance, then
     * mints a token carrying the full scope set, recording only its last four.
     * The plaintext token is returned once.
     *
     * @param  array{brand_name?:string,contact_email?:string,current_platform_version?:string,domain?:string}  $forkDetails
     * @return array{instance: WhiteLabelInstance, token: string}
     *
     * @throws LicenseActivationException on any unusable-key condition (generic
     *   message, specific reason logged — never leak which condition to a prober).
     */
    public function activateWithKey(string $licenseKey, array $forkDetails = []): array
    {
        $key = trim($licenseKey);

        // Constant-work lookup: fetch by key, then check state — the generic
        // exception makes "no such key" and "revoked key" indistinguishable outside.
        $instance = $key === '' ? null : WhiteLabelInstance::where('license_key', $key)->first();

        if ($instance === null) {
            throw new LicenseActivationException('unknown_key');
        }
        if ($instance->license_revoked_at !== null) {
            throw new LicenseActivationException('revoked');
        }
        if (! $instance->usable()) {
            throw new LicenseActivationException('inactive_status:'.$instance->status);
        }

        return DB::transaction(function () use ($instance, $forkDetails) {
            // Record the details the fork self-reports at activation (its brand may
            // have been a placeholder when the admin issued the key ahead of sale).
            $update = array_filter([
                'brand_name' => isset($forkDetails['brand_name']) ? $this->cleanName($forkDetails['brand_name']) : null,
                'contact_email' => $forkDetails['contact_email'] ?? null,
                'current_platform_version' => $forkDetails['current_platform_version'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');

            if ($update !== []) {
                $instance->forceFill($update)->save();
            }

            $this->recordActivationDomain($instance, $forkDetails['domain'] ?? null);

            $token = $this->issueToken($instance);

            Auditor::log('white_label.activated', WhiteLabelInstance::class, $instance->id, [
                'brand' => $instance->brand_name,
                'tier' => $instance->tier,
            ]);

            return ['instance' => $instance->fresh(), 'token' => $token];
        });
    }

    /**
     * Oversight blueprint Phase C2 — tamper-evident domain fingerprint. The
     * FIRST activation permanently sets the fingerprint (never overwritten
     * again); every later activation is compared against it, never trusted
     * blindly. A mismatch is flagged for admin, never blocked here — a
     * legitimate re-domain happens (migrations, custom domain swaps), and
     * this is a detection signal, not an access gate.
     */
    private function recordActivationDomain(WhiteLabelInstance $instance, ?string $domain): void
    {
        if ($domain === null || trim($domain) === '') {
            return;
        }

        if ($instance->activation_domain_hash === null) {
            $instance->forceFill([
                'activation_domain' => $domain,
                'activation_domain_hash' => WhiteLabelInstance::hashDomain($domain),
            ])->save();

            return;
        }

        if ($instance->domainMismatches($domain)) {
            // Only alert on a NEW mismatch (first detection, or the domain
            // changed again to yet another one) — never re-alert on every
            // repeat activation/check-in from an already-flagged domain.
            $isNewMismatch = $instance->activation_domain_mismatch_at === null
                || $instance->activation_domain !== $domain;

            $instance->forceFill([
                'activation_domain' => $domain, // latest reported, for admin visibility
                'activation_domain_mismatch_at' => now(),
            ])->save();

            if ($isNewMismatch) {
                Auditor::log('white_label.activation_domain_mismatch', WhiteLabelInstance::class, $instance->id, [
                    'brand' => $instance->brand_name,
                    'original_domain_hash' => $instance->activation_domain_hash,
                    'reported_domain' => $domain,
                ]);
                AlertAdminJob::dispatch(
                    code: 'white_label_domain_mismatch',
                    message: "White-label instance '{$instance->brand_name}' activated from a domain that doesn't match its original fingerprint.",
                    context: ['instance_id' => $instance->id, 'reported_domain' => $domain],
                );
            }

            return;
        }

        // Same domain reactivating — keep the plain-text field current
        // without disturbing the fingerprint or clearing a past mismatch flag.
        if ($instance->activation_domain !== $domain) {
            $instance->forceFill(['activation_domain' => $domain])->save();
        }
    }

    /**
     * Admin-driven direct token issuance — the resilience fallback for a fork that
     * cannot reach the activate endpoint (firewalled/air-gapped), mirroring Batch
     * 5's principle that the manual path must always exist. Requires a live license
     * on an active instance; returns the plaintext token once for the admin to hand
     * over out of band.
     */
    public function issueTokenDirectly(WhiteLabelInstance $instance): string
    {
        if (! $instance->hasLiveLicense() || ! $instance->usable()) {
            throw new LicenseActivationException('not_issuable');
        }

        $token = $this->issueToken($instance);

        Auditor::log('white_label.token_issued_directly', WhiteLabelInstance::class, $instance->id, [
            'brand' => $instance->brand_name,
        ]);

        return $token;
    }

    /** Suspend access temporarily: kill tokens, keep the key live so a restore +
     *  re-activation with the SAME key works. */
    public function suspend(WhiteLabelInstance $instance, ?int $reviewerId = null): void
    {
        $instance->tokens()->delete();
        $instance->forceFill([
            'status' => WhiteLabelInstance::SUSPENDED,
            'api_token_last_four' => null,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ])->save();

        Auditor::log('white_label.suspended', WhiteLabelInstance::class, $instance->id, ['brand' => $instance->brand_name]);
    }

    /** Reverse a suspension. Tokens stay revoked (suspend deleted them) — the fork
     *  re-activates with its still-valid key to get a fresh token. */
    public function restore(WhiteLabelInstance $instance, ?int $reviewerId = null): void
    {
        $instance->forceFill([
            'status' => WhiteLabelInstance::ACTIVE,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ])->save();

        Auditor::log('white_label.restored', WhiteLabelInstance::class, $instance->id, ['brand' => $instance->brand_name]);
    }

    /** Reject a pending registration request. No key is ever issued; kill any
     *  tokens defensively. */
    public function reject(WhiteLabelInstance $instance, ?int $reviewerId = null): void
    {
        $instance->tokens()->delete();
        $instance->forceFill([
            'status' => WhiteLabelInstance::REJECTED,
            'api_token_last_four' => null,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ])->save();

        Auditor::log('white_label.rejected', WhiteLabelInstance::class, $instance->id, ['brand' => $instance->brand_name]);
    }

    /**
     * Permanently revoke the license: the key is dead forever (activateWithKey will
     * refuse it), all tokens die, and the instance is left suspended. Distinct from
     * suspend(), which is reversible with the same key. Use when a subscription
     * ends or a key is compromised beyond re-issue.
     */
    public function revokeLicense(WhiteLabelInstance $instance, ?int $reviewerId = null): void
    {
        $instance->tokens()->delete();
        $instance->forceFill([
            'status' => WhiteLabelInstance::SUSPENDED,
            'license_revoked_at' => now(),
            'api_token_last_four' => null,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ])->save();

        Auditor::log('white_label.license_revoked', WhiteLabelInstance::class, $instance->id, ['brand' => $instance->brand_name]);
    }

    /** Mint a Sanctum token carrying the full scope set and record its last four.
     *  The plaintext token (format {id}|{secret}) is returned once. */
    private function issueToken(WhiteLabelInstance $instance): string
    {
        $instance->tokens()->delete();

        $plain = $instance->createToken($instance->slug ?: 'white-label', WhiteLabelInstance::SCOPES)->plainTextToken;
        $instance->forceFill(['api_token_last_four' => substr($plain, -4)])->save();

        return $plain;
    }

    /** Generate a human-transcribable key NAARA-XXXX-XXXX-XXXX over an
     *  unambiguous alphabet (no 0/O/1/I/L), unique across the table. */
    private function generateUniqueKey(): string
    {
        do {
            $key = 'NAARA-'.$this->block().'-'.$this->block().'-'.$this->block();
        } while (WhiteLabelInstance::where('license_key', $key)->exists());

        return $key;
    }

    private function block(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // 30 chars, no 0/O/1/I/L
        $out = '';
        for ($i = 0; $i < 4; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    }

    /**
     * Prompt 21-EXT §6.3 — after a self-service sale actually lands (license
     * issued or upgraded, never at request time), check whether a resell
     * threshold was just crossed and auto-close it. Plain counts against
     * existing WhiteLabelInstance rows (§0.8) — no new counter column, no
     * scheduled job. An admin can always manually flip a flag back to true
     * regardless of how it closed (Setting is the single source of truth).
     */
    private function checkResellAutoClose(): void
    {
        if (WhiteLabelLicensePlan::soldCountForTier(WhiteLabelInstance::TIER_NORMAL)
            >= WhiteLabelLicensePlan::thresholdForTier(WhiteLabelInstance::TIER_NORMAL)) {
            Setting::setValue(WhiteLabelLicensePlan::SETTING_NORMAL_OPEN, false);
        }

        if (WhiteLabelLicensePlan::soldCountForTier(WhiteLabelInstance::TIER_EXTENDED)
            >= WhiteLabelLicensePlan::thresholdForTier(WhiteLabelInstance::TIER_EXTENDED)) {
            Setting::setValue(WhiteLabelLicensePlan::SETTING_NORMAL_OPEN, false);
            Setting::setValue(WhiteLabelLicensePlan::SETTING_EXTENDED_OPEN, false);
        }
    }

    /**
     * Fan a notification out to every admin/super_admin — best-effort, like
     * every other transactional mail here: a missing role (an incomplete
     * install, or a test that never seeded roles) must never break the real
     * action that triggered it.
     */
    private function notifyAdmins(\Illuminate\Notifications\Notification $notification): void
    {
        try {
            $admins = User::role(['super_admin', 'admin'])->get();
        } catch (\Throwable) {
            return;
        }

        foreach ($admins as $admin) {
            Mailer::notify($admin, $notification);
        }
    }

    private function normalizeTier(string $tier): string
    {
        $tier = strtolower(trim($tier));
        if (! in_array($tier, WhiteLabelInstance::TIERS, true)) {
            Log::warning('white_label.unknown_tier_defaulted', ['given' => $tier]);

            return WhiteLabelInstance::TIER_NORMAL;
        }

        return $tier;
    }

    private function cleanName(string $name): string
    {
        return Str::limit(trim($name) ?: 'White-label brand', 120, '');
    }
}
