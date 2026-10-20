<?php

namespace App\Services\Omni\Dto;

/** What the platform asks an adapter to send (MASTER-ONLY). */
final class OutboundDraft
{
    public function __construct(
        public readonly string $externalId,
        public readonly ?string $text,
        public readonly array $media = [],
        public readonly ?string $idempotencyKey = null,
        public readonly ?array $template = null,   // an approved WhatsApp template: {name, language, params: list<string>}
    ) {
    }
}
