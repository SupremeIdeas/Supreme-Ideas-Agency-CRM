<?php

namespace App\Services\Omni;

use App\Models\ChannelConnection;
use App\Services\Omni\Dto\NormalizedEvent;
use App\Services\Omni\Dto\OutboundDraft;
use Illuminate\Http\Request;

/**
 * The one contract every channel satisfies (MASTER-ONLY, blueprint section 3). The pipeline knows nothing about Telegram, WhatsApp or email: it only
 * calls these methods. An adapter never touches the database and never decides who a customer is; it verifies, parses and sends.
 */
interface ChannelAdapter
{
    public function channel(): string;

    /** HMAC or secret-token check of the raw request, constant time. False means "not from the provider": the controller answers 403 and stores nothing. */
    public function verify(Request $request, ChannelConnection $connection): bool;

    /** A stable id for the whole webhook body, so a replayed delivery collapses to one raw event. */
    public function eventId(array $payload): string;

    /** @return list<NormalizedEvent> */
    public function events(array $payload, ?ChannelConnection $connection = null): array;

    /** Whether a free-form reply needs an open 24-hour window (WhatsApp, Instagram, Messenger). */
    public function requiresWindow(): bool;

    /** Send one message. Returns the provider's message id; throws ChannelSendException on a refusal. Never retried blindly by the caller. */
    public function send(ChannelConnection $connection, OutboundDraft $draft): string;
}
