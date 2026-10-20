<?php

namespace App\Livewire\Admin;

use App\Exceptions\LicenseActivationException;
use App\Jobs\ProcessWhiteLabelApprovalJob;
use App\Models\DistributedPackage;
use App\Models\PayoutAccount;
use App\Models\Setting;
use App\Models\WhiteLabelApiLog;
use App\Models\WhiteLabelGuideLink;
use App\Models\WhiteLabelInstance;
use App\Models\WhiteLabelLicensePlan;
use App\Models\WhiteLabelProjectIntake;
use App\Services\Payouts\PayoutException;
use App\Services\Platform\PlatformWithdrawalService;
use App\Services\Updater\PackagePublisher;
use App\Services\Updater\WhiteLabelLicenseService;
use App\Services\Updater\WhiteLabelProjectIntakeException;
use App\Services\Updater\WhiteLabelProjectIntakeService;
use App\Support\Auditor;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → White-Label Oversight (Batch 4 §2) + License Authority (Batch 6). The
 * central view of who is a registered white-label brand, what tier/status they're
 * on, and — per brand — every call their deployed copy has made to the
 * distribution API. Batch 6 adds the brand-onboarding controls: issue a license
 * (mint a key + set a tier + activate), approve/reject a self-serve registration
 * request, suspend/restore/revoke, regenerate a leaked key, and — the firewalled-
 * fork fallback — hand a token directly.
 *
 * super_admin/admin gate, like every other sensitive admin screen. A freshly
 * minted license key is shown in a one-time banner: it's the credential handed to
 * the buyer, and while it's not a bearer token, there's no reason to keep echoing
 * it after issuance. A directly-issued API token IS a bearer credential and is
 * shown once, never persisted in view state beyond the immediate reveal.
 */
#[Layout('components.layouts.admin')]
/**
 * BOUNDARY: master-only. This class issues/oversees/distributes across
 * white-label instances. It must NEVER exist in a white-label build
 * (normal or Extended) — a child is a consumer + a leaf puller, never a
 * hub. See docs/architecture/WHITE-LABEL-LICENSE-BOUNDARY.md. CI fitness
 * tests in the child repos fail the build if this reappears there.
 */
class WhiteLabelRegistry extends Component
{
    use WithFileUploads;

    /** The brand whose API-call history is being drilled into, if any. */
    public ?int $selectedInstanceId = null;

    /** Oversight blueprint Phase C1 — brand/email/activation-domain filter
     *  for the Registered Instances list below. */
    public string $instanceSearch = '';

    // Issue-license form.
    public string $newBrand = '';

    public string $newEmail = '';

    public string $newTier = WhiteLabelInstance::TIER_NORMAL;

    // Prompt 21-EXT §3.1 — per-row price input for a pending self-service
    // request, pre-filled from its chosen plan the moment the row is opened.
    public array $priceInputs = [];

    /** Content/billing/hot-menu blueprint Phase F2 — per-instance deadline input (Y-m-d), keyed by instance id. */
    public array $deadlineInputs = [];

    // Prompt 21-EXT2 §5 — per-intake deploy-timeline day input, and which
    // instance's intake detail panel is currently expanded.
    public array $deployDaysInputs = [];

    public ?int $expandedIntakeInstanceId = null;

    // Prompt 21-EXT §1.4/§6.5 — plan create/edit form.
    public ?int $editingPlanId = null;

    public string $planName = '';

    public string $planTagline = '';

    public string $planDescription = '';

    public string $planPrice = '';

    public string $planTier = WhiteLabelInstance::TIER_NORMAL;

    public string $planSupportLevel = WhiteLabelLicensePlan::SUPPORT_STANDARD;

    public string $planFeaturesText = '';

    public int $planSortOrder = 0;

    public bool $planIsActive = true;

    public $planCoverUpload = null;

    // Owner request (2026-09-15) — merchant guide reference-link create/edit
    // form (Admin → White Label → Guide Links).
    public ?int $editingGuideLinkId = null;

    public string $guideLinkCategory = WhiteLabelGuideLink::CATEGORY_DOMAIN;

    public string $guideLinkLabel = '';

    public string $guideLinkUrl = '';

    public string $guideLinkDescription = '';

    public int $guideLinkSortOrder = 0;

    public bool $guideLinkIsActive = true;

    // Prompt 21-EXT §5.3/§5.5 — platform earnings withdrawal (super_admin only,
    // to the acting admin's OWN verified payout account — same shape as the
    // merchant withdrawal form).
    public ?int $platformAccountId = null;

