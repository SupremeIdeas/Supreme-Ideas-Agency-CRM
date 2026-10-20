<?php

namespace App\Livewire\Admin\N2N;

use App\Models\Setting;
use App\Services\N2N\Identity\PhoneLinkService;
use App\Services\N2N\Realtime\RealtimeHealth;
use App\Support\Auditor;
use App\Support\FeatureEntitlements;
use App\Support\N2N\N2NSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin > N2N (MASTER-ONLY): the one home for N2N switches and limits, plus realtime health. Later batches add their own sections here
 * rather than new top-level screens. Every limit is a setting; every change is audited.
 */
#[Layout('components.layouts.admin')]
class Settings extends Component
{
    use WithFileUploads;

    public $logoUpload = null;

    public bool $enabled = false;

    public string $driver = 'poll';

    public int $pollVisibleMs = 3000;

    public int $pollHiddenMs = 30000;

    public int $cooldownDays = 30;

    public string $reserved = '';

    public int $otpPerUserDay = 5;

    public int $otpPerNumberDay = 5;

    public int $otpPerIpDay = 20;

    public int $searchPerMin = 20;

    public int $importMax = 500;

    // Conversation power features (Batch 12)
    public int $maxPinnedChats = 3;

    public int $exportMaxMessages = 5000;

    public string $otpFrom = '';

    // Credit economy (Batch 5)
    public float $priceMessage = 0.5;

    public float $storageUsdPerMb = 0.00001;

    public float $mediaMarkup = 3.0;

    public float $minMarginPct = 30;

    public float $callUsdPerMin = 0.02;

    public float $speakerUsdPerMin = 0.02;

    public float $shareMin = 1;

    public float $shareMax = 500;

    public float $shareCapDay = 2000;

    public float $shareCapRecipient = 1000;

    public int $shareMinAgeDays = 7;

    public int $giftHours = 24;

    public int $giftMaxPerDay = 10;

    public float $purchaseMinUsd = 1;

    public float $purchaseMaxUsd = 500;

    public float $lowBalance = 5;

    public bool $purchaseIos = false;

    public bool $floatingChat = true;

    public string $floatingRoutes = '';

    public int $floatingHours = 24;

