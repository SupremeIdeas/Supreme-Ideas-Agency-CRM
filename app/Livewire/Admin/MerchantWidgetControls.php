<?php

namespace App\Livewire\Admin;

use App\Models\Merchant;
use App\Models\MerchantWidgetDataRequest;
use App\Models\MerchantWidgetKey;
use App\Models\MerchantWidgetOrder;
use App\Models\Setting;
use App\Services\MerchantWidgets\MerchantWidgetAccessService;
use App\Services\MerchantWidgets\MerchantWidgetKeyService;
use App\Support\Auditor;
use App\Support\MerchantWidgetSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin -> Widgets (MASTER-ONLY, blueprint §12): the master switch, every limit, a live key list with one-click revoke, and the queue
 * of orders held for a manual look. Every limit is a setting here, never a literal in code; every change is audited.
 */
#[Layout('components.layouts.admin')]
class MerchantWidgetControls extends Component
{
    public bool $enabled = false;

    public int $maxKeys = 5;

    public int $catalogueRate = 120;

    public int $checkoutRate = 10;

    public float $guestCap = 100;

    public int $sessionTtl = 15;

    public int $velocity = 60;

    public string $pluginUrl = '';

    public bool $numbersEnabled = false;

    public string $numberServices = '';

    public bool $accountEnabled = true;

    public int $shopperDays = 30;

    public bool $paidAccess = false;

    public float $accessPrice = 0;

