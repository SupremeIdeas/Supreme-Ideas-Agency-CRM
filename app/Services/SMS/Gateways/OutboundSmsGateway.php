<?php

namespace App\Services\SMS\Gateways;

/**
 * A send-only SMS route to a destination, from a sender ID (not from a leased Naara number). This is deliberately a different
 * contract from NumberProviderInterface: a regional aggregator owns no number a user can lease, so it can never be a lane
 * member, and it can never receive a reply. Use it only where the sender is the platform or a registered sender ID.
 */
interface OutboundSmsGateway
{
    /** Provider key (matches ProviderStatus / ProviderHealth / provider_registry). */
    public function key(): string;

    /** Credentials present. */
    public function available(): bool;

    /** Whether this gateway is allowed to carry traffic to the destination's country (admin allow-list; empty = none). */
    public function coversDestination(string $e164): bool;

    /**
     * Wholesale cost basis in USD per message part. Admin-set; never derived from the provider's reply, whose cost is in a
     * local currency string. Server-side only: PricingEngine applies the margin, the cost is never shown to a user.
     */
    public function costPerPart(string $e164): float;

    /**
     * @return array{provider_ref: string, status: string, parts: int}
     *
     * @throws \App\Exceptions\SmsException when the provider rejects the message or is unreachable
     */
    public function send(string $e164, string $body, ?string $senderId = null): array;
}
