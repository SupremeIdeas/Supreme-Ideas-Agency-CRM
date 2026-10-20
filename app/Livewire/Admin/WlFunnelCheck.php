<?php

namespace App\Livewire\Admin;

use App\Models\ManualPaymentAccount;
use App\Models\WhiteLabelLicensePlan;
use App\Services\WhiteLabel\Funnel\WlFunnel;
use App\Services\WhiteLabel\Funnel\WlFunnelManualPayments;
use App\Services\WhiteLabel\Funnel\WlInvoiceSettings;
use App\Services\WhiteLabel\Payments\ManualPaymentException;
use App\Services\WhiteLabel\Payments\WlRateQuoter;
use App\Support\FeatureFlags;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin > White Label > Funnel check (Funnel Phase 10, MASTER-ONLY): is the funnel ready to take a real buyer, and what would a buyer be quoted? Both are pure reads: the quote simulator
 * runs the same rate rules a buyer meets (fails closed on a stale rate) but creates, stores and numbers nothing.
 */
#[Layout('components.layouts.admin')]
class WlFunnelCheck extends Component
{
    public string $plan = '';

    public string $currency = 'USD';

    public ?array $quote = null;

    public ?string $quoteError = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);
        $this->plan = (string) (WhiteLabelLicensePlan::query()->orderBy('sort_order')->value('key') ?? '');
    }

    /** What a buyer picking this plan and currency would be asked to send right now. Nothing is saved. */
    public function simulate(WlRateQuoter $rates): void
    {
        $this->reset('quote', 'quoteError');
        $plan = WhiteLabelLicensePlan::query()->where('key', $this->plan)->first();
        if (! $plan || ! in_array($this->currency, WlFunnelManualPayments::CURRENCIES, true)) {
            $this->quoteError = 'Choose a plan and a currency.';

            return;
        }
        $price = (float) $plan->price_usd;
        try {
            $q = $rates->amountToSend($this->currency, $price);
        } catch (ManualPaymentException $e) {
            $this->quoteError = $e->getMessage().' A buyer would see exactly this and could not pay in this currency until a current rate is added.';

            return;
        }
        $tax = WlInvoiceSettings::tax();
        $taxTotal = round($price * $tax['rate'] / 100, 2);
        $this->quote = [
            'plan' => $plan->name, 'price_usd' => $price, 'tax_rate' => (float) $tax['rate'], 'tax' => $taxTotal, 'total_usd' => round($price + $taxTotal, 2), 'currency' => $q['currency'], 'amount' => (float) $q['amount'],
            'rate' => (float) $q['rate'], 'source' => (string) $q['source'], 'as_of' => $q['as_of']->format('Y-m-d H:i').' UTC', 'locked_until' => $q['locked_until']->format('Y-m-d H:i').' UTC',
        ];
    }

    /** @return list<array{label:string, ok:bool, detail:string}> */
    public function checks(WlFunnelManualPayments $svc): array
    {
        $out = [];
        $add = function (string $label, bool $ok, string $detail) use (&$out) {
            $out[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail];
        };
        $add('Funnel switched on', WlFunnel::enabled(), WlFunnel::enabled() ? 'Buyers can start a purchase.' : 'The wl_funnel switch is off: nobody can start a purchase.');
        $add('Manual payments switched on', WlFunnelManualPayments::enabled(), WlFunnelManualPayments::enabled() ? 'Buyers can pay by transfer.' : 'The wl_manual_payments switch is off: the pay page shows nothing to pay with.');
        $plans = WhiteLabelLicensePlan::query()->where('is_active', true)->get();
        $unpriced = $plans->filter(fn ($p) => (float) $p->price_usd <= 0)->pluck('name')->all();
        $add('Plans have prices', $plans->isNotEmpty() && $unpriced === [], $plans->isEmpty() ? 'No active plans.' : ($unpriced === [] ? $plans->count().' active plan(s), all priced.' : 'No price on: '.implode(', ', $unpriced)));
        $usable = [];
        foreach (WlFunnelManualPayments::CURRENCIES as $c) {
            $usable[$c] = $svc->availableCurrencies()[$c] === null;
        }
        $any = in_array(true, $usable, true);
        $add('At least one way to pay', $any, $any ? 'Ready: '.implode(', ', array_keys(array_filter($usable))).'.' : 'No receiving account is enabled (a stablecoin account also needs its test confirmed).');
        $noRate = [];
        foreach (array_keys(array_filter($usable)) as $c) {
            try {
                app(WlRateQuoter::class)->amountToSend($c, 100.0);
            } catch (ManualPaymentException) {
                $noRate[] = $c;
            }
        }
        $add('A current rate for every currency offered', $noRate === [], $noRate === [] ? 'Quotes work in every currency that is offered.' : 'No current rate for '.implode(', ', $noRate).': a buyer picking it gets an error.');
        $seller = WlInvoiceSettings::seller();
        $add('Invoice seller details', $seller['name'] !== '' && $seller['address'] !== '' && $seller['email'] !== '', ($seller['address'] === '' || $seller['email'] === '') ? 'Add the seller address and email so documents are complete.' : 'Name, address and email are set.');
        $api = (bool) \App\Models\Setting::getValue('white_label_api.enabled', false);
        $add('Distribution API switched on', $api, $api ? 'A buyer\'s White Label can activate with its licence key and receive updates.' : 'The Distribution API is off (Admin > White Label > Licences): a paid buyer\'s copy cannot activate or receive updates until you switch it on.');
        $add('Receiving accounts have a payee note', ManualPaymentAccount::query()->where('enabled', true)->whereNull('payee_note')->count() === 0, 'A short note helps a buyer pay the right account (optional).');

        return $out;
    }

    public function render(WlFunnelManualPayments $svc)
    {
        return view('livewire.admin.wl-funnel-check', ['checks' => $this->checks($svc), 'plans' => WhiteLabelLicensePlan::query()->orderBy('sort_order')->get(['key', 'name', 'price_usd']), 'currencies' => WlFunnelManualPayments::CURRENCIES]);
    }
}
