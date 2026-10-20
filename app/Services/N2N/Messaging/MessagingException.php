<?php

namespace App\Services\N2N\Messaging;

use RuntimeException;

/** A refusal the API maps to an HTTP status: not_participant 403, not_found 404, link_blocked 422, invalid 422, rate_limited 429, request_cap 429, pro_required 403, type_not_allowed 415, too_large 413, size_mismatch 422 (N2N, MASTER-ONLY). */
class MessagingException extends RuntimeException
{
    public const STATUS = ['not_participant' => 403, 'restricted' => 403, 'not_found' => 404, 'link_blocked' => 422, 'invalid' => 422, 'rate_limited' => 429, 'request_cap' => 429, 'pro_required' => 403, 'type_not_allowed' => 415, 'too_large' => 413, 'size_mismatch' => 422];

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return self::STATUS[$this->reason] ?? 422;
    }
}
