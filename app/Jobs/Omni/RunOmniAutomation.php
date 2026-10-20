<?php

namespace App\Jobs\Omni;

use App\Models\Omni\OmniMessage;
use App\Services\Omni\Agent\AgentRunner;
use App\Services\Omni\Flows\FlowEngine;
use App\Services\Omni\Webhooks\OutboundWebhooks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * What happens after a customer message is filed (MASTER-ONLY): outbound webhooks, then a matching flow, else the AI agent (a flow that answered keeps the
 * agent quiet for that message). Runs in the queue, so no model call or external request ever happens in the webhook request.
 */
class RunOmniAutomation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $messageId)
    {
    }

    public function handle(FlowEngine $flows, AgentRunner $agent, OutboundWebhooks $hooks): void
    {
        $m = OmniMessage::query()->find($this->messageId);
        if (! $m || $m->direction !== 'in') {
            return;
        }
        $this->optInOut($m);
        $hooks->emit((int) $m->workspace_id, 'message.received', ['conversation_id' => $m->conversation_id, 'message_id' => $m->id, 'text' => $m->body]);
        if ($flows->handleInbound($m) === null) {
            $agent->handle($m);
        }
    }

    /** STOP removes a customer from broadcasts at once; START puts them back. Exact words only, so a sentence containing "stop" is never misread. */
    private function optInOut(OmniMessage $m): void
    {
        $t = mb_strtolower(trim((string) $m->body));
        $conv = \App\Models\Omni\OmniConversation::query()->forWorkspace((int) $m->workspace_id)->find($m->conversation_id);
        $contact = $conv ? \App\Models\Omni\OmniContact::query()->forWorkspace((int) $m->workspace_id)->find($conv->contact_id) : null;
        if (! $contact) {
            return;
        }
        if (in_array($t, (array) config('omni.stop_words'), true)) {
            $contact->forceFill(['marketing_opt_in' => false, 'opted_out_at' => now()])->save();
        } elseif (in_array($t, (array) config('omni.start_words'), true)) {
            $contact->forceFill(['marketing_opt_in' => true, 'opted_out_at' => null])->save();
        }
    }
}