    public ?string $saved = null;

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']) && FeatureEntitlements::isMaster(), 403);
        $this->enabled = (bool) Setting::getValue(N2NSettings::FLAG, false);
        $this->driver = \App\Services\N2N\Realtime\Realtime::driver();
        $this->pollVisibleMs = N2NSettings::int('poll_visible_ms', 3000, 1000);
        $this->pollHiddenMs = N2NSettings::int('poll_hidden_ms', 30000, 5000);
        $this->cooldownDays = N2NSettings::usernameCooldownDays();
        $this->reserved = (string) Setting::getValue('n2n.reserved_usernames', '');
        $this->otpPerUserDay = N2NSettings::otpPerUserPerDay();
        $this->otpPerNumberDay = N2NSettings::otpPerNumberPerDay();
        $this->otpPerIpDay = N2NSettings::otpPerIpPerDay();
        $this->searchPerMin = N2NSettings::searchPerMinute();
        $this->importMax = N2NSettings::importMaxContacts();
        $this->maxPinnedChats = N2NSettings::int('max_pinned_chats', 3);
        $this->exportMaxMessages = N2NSettings::int('export_max_messages', 5000, 100);
        $this->otpFrom = (string) (N2NSettings::otpFrom() ?? '');
        $this->priceMessage = (float) Setting::getValue('n2n.price_message', 0.5);
        $this->storageUsdPerMb = \App\Services\N2N\Credit\N2NPricing::storageUsdPerMb();
        $this->mediaMarkup = \App\Services\N2N\Credit\N2NPricing::mediaMarkup();
        $this->minMarginPct = \App\Services\N2N\Credit\N2NPricing::minMarginPct();
        $this->callUsdPerMin = \App\Services\N2N\Credit\N2NPricing::callUsdPerMinute();
        $this->speakerUsdPerMin = \App\Services\N2N\Credit\N2NPricing::speakerUsdPerMinute();
        foreach (['shareMin' => ['share_min', 1], 'shareMax' => ['share_max', 500], 'shareCapDay' => ['share_cap_per_day', 2000], 'shareCapRecipient' => ['share_cap_per_recipient_day', 1000],
            'purchaseMinUsd' => ['purchase_min_usd', 1], 'purchaseMaxUsd' => ['purchase_max_usd', 500], 'lowBalance' => ['low_balance_credits', 5]] as $prop => [$key, $def]) {
            $this->{$prop} = (float) Setting::getValue('n2n.'.$key, $def);
        }
        $this->shareMinAgeDays = (int) Setting::getValue('n2n.share_min_account_days', 7);
        $this->giftHours = (int) Setting::getValue('n2n.gift_hours', 24);
        $this->giftMaxPerDay = (int) Setting::getValue('n2n.gift_max_per_day', 10);
        $this->purchaseIos = (bool) Setting::getValue('n2n.credit_purchase_ios', false);
        $this->floatingChat = (bool) Setting::getValue('n2n.floating_chat', true);
        $this->floatingRoutes = (string) Setting::getValue('n2n.floating_chat_routes', \App\Support\N2N\FloatingChat::DEFAULT_ROUTES);
        $this->floatingHours = \App\Support\N2N\FloatingChat::dismissHours();
    }

    /** Replace the Naara chat logo (shell, rail and quick chat button). Stored through MediaStorage like every other brand upload. */
    public function uploadLogo(): void
    {
        $this->validate(['logoUpload' => 'required|image|mimes:png,jpg,jpeg,webp,svg|max:1024']);
        Setting::setValue('n2n.logo_url', \App\Support\MediaStorage::storePublic($this->logoUpload, 'brand'), 'n2n', 'Naara chat logo (asset path or uploaded URL)');
        $this->logoUpload = null;
        Auditor::log('n2n.logo_changed', Setting::class, null, ['by' => Auth::id()]);
        $this->saved = 'Naara chat logo updated.';
    }

    public function resetLogo(): void
    {
        Setting::setValue('n2n.logo_url', N2NSettings::DEFAULT_LOGO, 'n2n', 'Naara chat logo (asset path or uploaded URL)');
        Auditor::log('n2n.logo_changed', Setting::class, null, ['by' => Auth::id(), 'reset' => true]);
        $this->saved = 'Naara chat logo reset to the shipped mark.';
    }

    public function save(): void
    {
        $this->validate([
            'driver' => 'required|in:reverb,poll,null',
            'pollVisibleMs' => 'required|integer|min:1000|max:60000',
            'pollHiddenMs' => 'required|integer|min:5000|max:300000',
            'cooldownDays' => 'required|integer|min:0|max:365',
            'reserved' => 'nullable|string|max:2000',
            'otpPerUserDay' => 'required|integer|min:1|max:50',
            'otpPerNumberDay' => 'required|integer|min:1|max:50',
            'otpPerIpDay' => 'required|integer|min:1|max:500',
            'searchPerMin' => 'required|integer|min:1|max:500',
            'importMax' => 'required|integer|min:10|max:5000',
            'maxPinnedChats' => 'required|integer|min:1|max:20',
            'exportMaxMessages' => 'required|integer|min:100|max:50000',
            'otpFrom' => 'nullable|string|max:30',
            'priceMessage' => 'required|numeric|min:0.01|max:100',
            'storageUsdPerMb' => 'required|numeric|min:0|max:1',
            'mediaMarkup' => 'required|numeric|min:1|max:100',
            'minMarginPct' => 'required|numeric|min:0|max:1000',
            'callUsdPerMin' => 'required|numeric|min:0|max:10',
            'speakerUsdPerMin' => 'required|numeric|min:0|max:10',
            'shareMin' => 'required|numeric|min:0.01|max:100000',
            'shareMax' => 'required|numeric|gte:shareMin|max:1000000',
            'shareCapDay' => 'required|numeric|gte:shareMax|max:10000000',
            'shareCapRecipient' => 'required|numeric|min:1|max:10000000',
            'shareMinAgeDays' => 'required|integer|min:0|max:365',
            'giftHours' => 'required|integer|min:1|max:168',
            'giftMaxPerDay' => 'required|integer|min:1|max:200',
            'purchaseMinUsd' => 'required|numeric|min:0.5|max:10000',
            'purchaseMaxUsd' => 'required|numeric|gte:purchaseMinUsd|max:100000',
            'lowBalance' => 'required|numeric|min:0|max:100000',
            'floatingRoutes' => ['nullable', 'string', 'max:600', 'regex:/^[a-z0-9_.*,\s-]*$/i'],
            'floatingHours' => 'required|integer|min:1|max:720',
        ]);
        $before = ['enabled' => (bool) Setting::getValue(N2NSettings::FLAG, false), 'driver' => \App\Services\N2N\Realtime\Realtime::driver()];
        Setting::setValue(N2NSettings::FLAG, $this->enabled);
        Setting::setValue('n2n.realtime_driver', $this->driver);
        Setting::setValue('n2n.poll_visible_ms', $this->pollVisibleMs);
        Setting::setValue('n2n.poll_hidden_ms', $this->pollHiddenMs);
        Setting::setValue('n2n.username_cooldown_days', $this->cooldownDays);
        Setting::setValue('n2n.reserved_usernames', trim($this->reserved));
        Setting::setValue('n2n.otp_per_user_day', $this->otpPerUserDay);
        Setting::setValue('n2n.otp_per_number_day', $this->otpPerNumberDay);
        Setting::setValue('n2n.otp_per_ip_day', $this->otpPerIpDay);
        Setting::setValue('n2n.search_per_min', $this->searchPerMin);
        Setting::setValue('n2n.import_max_contacts', $this->importMax);
        Setting::setValue('n2n.max_pinned_chats', $this->maxPinnedChats);
        Setting::setValue('n2n.export_max_messages', $this->exportMaxMessages);
        Setting::setValue('n2n.otp_from', trim($this->otpFrom));
        Setting::setValue('n2n.floating_chat', $this->floatingChat);
        Setting::setValue('n2n.floating_chat_routes', trim($this->floatingRoutes));
        Setting::setValue('n2n.floating_chat_dismiss_hours', $this->floatingHours);
        foreach (['price_message' => $this->priceMessage, 'storage_usd_per_mb' => $this->storageUsdPerMb, 'media_markup' => $this->mediaMarkup, 'min_margin_pct' => $this->minMarginPct,
            'call_usd_per_min' => $this->callUsdPerMin, 'speaker_usd_per_min' => $this->speakerUsdPerMin, 'share_min' => $this->shareMin, 'share_max' => $this->shareMax,
            'share_cap_per_day' => $this->shareCapDay, 'share_cap_per_recipient_day' => $this->shareCapRecipient, 'share_min_account_days' => $this->shareMinAgeDays,
            'gift_hours' => $this->giftHours, 'gift_max_per_day' => $this->giftMaxPerDay, 'purchase_min_usd' => $this->purchaseMinUsd, 'purchase_max_usd' => $this->purchaseMaxUsd,
            'low_balance_credits' => $this->lowBalance, 'credit_purchase_ios' => $this->purchaseIos] as $k => $v) {
            Setting::setValue('n2n.'.$k, $v);
        }
        Auditor::log('n2n.settings_updated', null, null, ['before' => $before, 'enabled' => $this->enabled, 'driver' => $this->driver, 'by' => Auth::id()]);
        $this->saved = 'N2N settings saved.';
    }

    public function render()
    {
        return view('livewire.admin.n2n.settings', [
            'health' => app(RealtimeHealth::class)->report(),
            'otpCost' => app(PhoneLinkService::class)->costReport(),
            'example' => \App\Services\N2N\Credit\N2NPricing::workedExample(),
            'accounting' => app(\App\Services\N2N\Credit\CreditAccounting::class)->report(),
            'flags' => app(\App\Services\N2N\Credit\GiftVelocity::class)->scan(false),
        ]);
    }
}
