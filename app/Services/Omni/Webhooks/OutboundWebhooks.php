<?php

namespace App\Services\Omni\Webhooks;

use App\Jobs\Omni\SendOmniWebhook;
use App\Models\Omni\OmniWebhook;
use App\Services\Omni\Kb\SafeFetcher;
use Illuminate\Support\Str;

/**
 * Outbound webhooks (MASTER-ONLY, blueprint section 11): a workspace gives us an address and picks events; we POST a signed JSON body when they happen.
 * The address is checked against the same public-network guard as the knowledge-base crawler, so a webhook can never be aimed at the platform's own network.
 */
class OutboundWebhooks
{
    public const EVENTS = ['message.received', 'message.sent', 'conversation.resolved'];

    public const MAX_PER_WORKSPACE = 10;

    public function __construct(private readonly SafeFetcher $guard)
    {
    }

    /** @throws \RuntimeException */
    public function create(int $ws, string $url, array $events): OmniWebhook
    {
        $url = trim($url);
        if (! str_starts_with(strtolower($url), 'https://')) {
            throw new \RuntimeException('Webhook addresses must start with https://.');
        }
        $this->guard->assertPublic($url);
        $events = array_values(array_intersect($events, self::EVENTS));
        if ($events === []) {
            throw new \RuntimeException('Pick at least one event.');
        }
        if (OmniWebhook::query()->forWorkspace($ws)->count() >= self::MAX_PER_WORKSPACE) {
            throw new \RuntimeException('You can have up to '.self::MAX_PER_WORKSPACE.' webhooks.');
        }

        return OmniWebhook::create(['workspace_id' => $ws, 'url' => mb_substr($url, 0, 500), 'secret' => Str::random(40), 'events' => $events, 'active' => true]);
    }

    public function emit(int $ws, string $event, array $data): void
    {
        foreach (OmniWebhook::query()->forWorkspace($ws)->where('active', true)->get() as $hook) {
            if (in_array($event, (array) $hook->events, true)) {
                SendOmniWebhook::dispatch($hook->id, $event, $data);
            }
        }
    }

    /** The signature a receiver recomputes: HMAC-SHA256 of "timestamp.body" with the webhook's secret. */
    public static function sign(string $secret, int $timestamp, string $body): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }
}
