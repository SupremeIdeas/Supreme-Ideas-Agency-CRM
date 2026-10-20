<?php

namespace App\Http\Controllers\MerchantWidget;

use App\Http\Controllers\Controller;
use App\Livewire\Wallet;
use App\Models\EsimPlan;
use App\Models\GiftCardProduct;
use App\Services\MerchantWidgets\MerchantWidgetCheckoutService;
use App\Services\Pricing\CurrencyService;
use App\Support\GatewayCurrencyMatrix;
use App\Support\MerchantWidgetBrand;
use App\Support\ProviderStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use RuntimeException;

/**
 * The Naara-hosted checkout a widget opens (MASTER-ONLY, blueprint §8). Co-branded: the merchant's name/colour leads, Naara is the
 * small "powered by". Single-use, 15-minute token; a stale token is a plain 404. The page never takes money itself: it hands off to
 * the shopper's chosen gateway through the existing pipeline.
 */
class HostedCheckoutController extends Controller
{
    /** Currencies a shopper can pick here; the same list `pay` validates against. */
    private const CURRENCIES = ['USD', 'NGN', 'GHS', 'KES', 'ZAR', 'GBP', 'EUR', 'CAD', 'INR'];

    public function show(Request $request, string $token, MerchantWidgetCheckoutService $service, CurrencyService $fx): Response
    {
        $order = $service->orderForToken($token);
        if ($order === null || ! $order->key?->usable()) {
            // Same 404 as before, but a shopper who pressed Back from the payment page lands on words, not a blank error.
            return response()->view('merchant-widget.expired', ['theme' => $this->theme($request, null), 'brand' => MerchantWidgetBrand::color($this->brandOverride($request))], 404)
                ->header('Cache-Control', 'no-store, private');
        }

        $merchant = $order->key->merchant;
        $key = (array) ($order->key->theme ?? []);
        $brand = MerchantWidgetBrand::color($this->brandOverride($request) ?: ($key['primary_color'] ?? null) ?: $merchant->brand_color);

        [$name, $details] = $this->product($order);

        // Only gateway + currency pairs that really work are offered, so the shopper can never pick a pairing that errors out.
        $gateways = [];
        foreach ($this->gateways() as $slug => [$label, $hint]) {
            $currencies = array_values(array_intersect(self::CURRENCIES, GatewayCurrencyMatrix::currenciesFor($slug)));
            if ($currencies !== []) {
                $gateways[$slug] = ['label' => $label, 'hint' => $hint, 'currencies' => $currencies];
            }
        }
        $usd = (float) $order->merchant_price_usd;
        $quotes = ['USD' => $usd];
        foreach (array_unique(array_merge(...array_column($gateways, 'currencies') ?: [[]])) as $cur) {
            if ($cur !== 'USD') {
                try {
                    $quotes[$cur] = $fx->usdToLocal($usd, $cur);
                } catch (\Throwable) {
                    // no rate right now: the page then just shows no estimate for that currency
                }
            }
        }
        $guess = MerchantWidgetBrand::currencyGuess($request->header('Accept-Language'));
        $defaultGateway = array_key_first(array_filter($gateways, fn ($g) => $guess && in_array($guess, $g['currencies'], true) && isset($quotes[$guess]))) ?? array_key_first($gateways);
        $currencies = $gateways[$defaultGateway]['currencies'] ?? [];
        $defaultCurrency = $guess && in_array($guess, $currencies, true) && isset($quotes[$guess]) ? $guess : (in_array('USD', $currencies, true) ? 'USD' : ($currencies[0] ?? 'USD'));

        return response()->view('merchant-widget.checkout', [
            'order' => $order,
            'token' => $token,
            'merchant' => $merchant,
            'productName' => $name,
            'details' => $details,
            'gateways' => $gateways,
            'quotes' => $quotes,
            'defaultGateway' => $defaultGateway,
            'defaultCurrency' => $defaultCurrency,
            'parentOrigins' => $order->key->allowedDomains(),
            'brand' => $brand,
            'theme' => $this->theme($request, $key['mode'] ?? null),
            'kind' => $order->kind,
            'ref' => 'NW-'.$order->id,
        ])->header('Cache-Control', 'no-store, private');
    }

    private function brandOverride(Request $request): ?string
    {
        $c = '#'.ltrim((string) $request->query('color', ''), '#');

        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : null;
    }

