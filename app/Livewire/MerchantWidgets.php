<?php

namespace App\Livewire;

use App\Models\Merchant;
use App\Models\MerchantEarning;
use App\Models\MerchantWidgetKey;
use App\Exceptions\InsufficientBalanceException;
use App\Services\MerchantWidgets\MerchantWidgetAccessService;
use App\Services\MerchantWidgets\MerchantWidgetAnalytics;
use App\Services\MerchantWidgets\MerchantWidgetKeyService;
use App\Services\MerchantWidgets\MerchantWidgetPluginPackager;
use App\Support\MerchantWidgetSdk;
use App\Support\MerchantWidgetSettings;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Merchant dashboard "Widgets" page (MASTER-ONLY, blueprint §11): create/rotate/revoke widget keys, copy the embed snippet, download the
 * WordPress plugin, and see this key's funnel and earnings. Only the PUBLIC key is ever shown; every key is scoped to the signed-in
 * merchant on every action, never to an id the browser sent unchecked.
 */
#[Layout('components.layouts.customer')]
class MerchantWidgets extends Component
{
    public string $label = '';

    public string $domains = '';

    /** @var list<string> */
    public array $scopes = ['esim', 'number', 'giftcard'];

    public ?int $selectedId = null;

    public string $snippetKind = '';

    public string $snippetMode = 'modal';

    public ?string $error = null;

    public ?string $notice = null;

    /** Analytics window in days (7, 30 or 90). */
    public int $range = 30;

    private function merchant(): Merchant
    {
        $merchant = Auth::user()?->merchantAccount;
        abort_unless(MerchantWidgetSettings::enabled() && $merchant && $merchant->isActive(), 404);

        return $merchant;
    }

    private function ownKey(int $id): MerchantWidgetKey
    {
        return MerchantWidgetKey::query()->where('merchant_id', $this->merchant()->id)->findOrFail($id);
    }

    public function buyAccess(MerchantWidgetAccessService $access): void
    {
        $this->error = $this->notice = null;
        $merchant = $this->merchant();
        try {
            $access->buy($merchant, Auth::user());
        } catch (InsufficientBalanceException) {
            $this->error = 'Your wallet does not have enough balance. Top up your wallet and try again.';

            return;
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->notice = 'Widgets unlocked. Create your first key below.';
    }

    public function create(MerchantWidgetKeyService $keys, MerchantWidgetAccessService $access): void
    {
        $this->error = $this->notice = null;
        if (! $access->hasAccess($this->merchant())) {
            $this->error = 'Unlock Widgets first to create a key.';

            return;
        }
        try {
            ['key' => $key] = $keys->create(
                $this->merchant(), $this->label, preg_split('/[\s,]+/', trim($this->domains)) ?: [], array_values($this->scopes), null, Auth::id(),
            );
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->selectedId = $key->id;
        $this->reset('label', 'domains');
        $this->notice = 'Widget key created. Paste the snippet below on the site you listed.';
    }

    public function select(int $id): void
    {
        $this->selectedId = $this->ownKey($id)->id;
    }

    public function revoke(int $id, MerchantWidgetKeyService $keys): void
    {
        $key = $this->ownKey($id);
        if ($key->status === MerchantWidgetKey::ACTIVE) {
            $keys->revoke($key, 'revoked by merchant', Auth::id());
            $this->notice = 'Key revoked. It stops working on its very next request.';
        }
    }

    public function setRange(int $days): void
    {
        $this->range = in_array($days, MerchantWidgetAnalytics::RANGES, true) ? $days : 30;
    }

    public function rotate(int $id, MerchantWidgetKeyService $keys, MerchantWidgetAccessService $access): void
    {
        $this->error = $this->notice = null;
        if (! $access->hasAccess($this->merchant())) {
            $this->error = 'Unlock Widgets first to rotate a key.';

            return;
        }
        $key = $this->ownKey($id);
        abort_unless($key->status === MerchantWidgetKey::ACTIVE, 422);
        try {
            $new = $keys->rotate($key, Auth::id())['key'];
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->selectedId = $new->id;
        $this->notice = 'New key issued and the old one revoked. Update the snippet on your site.';
    }

    public function render()
    {
        $merchant = $this->merchant();
        $keys = MerchantWidgetKey::query()->where('merchant_id', $merchant->id)->orderByDesc('id')->get();
        $selected = $keys->firstWhere('id', $this->selectedId) ?? $keys->firstWhere('status', MerchantWidgetKey::ACTIVE);

        $analytics = $selected ? app(MerchantWidgetAnalytics::class)->forKey($selected, $this->range) : null;
        $hasAccess = app(MerchantWidgetAccessService::class)->hasAccess($merchant);
        $earned = MerchantEarning::query()->where('merchant_id', $merchant->id)->where('type', MerchantEarning::ACCRUAL)
            ->where('source_type', 'like', 'widget:%')->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('source_type, sum(amount) as total')->groupBy('source_type')->pluck('total', 'source_type');

        return view('livewire.merchant-widgets', [
            'keys' => $keys,
            'selected' => $selected,
            'analytics' => $analytics,
            'hasAccess' => $hasAccess,
            'paid' => MerchantWidgetSettings::isPaid(),
            'price' => MerchantWidgetSettings::accessPriceUsd(),
            'spendable' => ! $hasAccess ? app(\App\Services\Wallet\WalletService::class)->spendableUsd(Auth::user()) : 0.0,
            'earned' => $earned,
            'numbersOn' => MerchantWidgetSettings::numbersEnabled(),
            'accountOn' => MerchantWidgetSettings::accountEnabled(),
            'snippet' => $hasAccess && $selected && $selected->status === MerchantWidgetKey::ACTIVE
                ? MerchantWidgetSdk::snippet($selected->public_key, $this->snippetKind, $this->snippetMode) : null,
            'pluginVersion' => MerchantWidgetPluginPackager::version(),
            'pluginUrl' => MerchantWidgetSettings::wpPluginUrl(),
        ]);
    }
}
