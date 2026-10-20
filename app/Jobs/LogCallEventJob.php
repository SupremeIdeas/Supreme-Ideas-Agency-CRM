<?php

namespace App\Jobs;

use App\Models\CallEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Record an inbound-call event on a forwarded number (Live Voice — Part A).
 * Runs on the queue so the voice webhook returns TwiML immediately (money-safety
 * rule 8 — external-API side effects are queued, never synchronous).
 *
 * One table serves both inbound paths: a CallForwardingRule call carries
 * `rule_id`; a Business Phone Core call (Prompt 14) carries `call_flow_id` +
 * `call_flow_node_id` and a null `rule_id`.
 *
 * @param array{user_id:?int, rule_id?:?int, call_flow_id?:?int, call_flow_node_id?:?int, call_sid:?string, from:?string, to:?string, status:string} $data
 */
class LogCallEventJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param array<string,mixed> $data */
    public function __construct(public array $data)
    {
    }

    public function handle(): void
    {
        $event = CallEvent::create([
            'user_id' => $this->data['user_id'] ?? null,
            'call_forwarding_rule_id' => $this->data['rule_id'] ?? null,
            'call_flow_id' => $this->data['call_flow_id'] ?? null,
            'call_flow_node_id' => $this->data['call_flow_node_id'] ?? null,
            'call_sid' => $this->data['call_sid'] ?? null,
            'from_number' => $this->data['from'] ?? null,
            'to_number' => $this->data['to'] ?? null,
            'status' => $this->data['status'] ?? 'ringing',
        ]);

        // Business Suite integrations (MASTER-ONLY): a workspace call that has reached an outcome is reported to its webhooks. Guarded so a stray copy never half-wires it.
        if ($event->call_flow_id && in_array($event->status, ['answered', 'no-answer'], true) && class_exists(\App\Services\Business\Integrations\WebhookEvents::class)) {
            \App\Services\Business\Integrations\WebhookEvents::fire('call.completed', \App\Models\CallFlow::query()->whereKey($event->call_flow_id)->value('wallet_group_id'), [
                'call_id' => $event->call_sid, 'from' => $event->from_number, 'to' => $event->to_number, 'status' => $event->status,
            ]);
        }
    }
}