    /** ?theme=light|dark from the widget wins, then the key's own mode, else follow the shopper's device. */
    private function theme(Request $request, ?string $keyMode): string
    {
        $t = (string) $request->query('theme', '');

        return in_array($t, ['light', 'dark'], true) ? $t : (in_array($keyMode, ['light', 'dark'], true) ? $keyMode : 'auto');
    }

    /** @return array{0: string, 1: list<array{icon: string, text: string}>} the product's name and the facts a shopper wants before paying */
    private function product(\App\Models\MerchantWidgetOrder $order): array
    {
        if ($order->kind === 'esim' && ($plan = EsimPlan::find($order->product_id))) {
            $mb = (int) $plan->data_mb;
            $facts = [];
            if ($mb > 0) {
                $facts[] = ['icon' => 'data', 'text' => $mb >= 1024 ? rtrim(rtrim(number_format($mb / 1024, 1), '0'), '.').' GB data' : $mb.' MB data'];
            }
            if ($plan->validity_days) {
                $facts[] = ['icon' => 'clock', 'text' => $plan->validity_days.' days'];
            }
            $countries = array_values(array_filter((array) $plan->countries, 'is_string'));
            if ($countries !== []) {
                $facts[] = ['icon' => 'globe', 'text' => count($countries) > 3 ? count($countries).' countries' : implode(', ', $countries)];
            }

            return [$plan->name, $facts];
        }
        if ($order->kind === 'giftcard' && ($gc = GiftCardProduct::find($order->product_id))) {
            return [$gc->brand_name.' gift card', [['icon' => 'gift', 'text' => number_format((float) $order->face_value, 2).' '.($gc->currency ?: '').' value']]];
        }

        if ($order->kind === 'number' && ($pick = \App\Support\MerchantWidgetNumbers::parse($order->product_ref))) {
            return [
                \App\Support\MerchantWidgetNumbers::serviceLabel($pick['service']).' verification number',
                [
                    ['icon' => 'globe', 'text' => \App\Support\MerchantWidgetNumbers::countryLabel($pick['country'])],
                    ['icon' => 'clock', 'text' => 'Code arrives by SMS in about a minute'],
                    ['icon' => 'shield', 'text' => 'No code within '.\App\Support\MerchantWidgetNumbers::refundWindowMinutes().' minutes: refunded automatically'],
                ],
            ];
        }

        return ['Your order', []];
    }

    /** Where the payment gateway sends the shopper back (signed URL). Status only: delivery details are emailed, never shown to a bearer URL. */
    public function done(Request $request, int $order): Response
    {
        $row = \App\Models\MerchantWidgetOrder::query()->with('key.merchant')->find($order);
        abort_if($row === null || ! $row->key?->merchant, 404);
        $host = $row->key->allowedDomains()[0] ?? null;

        return response()->view('merchant-widget.return', [
            'order' => $row,
            'merchant' => $row->key->merchant,
            'shopUrl' => $host ? (str_starts_with($host, 'localhost') ? 'http://' : 'https://').$host : null,
            'pending' => in_array($row->status, [\App\Models\MerchantWidgetOrder::PENDING, \App\Models\MerchantWidgetOrder::FULFILLING], true),
            'brand' => MerchantWidgetBrand::color(((array) ($row->key->theme ?? []))['primary_color'] ?? $row->key->merchant->brand_color),
            'theme' => $this->theme($request, ((array) ($row->key->theme ?? []))['mode'] ?? null),
            'kind' => $row->kind,
            'ref' => 'NW-'.$row->id,
        ])->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    public function pay(Request $request, string $token, MerchantWidgetCheckoutService $service): JsonResponse
    {
        $order = $service->orderForToken($token);
        abort_if($order === null || ! $order->key?->usable(), 404);

        $data = $request->validate([
            'email' => 'required|email:rfc|max:190',
            'gateway' => 'required|in:'.implode(',', array_keys($this->gateways())),
            'currency' => 'required|in:USD,NGN,GHS,KES,ZAR,GBP,EUR,CAD,INR',
        ]);

        try {
            $url = $service->startPayment($order, $data['email'], $data['gateway'], $data['currency']);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'message' => 'We could not start the payment. You were not charged.'], 502);
        }

        return response()->json(['ok' => true, 'redirect_url' => $url]);
    }

    /** Only gateways that are switched on (ProviderStatus), same list the wallet top-up uses. */
    private function gateways(): array
    {
        return array_filter(Wallet::GATEWAYS, fn ($v, $slug) => ProviderStatus::isActive($slug), ARRAY_FILTER_USE_BOTH);
    }
}
