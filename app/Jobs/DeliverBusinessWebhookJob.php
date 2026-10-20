<?php

namespace App\Jobs;

use App\Models\WebhookSubscription;
use App\Support\Security\SsrfGuard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/**
 * Delivers one signed webhook (MASTER-ONLY, Business Suite Prompt 18). The body is signed with the subscription's secret: HMAC-SHA256 over the RAW body, sent as
 * `X-Naara-Signature: sha256=<hex>`, the same shape Naara verifies on incoming provider webhooks (compare with hash_equals). Redirects are not followed and the
 * target is re-checked against the SSRF guard at delivery time (DNS can change after registration). A non-2xx answer is retried with backoff; when the retries
 * run out the failure is counted, and enough consecutive failures switch the subscription to `failed` so the owner sees it.
 */
class DeliverBusinessWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @return list<int> seconds between attempts */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function __construct(public int $subscriptionId, public string $deliveryId, public string $body)
    {
    }

    public function handle(): void
    {
        $sub = WebhookSubscription::find($this->subscriptionId);
        if (! $sub || ! $sub->isActive()) {
            return;
        }

        if (! SsrfGuard::isSafe($sub->target_url)) {
            $sub->forceFill(['status' => WebhookSubscription::FAILED, 'last_error' => 'The address is not a public https URL any more.'])->save();

            return;
        }

        $response = Http::withHeaders([
            'X-Naara-Event' => (string) (json_decode($this->body, true)['type'] ?? ''),
            'X-Naara-Delivery' => $this->deliveryId,
            'X-Naara-Timestamp' => (string) time(),
            'X-Naara-Signature' => 'sha256='.hash_hmac('sha256', $this->body, (string) $sub->secret),
            'User-Agent' => 'NaaraWebhooks/1.0',
        ])->withBody($this->body, 'application/json')->connectTimeout(5)->timeout(10)->withoutRedirecting()->post($sub->target_url);

        $sub->forceFill(['last_status_code' => $response->status(), 'last_delivery_at' => now()])->save();

        if (! $response->successful()) {
            throw new \RuntimeException('Webhook endpoint answered HTTP '.$response->status());
        }

        $sub->forceFill(['consecutive_failures' => 0, 'last_error' => null])->save();
    }

    /** Retries exhausted (or a connection error that exhausted them). */
    public function failed(\Throwable $e): void
    {
        $sub = WebhookSubscription::find($this->subscriptionId);
        if (! $sub) {
            return;
        }
        $failures = $sub->consecutive_failures + 1;
        $sub->forceFill([
            'consecutive_failures' => $failures,
            'last_error' => mb_substr($e->getMessage(), 0, 190),
            'status' => $failures >= WebhookSubscription::FAILURE_LIMIT ? WebhookSubscription::FAILED : $sub->status,
        ])->save();
    }
}
