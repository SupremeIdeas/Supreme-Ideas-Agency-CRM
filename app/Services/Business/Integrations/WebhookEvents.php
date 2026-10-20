<?php

namespace App\Services\Business\Integrations;

use App\Jobs\DeliverBusinessWebhookJob;
use App\Models\WebhookSubscription;
use App\Support\Business\BusinessSettings;
use Illuminate\Support\Str;

/**
 * The single dispatch call added at the places an event already happens (MASTER-ONLY, Business Suite Prompt 18): it is NOT an event bus. A call site says
 * "this happened in workspace N"; this finds the active subscriptions for that workspace and event and queues one delivery each. Anything that goes wrong here
 * is swallowed: an integration problem must never break the call or message it was reporting.
 */
class WebhookEvents
{
    /** @param array<string,mixed> $data */
    public static function fire(string $event, ?int $walletGroupId, array $data): void
    {
        if (! $walletGroupId || ! in_array($event, WebhookSubscription::EVENTS, true)) {
            return;
        }

        try {
            if (! BusinessSettings::module('integrations')) {
                return;
            }
            $group = \App\Models\WalletGroup::find($walletGroupId);
            if ($group === null || ! app(\App\Services\Business\BusinessAccess::class)->allowsWorkspace($group, 'integrations')) {
                return;   // the owner's plan no longer includes integrations: no new deliveries
            }
            $subs = WebhookSubscription::query()->where('wallet_group_id', $walletGroupId)->where('event_type', $event)->where('status', WebhookSubscription::ACTIVE)
                ->with('apiClient.owner')->get()->filter(fn (WebhookSubscription $s) => $s->apiClient?->usable());

            foreach ($subs as $sub) {
                $id = (string) Str::uuid();
                $body = json_encode(['id' => $id, 'type' => $event, 'created_at' => now()->toIso8601String(), 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                DeliverBusinessWebhookJob::dispatch($sub->id, $id, $body);
            }
        } catch (\Throwable) {
            // never let an integration problem break the call or message that triggered it
        }
    }
}
