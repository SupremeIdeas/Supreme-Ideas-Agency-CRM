<?php

namespace App\Services\N2N\Group;

use RuntimeException;

/** A group-call action was refused (N2N Batch 11, MASTER-ONLY). The message is safe to show; `reason` is machine-readable. */
class GroupCallException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return match ($this->reason) {
            'not_found', 'link_invalid' => 404,
            'forbidden', 'banned' => 403,
            'unavailable' => 503,
            'rate_limited' => 429,
            'no_credit' => 402,
            default => 422,
        };
    }
}