    public ?string $saved = null;

    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        $this->enabled = MerchantWidgetSettings::enabled();
        $this->maxKeys = MerchantWidgetSettings::maxKeys();
        $this->catalogueRate = MerchantWidgetSettings::catalogueRate();
        $this->checkoutRate = MerchantWidgetSettings::checkoutRate();
        $this->guestCap = MerchantWidgetSettings::firstTimeGuestCapUsd();
        $this->sessionTtl = MerchantWidgetSettings::sessionTtlMinutes();
        $this->velocity = MerchantWidgetSettings::velocityPerKeyHour();
        $this->pluginUrl = (string) MerchantWidgetSettings::wpPluginUrl();
        $this->numbersEnabled = MerchantWidgetSettings::numbersEnabled();
        $this->numberServices = implode(', ', MerchantWidgetSettings::numberServices());
        $this->accountEnabled = MerchantWidgetSettings::accountEnabled();
        $this->shopperDays = MerchantWidgetSettings::shopperSessionDays();
        $this->paidAccess = MerchantWidgetSettings::paidAccess();
        $this->accessPrice = MerchantWidgetSettings::accessPriceUsd();
    }

    /** An admin has carried out a shopper's deletion request (through the normal erasure tools): close it. */
    public function closeRequest(int $id): void
    {
        $r = MerchantWidgetDataRequest::query()->where('status', 'open')->findOrFail($id);
        $r->forceFill(['status' => 'done', 'handled_at' => now()])->save();
        Auditor::log('merchant_widget.data_request_closed', MerchantWidgetDataRequest::class, $r->id, ['kind' => $r->kind, 'by' => Auth::id()]);
        $this->saved = 'Request closed.';
    }

    public function save(): void
    {
        $this->validate([
            'maxKeys' => 'required|integer|min:1|max:50',
            'catalogueRate' => 'required|integer|min:1|max:5000',
            'checkoutRate' => 'required|integer|min:1|max:500',
            'guestCap' => 'required|numeric|min:1|max:10000',
            'sessionTtl' => 'required|integer|min:1|max:120',
            'velocity' => 'required|integer|min:1|max:5000',
            'pluginUrl' => ['nullable', 'url:https', 'max:300'],
            'numberServices' => ['nullable', 'string', 'max:1000', 'regex:/^[a-z0-9_\-,\s]*$/i'],
            'shopperDays' => 'required|integer|min:1|max:90',
            'accessPrice' => 'required|numeric|min:0|max:100000',
        ]);
        $before = ['enabled' => MerchantWidgetSettings::enabled()];
        Setting::setValue(MerchantWidgetSettings::FLAG, $this->enabled);
        Setting::setValue(MerchantWidgetSettings::MAX_KEYS, $this->maxKeys);
        Setting::setValue(MerchantWidgetSettings::CATALOGUE_RATE, $this->catalogueRate);
        Setting::setValue(MerchantWidgetSettings::CHECKOUT_RATE, $this->checkoutRate);
        Setting::setValue(MerchantWidgetSettings::FIRST_TIME_GUEST_CAP_USD, $this->guestCap);
        Setting::setValue(MerchantWidgetSettings::SESSION_TTL_MINUTES, $this->sessionTtl);
        Setting::setValue(MerchantWidgetSettings::VELOCITY_PER_KEY_HOUR, $this->velocity);
        Setting::setValue(MerchantWidgetSettings::WP_PLUGIN_URL, trim($this->pluginUrl));
        Setting::setValue(MerchantWidgetSettings::NUMBERS_ENABLED, $this->numbersEnabled);
        Setting::setValue(MerchantWidgetSettings::NUMBER_SERVICES, strtolower(trim($this->numberServices)));
        Setting::setValue(MerchantWidgetSettings::ACCOUNT_ENABLED, $this->accountEnabled);
        Setting::setValue(MerchantWidgetSettings::SHOPPER_SESSION_DAYS, $this->shopperDays);
        $paidBefore = ['paid' => MerchantWidgetSettings::paidAccess(), 'price' => MerchantWidgetSettings::accessPriceUsd()];
        Setting::setValue(MerchantWidgetSettings::PAID_ACCESS, $this->paidAccess);
        Setting::setValue(MerchantWidgetSettings::ACCESS_PRICE_USD, round($this->accessPrice, 2));
        if ($paidBefore !== ['paid' => $this->paidAccess, 'price' => round($this->accessPrice, 2)]) {
            Auditor::log('merchant_widget.access_pricing_changed', null, null, ['before' => $paidBefore, 'after' => ['paid' => $this->paidAccess, 'price' => round($this->accessPrice, 2)], 'by' => Auth::id()]);
        }
        Auditor::log('merchant_widget.settings_updated', null, null, ['enabled_before' => $before['enabled'], 'enabled_after' => $this->enabled, 'by' => Auth::id()]);
        $this->saved = 'Widget settings saved.';
    }

    /** Give a merchant access without charging (support, partners). */
    public function grantAccess(int $merchantId, MerchantWidgetAccessService $access): void
    {
        $access->grant(Merchant::query()->findOrFail($merchantId), Auth::user());
        $this->saved = 'Access granted.';
    }

    public function revoke(int $keyId, MerchantWidgetKeyService $keys): void
    {
        $key = MerchantWidgetKey::query()->findOrFail($keyId);
        if ($key->status === MerchantWidgetKey::ACTIVE) {
            $keys->revoke($key, 'revoked by admin', Auth::id());
            $this->saved = 'Key revoked. It stops working on its next request.';
        }
    }

    public function render()
    {
        $keys = MerchantWidgetKey::query()->with('merchant:id,business_name,slug')
            ->when(trim($this->search) !== '', function ($q) {
                $s = '%'.trim($this->search).'%';
                $q->where(fn ($w) => $w->where('label', 'like', $s)->orWhere('public_key', 'like', $s)
                    ->orWhereIn('merchant_id', Merchant::query()->where('business_name', 'like', $s)->pluck('id')));
            })
            ->orderByRaw("status = 'active' desc")->orderByDesc('last_seen_at')->limit(100)->get();
        $sessions = MerchantWidgetOrder::query()->where('created_at', '>=', now()->subDay())->selectRaw('merchant_widget_key_id, count(*) as n')
            ->groupBy('merchant_widget_key_id')->pluck('n', 'merchant_widget_key_id');
        $held = MerchantWidgetOrder::query()->with('merchant:id,business_name')->where('status', MerchantWidgetOrder::HELD)->latest()->limit(50)->get();

        return view('livewire.admin.merchant-widget-controls', [
            'keys' => $keys,
            'purchases' => \App\Models\MerchantWidgetAccessPurchase::query()->with('merchant:id,business_name')->latest('id')->limit(20)->get(),
            'purchaseRevenue' => (float) \App\Models\MerchantWidgetAccessPurchase::query()->sum('amount_usd'),
            'sessions' => $sessions,
            'held' => $held,
            'dataRequests' => MerchantWidgetDataRequest::query()->with('merchant:id,business_name')->where('status', 'open')->where('kind', MerchantWidgetDataRequest::DELETE)->latest()->limit(50)->get(),
            'orders24h' => MerchantWidgetOrder::query()->where('created_at', '>=', now()->subDay())->count(),
            'completed24h' => MerchantWidgetOrder::query()->where('created_at', '>=', now()->subDay())->where('status', MerchantWidgetOrder::COMPLETED)->count(),
        ]);
    }
}
