<?php

namespace App\Services\Omni\Dto;

use Illuminate\Support\Carbon;

/** One thing a channel told us, in the platform's own shape (MASTER-ONLY): a customer message or a delivery status of one of ours. */
final class NormalizedEvent
{
    public const MESSAGE = 'message';

    public const STATUS = 'status';

    public function __construct(
        public readonly string $type,
        public readonly string $externalId,            // the customer's id on the channel
        public readonly ?string $providerMessageId = null,
        public readonly ?string $text = null,
        public readonly array $media = [],
        public readonly ?string $name = null,
        public readonly ?string $phone = null,
        public readonly ?string $email = null,
        public readonly ?string $status = null,        // for STATUS: sent | delivered | read | failed
        public readonly ?string $error = null,
        public readonly ?Carbon $at = null,
    ) {
    }
}
