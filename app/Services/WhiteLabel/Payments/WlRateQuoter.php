<?php

namespace App\Services\WhiteLabel\Payments;

use App\Models\ManualPaymentRate;
use App\Models\Setting;
use Carbon\CarbonImmutable;

/**
 * How much a payer must send in their own currency so that, converted to USD at a trustworthy rate, it covers the
 * USD price (Funnel blueprint 5.3). Source order: Grey live quote, then the newest admin-entered manual rate that is
 * not older than the allowed age, otherwise NO quote (fail closed: a payer is never shown an amount from a stale or
 * missing rate).
 *
 *   amount_to_send = ceil_to_cents( price_usd / rate(X to USD) )
 *
 * The live rate is the NET effective rate (after Grey's fee), sized with a confirming quote so the amount we ask for
 * really converts to at least the price.
 */
class WlRateQuoter
{
    public const S_USE_LIVE = 'wl.rates.use_grey_live';

    public const S_MAX_AGE_HOURS = 'wl.rates.max_manual_age_hours';

    public const S_LOCK_FIAT_MIN = 'wl.rates.lock_fiat_minutes';

    public const S_LOCK_STABLE_MIN = 'wl.rates.lock_stable_minutes';

    private const STABLES = ['USDT', 'USDC'];

    public function __construct(private readonly GreyRateSource $grey)
    {
    }

    public static function useLive(): bool
    {
        return (bool) Setting::getValue(self::S_USE_LIVE, true);
    }

    /**
     * @return array{currency: string, amount: float, rate: float, source: string, as_of: CarbonImmutable, locked_until: CarbonImmutable}
     *
     * @throws ManualPaymentException when no trustworthy rate exists
     */
    public function amountToSend(string $currency, float $priceUsd): array
    {
        $currency = strtoupper($currency);
        $now = CarbonImmutable::now();
        if ($priceUsd <= 0) {
            throw new ManualPaymentException('There is nothing to pay.');
        }
        if ($currency === 'USD') {
            return ['currency' => 'USD', 'amount' => round($priceUsd, 2), 'rate' => 1.0, 'source' => 'none', 'as_of' => $now, 'locked_until' => $now->addMinutes($this->lockMinutes($currency))];
        }

        if (self::useLive() && ($live = $this->live($currency, $priceUsd)) !== null) {
            return $live + ['locked_until' => $now->addMinutes($this->lockMinutes($currency))];
        }

        $maxAge = max(1, (int) Setting::getValue(self::S_MAX_AGE_HOURS, 24));
        $row = ManualPaymentRate::query()->where(['from_currency' => $currency, 'to_currency' => 'USD', 'source' => ManualPaymentRate::SOURCE_MANUAL])
            ->where('as_of', '>=', $now->subHours($maxAge))->orderByDesc('as_of')->first();
        if ($row === null || (float) $row->rate <= 0) {
            throw new ManualPaymentException("A current {$currency} rate isn't available, so a payment amount can't be quoted right now.");
        }

        return [
            'currency' => $currency, 'amount' => $this->ceilCents($priceUsd / (float) $row->rate), 'rate' => (float) $row->rate,
            'source' => ManualPaymentRate::SOURCE_MANUAL, 'as_of' => CarbonImmutable::parse($row->as_of), 'locked_until' => $now->addMinutes($this->lockMinutes($currency)),
        ];
    }

    /** @return array{currency: string, amount: float, rate: float, source: string, as_of: CarbonImmutable}|null */
    private function live(string $currency, float $priceUsd): ?array
    {
        $probe = $this->grey->quote($currency, 'USD', round($priceUsd, 2));
        if ($probe === null) {
            return null;
        }
        $amount = $this->ceilCents($priceUsd / $probe['rate']);
        for ($i = 0; $i < 2; $i++) {
            $confirm = $this->grey->quote($currency, 'USD', $amount);
            if ($confirm === null) {
                return null;
            }
            if ($confirm['destination_amount'] + 0.00001 >= $priceUsd) {
                $rate = $confirm['destination_amount'] / $amount;
                ManualPaymentRate::create(['from_currency' => $currency, 'to_currency' => 'USD', 'rate' => round($rate, 8), 'source' => ManualPaymentRate::SOURCE_GREY, 'as_of' => now(), 'note' => 'Grey live quote (net of fees)']);

                return ['currency' => $currency, 'amount' => $amount, 'rate' => $rate, 'source' => ManualPaymentRate::SOURCE_GREY, 'as_of' => CarbonImmutable::now()];
            }
            $amount = $this->ceilCents($amount * ($priceUsd / $confirm['destination_amount']) * 1.0005);
        }

        return null; // would not converge: fail closed rather than quote a short amount
    }

    private function lockMinutes(string $currency): int
    {
        return in_array($currency, self::STABLES, true)
            ? max(5, (int) Setting::getValue(self::S_LOCK_STABLE_MIN, 30))
            : max(5, (int) Setting::getValue(self::S_LOCK_FIAT_MIN, 60));
    }

    private function ceilCents(float $v): float
    {
        return ceil(round($v * 100, 6)) / 100;
    }
}
