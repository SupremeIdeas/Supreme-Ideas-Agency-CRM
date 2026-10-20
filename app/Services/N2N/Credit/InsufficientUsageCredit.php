<?php

namespace App\Services\N2N\Credit;

use RuntimeException;

/** Not enough usage credit. The UI answers with Buy and Ask-a-friend-to-share, never a bare error (N2N, MASTER-ONLY). */
class InsufficientUsageCredit extends RuntimeException
{
    public function __construct(public readonly int $neededCents, public readonly int $availableCents)
    {
        parent::__construct('Not enough NaaraCredit: need '.Money::credits($neededCents).', have '.Money::credits($availableCents).'.');
    }
}
