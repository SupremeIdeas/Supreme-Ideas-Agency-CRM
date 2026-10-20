<?php

namespace App\Services\WhiteLabel\Payments;

use Illuminate\Support\Facades\Http;

/**
 * Live rates from the Grey Business API (`POST /v1/currency/rate`, a read-only quote). The `swap` quote is the one that
 * answers for X to USD (the `deposit` type answers "deposit channel is empty"). `destination_amount` is NET of Grey's
 * fee (0.5% on GBP/EUR, none on USDT/USDC at the time of writing), which is the conservative value to credit.
 * Uses the same Grey key as payouts; a read of a quote moves no money.
 */
class GreyRateSource
{
    public function configured(): bool
    {
        return filled(config('services.grey.api_secret'));
    }

    /** @return array{source_amount: float, destination_amount: float, rate: float}|null null when Grey cannot be reached or refuses */
    public function quote(string $from, string $to, float $amount): ?array
    {
        if (! $this->configured() || $amount <= 0) {
            return null;
        }
        try {
            $r = Http::withToken((string) config('services.grey.api_secret'))->acceptJson()->asJson()->connectTimeout(3)->timeout(12)
                ->post(rtrim((string) config('services.grey.base_url', 'https://businessapi-sandbox.grey.co'), '/').'/v1/currency/rate', [
                    'source_currency' => strtoupper($from), 'destination_currency' => strtoupper($to), 'source_amount' => $amount, 'transaction_type' => 'swap',
                ]);
        } catch (\Throwable) {
            return null;
        }
        $dest = (float) data_get($r->json(), 'data.destination_amount', 0);
        if (! $r->successful() || $dest <= 0) {
            return null;
        }

        return ['source_amount' => $amount, 'destination_amount' => $dest, 'rate' => $dest / $amount];
    }
}
