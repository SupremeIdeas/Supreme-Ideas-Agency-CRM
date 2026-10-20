<?php

namespace App\Services\N2N\Brands;

use RuntimeException;

/** A brand profile limit or validation rule was hit (N2N Batch 8, MASTER-ONLY). The message is safe to show the owner. */
class BrandLimitException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
