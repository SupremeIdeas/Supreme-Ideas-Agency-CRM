<?php

namespace App\Services\N2N\Credit;

/** Credit amounts as integer hundredths: exact, no float comparisons on money (N2N, MASTER-ONLY). */
final class Money
{
    public static function cents(float|int|string $credits): int
    {
        return (int) round(((float) $credits) * 100);
    }

    public static function credits(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /** Round UP to the next hundredth, so a metered price is never rounded below cost. */
    public static function ceilCents(float $credits): int
    {
        return (int) ceil(round($credits * 100, 6));
    }
}
