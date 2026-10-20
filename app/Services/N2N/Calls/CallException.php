<?php

namespace App\Services\N2N\Calls;

use RuntimeException;

/** A call could not be placed or changed (N2N Batch 10, MASTER-ONLY). `code` is machine-readable, the message is safe to show. */
class CallException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
