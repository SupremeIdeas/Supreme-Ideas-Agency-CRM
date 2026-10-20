<?php

namespace App\Jobs\Omni;

use App\Models\Omni\OmniWebhook;
use App\Services\Omni\Kb\SafeFetcher;
use App\Services\Omni\Webhooks\OutboundWebhooks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

/** Delivers one outbound webhook (MASTER-ONLY): signed, public addresses only, 3 tries with backoff, switched off after 10 failures in a row. */
class SendOmniWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $hookId, public string $event, public array $data)
    {
    }

    public function backoff(): array
    {
        return [30, 300];
    }

    public function handle(SafeFetcher $guard): void
    {
        $hook = OmniWebhook::query()->find($this->hookId);
        if (! $hook || ! $hook->active) {
            return;
        }
        $body = json_encode(['event' => $this->event, 'workspace_id' => $hook->workspace_id, 'data' => $this->data, 'sent_at' => now()->toIso8601String()]);
        $ts = time();
        try {
            $guard->assertPublic((string) $hook->url);
            $r = Http::timeout(8)->withOptions(['allow_redirects' => false])->withHeaders([
                'Content-Type' => 'application/json', 'X-Naara-Event' => $this->event, 'X-Naara-Timestamp' => (string) $ts, 'X-Naara-Signature' => OutboundWebhooks::sign((string) $hook->secret, $ts, $body),
            ])->withBody($body, 'application/json')->post((string) $hook->url);
            $ok = $r->successful();
            $status = $r->status();
        } catch (\Throwable) {
            $ok = false;
            $status = 0;
        }
        $hook->forceFill(['last_status' => $status, 'last_at' => now(), 'failures' => $ok ? 0 : $hook->failures + 1])->save();
        if (! $ok) {
            if ($hook->failures >= 10) {
                $hook->forceFill(['active' => false])->save();

                return;
            }
            $this->release(60);
        }
    }
}