    public $platformAmountUsd = '';

    public ?string $platformWithdrawError = null;

    // One-time credential reveal (cleared on the next action / dismiss).
    public ?int $revealedForId = null;

    public ?string $revealedKey = null;

    public ?string $revealedToken = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->platformAccountId = PayoutAccount::where('user_id', Auth::id())
            ->where('is_verified', true)->where('is_default', true)->value('id');
    }

    private function guard(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function getApiEnabledProperty(): bool
    {
        return (bool) Setting::getValue('white_label_api.enabled', false);
    }

    public function getTierOptionsProperty(): array
    {
        return WhiteLabelInstance::TIERS;
    }

    public function toggleApi(): void
    {
        $this->guard();

        $new = ! $this->apiEnabled;
        Setting::setValue('white_label_api.enabled', $new);
        Auditor::log('white_label_api.toggled', null, null, ['enabled' => $new]);
        $this->dispatch('nx-toast', type: 'success', message: 'White-label API '.($new ? 'enabled' : 'disabled').'.');
    }

    /** Create a brand-new instance and immediately issue it a license + key. */
    public function issueNewLicense(WhiteLabelLicenseService $licenses): void
    {
        $this->guard();

        $data = $this->validate([
            'newBrand' => ['required', 'string', 'max:120'],
            'newEmail' => ['required', 'email', 'max:255'],
            'newTier' => ['required', 'in:'.implode(',', WhiteLabelInstance::TIERS)],
        ]);

        $instance = $licenses->register([
            'brand_name' => $data['newBrand'],
            'contact_email' => $data['newEmail'],
        ]);
        $licenses->issueLicense($instance, $data['newTier'], Auth::id());

        $this->reset('newBrand', 'newEmail');
        $this->newTier = WhiteLabelInstance::TIER_NORMAL;
        $this->revealCredential($instance->id, key: $instance->fresh()->license_key);
        $this->dispatch('nx-toast', type: 'success', message: 'License issued for '.$instance->brand_name.'.');
    }

    /** Approve a pending registration request at a chosen tier (issues the key). */
    public function approveInstance(int $id, string $tier, WhiteLabelLicenseService $licenses): void
    {
        $this->guard();

        $instance = WhiteLabelInstance::find($id);
        if ($instance === null) {
            return;
        }

        $licenses->issueLicense($instance, $tier, Auth::id());
        $this->revealCredential($instance->id, key: $instance->fresh()->license_key);
        $this->dispatch('nx-toast', type: 'success', message: 'Approved and licensed.');
    }

    /** Generate a fresh key on an existing instance (a leaked key replacement). */
    public function regenerateKey(int $id, WhiteLabelLicenseService $licenses): void
    {
        $this->guard();

        $instance = WhiteLabelInstance::find($id);
        if ($instance === null) {
            return;
        }

        $licenses->issueLicense($instance, $instance->tier ?? WhiteLabelInstance::TIER_NORMAL, Auth::id());
        $this->revealCredential($instance->id, key: $instance->fresh()->license_key);
        $this->dispatch('nx-toast', type: 'success', message: 'New license key generated. The old key no longer works.');
    }

    /** Direct token issuance — the fallback for a fork that can't reach activate. */
    public function issueTokenFor(int $id, WhiteLabelLicenseService $licenses): void
    {
        $this->guard();

        $instance = WhiteLabelInstance::find($id);
        if ($instance === null) {
            return;
        }

        try {
            $token = $licenses->issueTokenDirectly($instance);
        } catch (LicenseActivationException $e) {
            $this->dispatch('nx-toast', type: 'error', message: 'Cannot issue a token: the instance needs a live license and active status.');

            return;
        }

        $this->revealCredential($instance->id, token: $token);
        $this->dispatch('nx-toast', type: 'success', message: 'API token issued — copy it now, it will not be shown again.');
    }

    /**
     * Prompt 21-EXT §3.1 — set (or override) the price on a pending
     * self-service request. Does NOT issue a license: the merchant pays via
     * their own dashboard, which is what actually activates the instance
     * (payAndActivate). `price_usd` defaults to the chosen plan's price in
     * the Blade view; this action persists whatever the admin confirms.
     */
    public function priceInstance(int $id, WhiteLabelLicenseService $licenses): void
    {
        $this->guard();

        $instance = WhiteLabelInstance::find($id);
        if ($instance === null || $instance->status !== WhiteLabelInstance::PENDING) {
            return;
        }

        $price = (float) ($this->priceInputs[$id] ?? 0);
        if ($price <= 0) {
            $this->dispatch('nx-toast', type: 'error', message: 'Enter a price greater than zero.');

            return;
        }

        $licenses->priceForPayment($instance, $price, Auth::id());
        unset($this->priceInputs[$id]);
        $this->dispatch('nx-toast', type: 'success', message: 'Price set — approve it once the merchant is ready to be charged.');
    }

    /**
     * Content/billing/hot-menu blueprint Phase F1 — the ONLY action that
     * charges a self-service white-label request. Super-admin only (a
     * stricter gate than guard()'s admin+super_admin, matching money-moving
     * actions elsewhere on this screen like withdrawPlatformEarnings()) —
     * this is what actually moves the merchant's money, not just a review
     * note like priceInstance(). Also the retry path for an instance stuck
     * in APPROVED_AWAITING_FUNDS once the merchant has topped up.
     *
     * Oversight/Withdrawal blueprint Phase E3 — the approval DECISION stays
     * synchronous and unchanged (the two precondition checks below are cheap
     * local reads, not the money-moving work); the actual wallet charge +
     * license issuance now run in ProcessWhiteLabelApprovalJob off the
     * request cycle. "System-processed approvals" below is the visibility
     * window into that queue, not a second gate.
     */
    public function approveAndActivate(int $id): void
    {
        abort_unless($this->isSuperAdmin, 403);

        $instance = WhiteLabelInstance::find($id);
        if ($instance === null) {
            return;
        }
        if ($instance->price_usd === null || $instance->hasLiveLicense()) {
            $this->dispatch('nx-toast', type: 'error', message: 'This request is not ready for approval (not priced, or already licensed).');

            return;
        }

        $instance->forceFill(['approval_queued_at' => now()])->save();
        ProcessWhiteLabelApprovalJob::dispatch($instance->id, Auth::id());

        Auditor::log('white_label.approval_queued', WhiteLabelInstance::class, $instance->id, ['brand' => $instance->brand_name]);
        $this->dispatch('nx-toast', type: 'success', message: 'Approval queued — '.$instance->brand_name.' will be charged and activated in the background.');
    }

    /** Oversight/Withdrawal blueprint Phase E3 — every self-service instance
     *  that went through the async approval job, most recent first, for the
     *  admin's double-confirmation glance. */
    public function getSystemProcessedApprovalsProperty()
    {
        return WhiteLabelInstance::query()
            ->where('acquisition_method', WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE)
            ->whereNotNull('approval_queued_at')
            ->latest('approval_queued_at')
            ->limit(20)
            ->get();
    }

    /**
     * Content/billing/hot-menu blueprint Phase F2 — confirm the ENFORCED
     * deadline for a merchant's proposed Basic-to-Extended installment
     * upgrade. The merchant's proposed timeframe is never unilaterally
     * binding; only this admin-confirmed date is ever scheduled to charge.
     */
    public function setUpgradeDeadline(int $id, WhiteLabelLicenseService $licenses): void
    {
        abort_unless($this->isSuperAdmin, 403);

        $instance = WhiteLabelInstance::find($id);
        if ($instance === null) {
            return;
        }

        $date = $this->deadlineInputs[$id] ?? null;
        if (! $date) {
            $this->dispatch('nx-toast', type: 'error', message: 'Choose a deadline date.');

            return;
        }

        try {
            $licenses->setUpgradeDeadline($instance, \Illuminate\Support\Carbon::parse($date), Auth::id());
        } catch (LicenseActivationException) {
            $this->dispatch('nx-toast', type: 'error', message: 'This instance has no pending upgrade proposal.');

            return;
        }

        unset($this->deadlineInputs[$id]);
        $this->dispatch('nx-toast', type: 'success', message: 'Upgrade deadline confirmed — '.$instance->brand_name.'.');
    }

    // --- Prompt 21-EXT2 §5: project intake review ---

    public function toggleIntakeDetail(int $instanceId): void
    {
        $this->expandedIntakeInstanceId = $this->expandedIntakeInstanceId === $instanceId ? null : $instanceId;
    }

    public function markIntakeSeen(int $intakeId, WhiteLabelProjectIntakeService $intakes): void
    {
        $this->guard();
        $intake = WhiteLabelProjectIntake::find($intakeId);
        if ($intake === null) {
            return;
        }

        $intakes->markSeen($intake, Auth::id());
        $this->dispatch('nx-toast', type: 'success', message: 'Marked as seen.');
    }

    public function setIntakeDeployTimeline(int $intakeId, WhiteLabelProjectIntakeService $intakes): void
    {
        $this->guard();
        $intake = WhiteLabelProjectIntake::find($intakeId);
        if ($intake === null) {
            return;
        }

        $days = (int) ($this->deployDaysInputs[$intakeId] ?? 0);

        try {
            $intakes->setDeployTimeline($intake, $days);
        } catch (WhiteLabelProjectIntakeException $e) {
            $this->dispatch('nx-toast', type: 'error', message: $e->getMessage() === 'not_seen_yet'
                ? 'Mark this intake as seen before setting a deploy timeline.'
                : 'Enter a valid number of days.');

            return;
        }

        unset($this->deployDaysInputs[$intakeId]);
        $this->dispatch('nx-toast', type: 'success', message: 'Deploy timeline set — '.$days.' day(s), starting now.');
    }

    public function getIntakeProgressProperty(): \Closure
    {
        $service = app(WhiteLabelProjectIntakeService::class);

        return fn (WhiteLabelProjectIntake $intake) => $service->progressPercent($intake);
    }

    public function getIntakeDayOfProperty(): \Closure
    {
        $service = app(WhiteLabelProjectIntakeService::class);

        return fn (WhiteLabelProjectIntake $intake) => $service->dayOf($intake);
    }

    // --- Prompt 21-EXT §1.4/§6.5: plan catalog + resell-status management ---

    public function getLicensePlansProperty()
    {
        return WhiteLabelLicensePlan::orderBy('sort_order')->get();
    }

    /** Live counts against each resell-status threshold (§6.5), read from
     *  the SAME query the auto-close check itself uses — never a second,
     *  possibly-drifting count. */
    public function getResellStatusProperty(): array
    {
        return [
            WhiteLabelInstance::TIER_NORMAL => [
                'open' => WhiteLabelLicensePlan::resellOpenForTier(WhiteLabelInstance::TIER_NORMAL),
                'count' => WhiteLabelLicensePlan::soldCountForTier(WhiteLabelInstance::TIER_NORMAL),
                'threshold' => WhiteLabelLicensePlan::thresholdForTier(WhiteLabelInstance::TIER_NORMAL),
            ],
            WhiteLabelInstance::TIER_EXTENDED => [
                'open' => WhiteLabelLicensePlan::resellOpenForTier(WhiteLabelInstance::TIER_EXTENDED),
                'count' => WhiteLabelLicensePlan::soldCountForTier(WhiteLabelInstance::TIER_EXTENDED),
                'threshold' => WhiteLabelLicensePlan::thresholdForTier(WhiteLabelInstance::TIER_EXTENDED),
            ],
        ];
    }

    /** Manually flip a tier's resell status — works regardless of whether it
     *  was closed by an admin or by the auto-close threshold (Setting is the
     *  single source of truth, no separate "why closed" state to keep in sync). */
    public function toggleResell(string $tier): void
    {
        $this->guard();
        abort_unless(in_array($tier, WhiteLabelInstance::TIERS, true), 404);

        $key = $tier === WhiteLabelInstance::TIER_EXTENDED
            ? WhiteLabelLicensePlan::SETTING_EXTENDED_OPEN
            : WhiteLabelLicensePlan::SETTING_NORMAL_OPEN;
        $next = ! WhiteLabelLicensePlan::resellOpenForTier($tier);

        Setting::setValue($key, $next);
        Auditor::log('white_label.resell_status_toggled', null, null, ['tier' => $tier, 'open' => $next]);
        $this->dispatch('nx-toast', type: 'success', message: ucfirst($tier).' resell is now '.($next ? 'open' : 'closed').'.');
    }

    /** Raise or lower a tier's auto-close sale ceiling (Phase D) — no code
     *  change needed, matches the same Setting-backed pattern as the
     *  open/closed flags right next to it. */
    public function setResellThreshold(string $tier, int $threshold): void
    {
        $this->guard();
        abort_unless(in_array($tier, WhiteLabelInstance::TIERS, true), 404);
        abort_if($threshold < 1, 422, 'The threshold must be at least 1.');

        $key = $tier === WhiteLabelInstance::TIER_EXTENDED
            ? WhiteLabelLicensePlan::SETTING_EXTENDED_THRESHOLD
            : WhiteLabelLicensePlan::SETTING_NORMAL_THRESHOLD;

        Setting::setValue($key, $threshold);
        Auditor::log('white_label.resell_threshold_updated', null, null, ['tier' => $tier, 'threshold' => $threshold]);
        $this->dispatch('nx-toast', type: 'success', message: ucfirst($tier).' resell ceiling set to '.number_format($threshold).'.');
    }

    public function newPlanForm(): void
    {
        $this->guard();
        $this->resetPlanForm();
    }

    public function editPlan(int $id): void
    {
        $this->guard();
        $plan = WhiteLabelLicensePlan::findOrFail($id);

        $this->editingPlanId = $plan->id;
        $this->planName = $plan->name;
        $this->planTagline = (string) $plan->tagline;
        $this->planDescription = (string) $plan->description;
        $this->planPrice = (string) $plan->price_usd;
        $this->planTier = $plan->tier;
        $this->planSupportLevel = $plan->support_level;
        $this->planFeaturesText = implode("\n", $plan->features ?? []);
        $this->planSortOrder = $plan->sort_order;
        $this->planIsActive = $plan->is_active;
        $this->planCoverUpload = null;
    }

    /** Create or update a plan. `key` is set once at creation and never
     *  re-typed here — a plan is looked up by its own id everywhere else in
     *  the app, so renaming a plan can never orphan/duplicate the seeded
     *  catalog's canonical keys. */
    public function savePlan(): void
    {
        $this->guard();

        $data = $this->validate([
            'planName' => ['required', 'string', 'max:120'],
            'planTagline' => ['nullable', 'string', 'max:200'],
            'planDescription' => ['required', 'string', 'max:2000'],
            'planPrice' => ['required', 'numeric', 'min:0.01'],
            'planTier' => ['required', 'in:'.implode(',', WhiteLabelInstance::TIERS)],
            'planSupportLevel' => ['required', 'in:'.WhiteLabelLicensePlan::SUPPORT_STANDARD.','.WhiteLabelLicensePlan::SUPPORT_PRIORITY],
            'planSortOrder' => ['nullable', 'integer', 'min:0'],
            'planCoverUpload' => ['nullable', 'image', 'mimes:webp,jpg,jpeg,png', 'max:2048'],
        ]);

        $features = array_values(array_filter(array_map('trim', explode("\n", $this->planFeaturesText))));

        $payload = [
            'name' => $data['planName'],
            'tagline' => $data['planTagline'] ?: null,
            'description' => $data['planDescription'],
            'price_usd' => $data['planPrice'],
            'tier' => $data['planTier'],
            'support_level' => $data['planSupportLevel'],
            'features' => $features,
            'sort_order' => $data['planSortOrder'] ?? 0,
            'is_active' => $this->planIsActive,
        ];

        if ($this->planCoverUpload) {
            $payload['cover_image_url'] = MediaStorage::storePublic($this->planCoverUpload, 'white-label-plans');
        }

        if ($this->editingPlanId) {
            $plan = WhiteLabelLicensePlan::findOrFail($this->editingPlanId);
            $plan->update($payload);
        } else {
            $plan = WhiteLabelLicensePlan::create($payload + [
                'key' => \Illuminate\Support\Str::slug($data['planName']).'-'.\Illuminate\Support\Str::random(4),
            ]);
        }

        Auditor::log('white_label.plan_saved', WhiteLabelLicensePlan::class, $plan->id, ['name' => $plan->name]);
        $this->resetPlanForm();
        $this->dispatch('nx-toast', type: 'success', message: 'Plan saved.');
    }

    public function togglePlanActive(int $id): void
    {
        $this->guard();
        $plan = WhiteLabelLicensePlan::findOrFail($id);
        $plan->update(['is_active' => ! $plan->is_active]);
        $this->dispatch('nx-toast', type: 'success', message: $plan->name.' is now '.($plan->is_active ? 'active' : 'inactive').'.');
    }

    private function resetPlanForm(): void
    {
        $this->reset('editingPlanId', 'planName', 'planTagline', 'planDescription', 'planPrice', 'planFeaturesText', 'planCoverUpload');
        $this->planTier = WhiteLabelInstance::TIER_NORMAL;
        $this->planSupportLevel = WhiteLabelLicensePlan::SUPPORT_STANDARD;
        $this->planIsActive = true;
        $this->planSortOrder = 0;
    }

    // --- Owner request (2026-09-15): merchant guide reference links ---

    public function getGuideLinksProperty()
    {
        return WhiteLabelGuideLink::query()->orderBy('category')->orderBy('sort_order')->get();
    }

    public function getGuideLinkCategoriesProperty(): array
    {
        return WhiteLabelGuideLink::CATEGORY_LABELS;
    }

    public function newGuideLinkForm(): void
    {
        $this->guard();
        $this->resetGuideLinkForm();
    }

    public function editGuideLink(int $id): void
    {
        $this->guard();
        $link = WhiteLabelGuideLink::findOrFail($id);

        $this->editingGuideLinkId = $link->id;
        $this->guideLinkCategory = $link->category;
        $this->guideLinkLabel = $link->label;
        $this->guideLinkUrl = $link->url;
        $this->guideLinkDescription = (string) $link->description;
        $this->guideLinkSortOrder = $link->sort_order;
        $this->guideLinkIsActive = $link->is_active;
    }

    /** Create or update a guide reference link. This is the only place an
     *  admin needs to touch to point merchants at a different provider, or
     *  swap a plain URL for their own affiliate link — no code change. */
    public function saveGuideLink(): void
    {
        $this->guard();

        $data = $this->validate([
            'guideLinkCategory' => ['required', 'in:'.implode(',', array_keys(WhiteLabelGuideLink::CATEGORY_LABELS))],
            'guideLinkLabel' => ['required', 'string', 'max:150'],
            'guideLinkUrl' => ['required', 'url', 'max:2048'],
            'guideLinkDescription' => ['nullable', 'string', 'max:255'],
            'guideLinkSortOrder' => ['nullable', 'integer', 'min:0'],
        ]);

        $payload = [
            'category' => $data['guideLinkCategory'],
            'label' => $data['guideLinkLabel'],
            'url' => $data['guideLinkUrl'],
            'description' => $data['guideLinkDescription'] ?: null,
            'sort_order' => $data['guideLinkSortOrder'] ?? 0,
            'is_active' => $this->guideLinkIsActive,
        ];

        if ($this->editingGuideLinkId) {
            $link = WhiteLabelGuideLink::findOrFail($this->editingGuideLinkId);
            $link->update($payload);
        } else {
            $link = WhiteLabelGuideLink::create($payload);
        }

        Auditor::log('white_label.guide_link_saved', WhiteLabelGuideLink::class, $link->id, ['label' => $link->label]);
        $this->resetGuideLinkForm();
        $this->dispatch('nx-toast', type: 'success', message: 'Guide link saved.');
    }

    public function toggleGuideLinkActive(int $id): void
    {
        $this->guard();
        $link = WhiteLabelGuideLink::findOrFail($id);
        $link->update(['is_active' => ! $link->is_active]);
        $this->dispatch('nx-toast', type: 'success', message: $link->label.' is now '.($link->is_active ? 'visible' : 'hidden').' in the merchant guide.');
    }

    public function deleteGuideLink(int $id): void
    {
        $this->guard();
        $link = WhiteLabelGuideLink::find($id);
        if ($link !== null) {
            $link->delete();
            $this->dispatch('nx-toast', type: 'success', message: 'Guide link removed.');
        }
    }

    private function resetGuideLinkForm(): void
    {
        $this->reset('editingGuideLinkId', 'guideLinkLabel', 'guideLinkUrl', 'guideLinkDescription');
        $this->guideLinkCategory = WhiteLabelGuideLink::CATEGORY_DOMAIN;
        $this->guideLinkSortOrder = 0;
        $this->guideLinkIsActive = true;
    }

    // --- Prompt 21-EXT §5.3/§5.5: platform earnings withdrawal ---

    public function getIsSuperAdminProperty(): bool
    {
        return Auth::user()->hasRole('super_admin');
    }

    /** Oversight/Withdrawal blueprint Phase B — the TOTAL live withdrawable
     *  figure (core-business margin net of staff/partner/merchant/referral
     *  shares, PLUS 100% White-Label revenue), not just the WL ledger. */
    public function getPlatformBalanceProperty(): float
    {
        return app(PlatformWithdrawalService::class)->availableUsd();
    }

    /** Every term of that formula, for the transparency panel. */
    public function getPlatformBalanceBreakdownProperty(): array
    {
        return app(PlatformWithdrawalService::class)->breakdown();
    }

    public function getPlatformAccountsProperty()
    {
        return PayoutAccount::where('user_id', Auth::id())->where('is_verified', true)->get();
    }

    /** Cash out the global platform-earnings balance to the acting admin's
     *  OWN verified payout account — reuses PayoutService exactly like a
     *  merchant withdrawal does, per the owner's own framing ("admin can
     *  withdraw this the way normal users withdraw funds"). */
    public function withdrawPlatformEarnings(PlatformWithdrawalService $withdrawals): void
    {
        $this->platformWithdrawError = null;
        abort_unless($this->isSuperAdmin, 403);

        $this->validate([
            'platformAccountId' => ['required', 'integer'],
            'platformAmountUsd' => ['required', 'numeric', 'min:0.01'],
        ]);

        $account = PayoutAccount::where('user_id', Auth::id())->find($this->platformAccountId);
        if ($account === null) {
            $this->platformWithdrawError = 'Choose a verified payout account.';

            return;
        }

        try {
            $withdrawals->request(Auth::user(), $account, (float) $this->platformAmountUsd);
        } catch (PayoutException $e) {
            $this->platformWithdrawError = $e->getMessage();

            return;
        }

        $this->reset('platformAmountUsd');
        $this->dispatch('nx-toast', type: 'success', message: 'Withdrawal requested — we\'ll process it shortly.');
    }

    public function rejectInstance(int $id, WhiteLabelLicenseService $licenses): void
    {
        $this->guard();
        $instance = WhiteLabelInstance::find($id);
        if ($instance !== null) {
            $licenses->reject($instance, Auth::id());
            $this->clearReveal();
            $this->dispatch('nx-toast', type: 'success', message: 'Registration rejected.');
        }
    }

    public function suspendInstance(int $id, WhiteLabelLicenseService $licenses): void
    {
        $this->guard();
        $instance = WhiteLabelInstance::find($id);
        if ($instance !== null) {
            $licenses->suspend($instance, Auth::id());
            $this->clearReveal();
            $this->dispatch('nx-toast', type: 'success', message: 'Instance suspended — its token has been revoked.');
        }
    }

    public function restoreInstance(int $id, WhiteLabelLicenseService $licenses): void
    {
        $this->guard();
        $instance = WhiteLabelInstance::find($id);
        if ($instance !== null) {
            $licenses->restore($instance, Auth::id());
            $this->dispatch('nx-toast', type: 'success', message: 'Instance restored. It must re-activate with its key to get a new token.');
        }
    }

    public function revokeLicense(int $id, WhiteLabelLicenseService $licenses): void
    {
        $this->guard();
        $instance = WhiteLabelInstance::find($id);
        if ($instance !== null) {
            $licenses->revokeLicense($instance, Auth::id());
            $this->clearReveal();
            $this->dispatch('nx-toast', type: 'success', message: 'License permanently revoked — the key can never be used again.');
        }
    }

    /**
     * Raise/lower an instance's feature-entitlement level (Batch 8) — the
     * operator's "this Normal fork has paid up" action (basic → standard), or
     * any admin-chosen level. Feature-lock change only; never touches the token,
     * key, or package tier.
     */
    public function setLevel(int $id, string $level, WhiteLabelLicenseService $licenses): void
    {
        $this->guard();
        $instance = WhiteLabelInstance::find($id);
        if ($instance !== null) {
            $licenses->setEntitlementLevel($instance, $level);
            $this->dispatch('nx-toast', type: 'success', message: 'Feature level set to '.$instance->fresh()->entitlement_level.'. The fork unlocks on its next check-in.');
        }
    }

    public function getFeatureCatalogProperty(): array
    {
        return \App\Support\FeatureLocks::catalog();
    }

    public function getFeatureLevelsProperty(): array
    {
        return WhiteLabelInstance::LEVELS;
    }

    public function getFeatureLocksProperty(): array
    {
        return \App\Support\FeatureLocks::all();
    }

    /** Toggle whether one feature is locked at one level (admin config). */
    public function toggleFeatureLock(string $level, string $key): void
    {
        $this->guard();

        if (! in_array($level, WhiteLabelInstance::LEVELS, true) || ! array_key_exists($key, \App\Support\FeatureLocks::catalog())) {
            return;
        }

        $current = \App\Support\FeatureLocks::all()[$level] ?? [];
        $next = in_array($key, $current, true)
            ? array_values(array_diff($current, [$key]))
            : array_values(array_merge($current, [$key]));

        \App\Support\FeatureLocks::saveLevel($level, $next);
        Auditor::log('white_label.feature_lock_toggled', null, null, ['level' => $level, 'key' => $key]);
        $this->dispatch('nx-toast', type: 'success', message: 'Feature locks updated for '.$level.'.');
    }

    /** @return array<string, int> */
    public function getSkinTierDefaultsProperty(): array
    {
        return \App\Support\WhiteLabel\SkinAllowance::tierMap();
    }

    public function getSkinCatalogueSizeProperty(): int
    {
        return \App\Support\WhiteLabel\SkinAllowance::catalogueSize();
    }

    /** Edit the skin allowance each licence TIER unlocks (Prompt 22). Forks pick it up on their next check-in. */
    public function saveSkinTierDefault(string $tier, string $value): void
    {
        $this->guard();
        if (! in_array($tier, WhiteLabelInstance::TIERS, true) || ! ctype_digit(trim($value))) {
            $this->dispatch('nx-toast', type: 'error', message: 'Enter a whole number of skins.');

            return;
        }
        $next = \App\Support\WhiteLabel\SkinAllowance::tierMap();
        $next[$tier] = (int) $value;
        \App\Support\WhiteLabel\SkinAllowance::saveTierDefaults($next);
        Auditor::log('white_label.skin_allowance_set', null, null, ['scope' => 'tier', 'tier' => $tier, 'allowance' => \App\Support\WhiteLabel\SkinAllowance::forTier($tier)]);
        $this->dispatch('nx-toast', type: 'success', message: ucfirst($tier).' licences now unlock '.\App\Support\WhiteLabel\SkinAllowance::forTier($tier).' skin(s).');
    }

    /** Set or clear one instance's own skin allowance (blank = use its tier default). */
    public function setSkinOverride(int $id, string $value): void
    {
        $this->guard();
        $instance = WhiteLabelInstance::find($id);
        if ($instance === null) {
            return;
        }
        $value = trim($value);
        if ($value !== '' && ! ctype_digit($value)) {
            $this->dispatch('nx-toast', type: 'error', message: 'Enter a whole number, or leave blank for the tier default.');

            return;
        }
        $instance->forceFill(['skin_allowance_override' => $value === '' ? null : \App\Support\WhiteLabel\SkinAllowance::clamp((int) $value)])->save();
        Auditor::log('white_label.skin_allowance_set', WhiteLabelInstance::class, $instance->id, ['scope' => 'instance', 'override' => $instance->skin_allowance_override, 'effective' => \App\Support\WhiteLabel\SkinAllowance::forInstance($instance)]);
        $this->dispatch('nx-toast', type: 'success', message: $instance->brand_name.' now unlocks '.\App\Support\WhiteLabel\SkinAllowance::forInstance($instance).' skin(s). It applies on the next check-in.');
    }

    private function revealCredential(int $id, ?string $key = null, ?string $token = null): void
    {
        $this->revealedForId = $id;
        $this->revealedKey = $key;
        $this->revealedToken = $token;
    }

    public function clearReveal(): void
    {
        $this->reset('revealedForId', 'revealedKey', 'revealedToken');
    }

    public function selectInstance(int $id): void
    {
        $this->selectedInstanceId = $this->selectedInstanceId === $id ? null : $id;
    }

    public function togglePublish(int $packageId, PackagePublisher $publisher): void
    {
        $this->guard();

        $package = DistributedPackage::find($packageId);
        if ($package === null) {
            return;
        }

        // Defense in depth: the Blade view never renders a clickable toggle
        // for a master-only package (a locked badge shows instead) — this
        // catch is only for a direct wire:click call that bypasses the UI.
        // setPublished() is the real, server-side gate; this just turns its
        // loud rejection into a clean toast instead of a Livewire error.
        try {
            $publisher->setPublished($package, ! $package->is_published, Auth::id());
        } catch (\RuntimeException $e) {
            $this->dispatch('nx-toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->dispatch('nx-toast', type: 'success', message: 'Package '.($package->fresh()->is_published ? 'published' : 'unpublished').'.');
    }

    public function withdrawPackage(int $packageId, PackagePublisher $publisher): void
    {
        $this->guard();

        $package = DistributedPackage::find($packageId);
        if ($package === null) {
            return;
        }

        $publisher->withdraw($package, Auth::id());
        $this->dispatch('nx-toast', type: 'success', message: 'Package withdrawn from distribution.');
    }

    /** Oversight blueprint Phase C1 — filter by brand, contact email, or the
     *  domain the license is actually activated against. */
    public function getInstancesProperty()
    {
        $search = trim($this->instanceSearch);

        return WhiteLabelInstance::query()
            ->with(['licensePlan', 'intake'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('brand_name', 'like', "%{$search}%")
                        ->orWhere('contact_email', 'like', "%{$search}%")
                        ->orWhere('activation_domain', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->get();
    }

    public function getPackagesProperty()
    {
        return DistributedPackage::query()->latest()->get();
    }

    public function getSelectedLogsProperty()
    {
        if ($this->selectedInstanceId === null) {
            return collect();
        }

        return WhiteLabelApiLog::query()
            ->where('white_label_instance_id', $this->selectedInstanceId)
            ->latest('created_at')
            ->limit(50)
            ->get();
    }

    public function render()
    {
        return view('livewire.admin.white-label-registry');
    }
}
