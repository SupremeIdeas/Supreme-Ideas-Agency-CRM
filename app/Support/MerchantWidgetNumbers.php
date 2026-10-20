<?php

namespace App\Support;

use App\Exceptions\SmsException;
use App\Jobs\PollSmsOtpJob;
use App\Models\MerchantWidgetKey;
use App\Services\Pricing\PricingEngine;
use App\Services\SMS\NumberRequest;
use App\Services\SMS\SmsNumberRouter;
use Illuminate\Support\Facades\Cache;

/**
 * Verification numbers (one-time SMS codes) for Naara Widgets (MASTER-ONLY). What a merchant's shoppers can pick, and a LIVE merchant-lane quote for one pick.
 * The quote asks the provider lane for today's wholesale cost, runs it through the same PricingEngine the merchant storefront uses, and returns only the
 * shopper price: cost and provider never leave this class.
 */
class MerchantWidgetNumbers
{
    private const QUOTE_TTL_SECONDS = 45;

    /** @return list<array{id: string, name: string}> the services on offer, in the admin's order */
    public static function services(): array
    {
        $all = NumberCatalogue::services();
        $out = [];
        foreach (MerchantWidgetSettings::numberServices() as $slug) {
            if (isset($all[$slug])) {
                $out[] = ['id' => $slug, 'name' => (string) $all[$slug]];
            }
        }

        return $out;
    }

    /** @return list<array{code: string, name: string, dial: ?string}> */
    public static function countries(): array
    {
        $out = [];
        foreach (NumberCatalogue::offerableCountries() as $slug => $label) {
            $out[] = ['code' => (string) $slug, 'name' => (string) $label, 'dial' => DialCodes::for((string) $slug)];
        }

        return $out;
    }

    public static function serviceLabel(string $slug): string
    {
        return (string) (NumberCatalogue::services()[$slug] ?? ucfirst($slug));
    }

    public static function countryLabel(string $slug): string
    {
        return (string) (NumberCatalogue::countries()[$slug] ?? ucfirst($slug));
    }

    public static function valid(string $service, string $country): bool
    {
        return in_array($service, MerchantWidgetSettings::numberServices(), true) && isset(NumberCatalogue::services()[$service]) && isset(NumberCatalogue::countries()[$country]);
    }

    /** "otp:whatsapp:nigeria" */
    public static function ref(string $service, string $country): string
    {
        return 'otp:'.$service.':'.$country;
    }

    /** @return array{service: string, country: string}|null */
    public static function parse(?string $ref): ?array
    {
        $p = explode(':', (string) $ref, 3);

        return count($p) === 3 && $p[0] === 'otp' ? ['service' => $p[1], 'country' => $p[2]] : null;
    }

    /**
     * Today's shopper price for one service + country in this key's merchant lane.
     *
     * @return array{retail: float, price: float}
     *
     * @throws SmsException when nothing can serve it right now
     */
    public static function quote(MerchantWidgetKey $key, string $service, string $country): array
    {
        if (! self::valid($service, $country)) {
            throw new SmsException('That service or country is not available here.');
        }
        $merchant = $key->merchant;
        $cacheKey = 'mw:numquote:'.$merchant->id.':'.$service.':'.$country;
        if (($hit = Cache::get($cacheKey)) !== null) {
            return $hit;
        }
        $quote = app(SmsNumberRouter::class)->quote(new NumberRequest($country, NumberRequest::TYPE_OTP, $service, $merchant->owner));
        $pricing = app(PricingEngine::class);
        $out = [
            'retail' => round((float) $quote['retail'], 4),
            'price' => round($pricing->merchantSmsPrice((float) $quote['cost'], (string) $quote['provider'], $merchant, log: false), 4),
        ];
        Cache::put($cacheKey, $out, self::QUOTE_TTL_SECONDS);

        return $out;
    }

    public static function refundWindowMinutes(): int
    {
        return PollSmsOtpJob::TIMEOUT_MINUTES;
    }
}
