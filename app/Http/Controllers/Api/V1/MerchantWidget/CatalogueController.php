<?php

namespace App\Http\Controllers\Api\V1\MerchantWidget;

use App\Http\Controllers\Controller;
use App\Models\EsimPlan;
use App\Models\GiftCardProduct;
use App\Models\MerchantWidgetKey;
use App\Services\Pricing\PricingEngine;
use App\Support\GiftCardPricing;
use App\Support\MerchantWidgetNumbers;
use App\Support\MerchantWidgetSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Naara Widgets catalogue (MASTER-ONLY, blueprint §6). Same masked shape as the Developer API catalogue, priced in the MERCHANT lane,
 * and filtered to the key's product scopes. Never returns cost, provider or supplier identity.
 */
class CatalogueController extends Controller
{
    public function index(Request $request, PricingEngine $pricing, GiftCardPricing $giftPricing): JsonResponse
    {
        /** @var MerchantWidgetKey $key */
        $key = $request->attributes->get('merchant_widget_key');
        $merchant = $key->merchant;
        $kind = (string) $request->query('kind', '');

        $data = [];

        if ($key->hasScope('esim') && ($kind === '' || $kind === 'esim')) {
            $data['esim'] = EsimPlan::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(200)
                ->get()
                ->map(fn (EsimPlan $plan) => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'type' => $plan->type,
                    'has_voice' => (bool) $plan->has_voice,
                    'data_mb' => $plan->data_mb,
                    'validity_days' => $plan->validity_days,
                    'countries' => $plan->countries,
                    'price_usd' => round($pricing->merchantEsimPrice($plan, $merchant, log: false), 2),
                    'currency' => 'USD',
                ])->all();
        }

        if ($key->hasScope('giftcard') && ($kind === '' || $kind === 'giftcard')) {
            // One lead card per brand (not every regional duplicate), the world's most-bought brands first, then A-Z; the widget shows a short, recognisable shelf.
            $cards = GiftCardProduct::query()->storefrontCards();
            $leads = (clone $cards)->where('is_lead', true);
            $cards = $leads->exists() ? $leads : $cards;
            $data['giftcard'] = $cards->tap(fn ($q) => \App\Support\GiftPopularity::order($q))->limit(200)->get()
                ->map(function (GiftCardProduct $p) use ($giftPricing, $merchant) {
                    $denoms = $p->isRange()
                        ? ['type' => 'RANGE', 'min' => (float) $p->min_amount, 'max' => (float) $p->max_amount]
                        : ['type' => 'FIXED', 'options' => collect((array) $p->fixed_denominations)
                            ->filter(fn ($f) => is_numeric($f) && (float) $f > 0)
                            ->map(fn ($f) => ['face' => (float) $f, 'price_usd' => $giftPricing->merchant($p, (float) $f, $merchant)])
                            ->values()->all()];

                    return [
                        'id' => $p->id,
                        'brand' => $p->brand_name,
                        // The embed runs on the MERCHANT's site, so a logo must be a full https address (a root-relative path would 404 there and the widget drops it).
                        'logo_url' => self::absoluteUrl($p->logo_url),
                        'brand_color' => $p->brand_color,
                        'category' => $p->category,
                        'face_currency' => $p->currency,
                        'denominations' => $denoms,
                        'currency' => 'USD',
                    ];
                })->all();
        }

        $lookups = [];
        if ($key->hasScope('number') && MerchantWidgetSettings::numbersEnabled() && ($kind === '' || $kind === 'number')) {
            // Verification numbers: the services on offer; the shopper then picks a country and gets a live price from /number-quote.
            $data['number'] = MerchantWidgetNumbers::services();
            $lookups['number_countries'] = MerchantWidgetNumbers::countries();
            $lookups['number_refund_minutes'] = MerchantWidgetNumbers::refundWindowMinutes();
        }

        // What each tab is, in plain words and a fixed order, so a shopper always knows what they are buying.
        $about = [
            'esim' => ['title' => 'Travel eSIM data', 'blurb' => 'Mobile data for your trip. No SIM swap: scan a QR code and you are online.'],
            'number' => ['title' => 'Verification numbers', 'blurb' => 'A temporary phone number to receive the SMS code for WhatsApp, Telegram, Google and more.'],
            'giftcard' => ['title' => 'Gift cards', 'blurb' => 'Digital gift cards for top brands. The code is delivered by email.'],
        ];
        $kinds = [];
        foreach ($about as $k => $meta) {
            if (! empty($data[$k])) {
                $kinds[] = ['kind' => $k, 'title' => $meta['title'], 'blurb' => $meta['blurb'], 'count' => count($data[$k])];
            }
        }

        return response()->json([
            'data' => $data,
            'kinds' => $kinds,
            'lookups' => (object) $lookups,
            'account' => MerchantWidgetSettings::accountEnabled(),
            'merchant' => ['name' => $merchant->business_name],
            'theme' => $key->theme,
        ]);
    }

    /**
     * A stored logo path or URL as an absolute address this platform serves. Our own files (`/images/...`, `/storage/...`) are re-rooted on the current site
     * address, so a logo saved while the app answered on another host (an old APP_URL, a staging address) never breaks on a merchant's page; a third-party
     * address stays as it is. Empty stays null.
     */
    private static function absoluteUrl(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }
        if (! preg_match('#^https?://#i', $path)) {
            return url('/'.ltrim($path, '/'));
        }
        $parts = parse_url($path);
        $own = (string) ($parts['path'] ?? '');
        if (preg_match('#^/(images|storage)/#', $own) && ($parts['host'] ?? '') !== parse_url(url('/'), PHP_URL_HOST)) {
            return url($own);
        }

        return $path;
    }
}
