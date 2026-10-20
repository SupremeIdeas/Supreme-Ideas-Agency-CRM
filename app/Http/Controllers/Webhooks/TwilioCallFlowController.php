<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\CallFlow;
use App\Models\WebhookLog;
use App\Services\SMS\VoiceProviderInterface;
use App\Services\Voice\CallFlowResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Continuation callbacks for a Business Phone Core call flow (Prompt 14):
 * a menu's `<Gather>` digits, a `<Redirect>` to the next node, and a queue
 * `<Dial>` leg finishing. Same discipline as TwilioVoiceWebhookController —
 * the Twilio signature is verified over the FULL url (these carry query
 * counters) BEFORE any id in the path is trusted or even looked up.
 */
class TwilioCallFlowController extends Controller
{
    public function node(Request $request, int $callFlow, int $node): Response
    {
        return $this->handle($request, $callFlow, $node, 'twilio:call-flow',
            fn (CallFlowResolver $r, $flow, $n) => $r->nodeTwiml($flow, $n, $request));
    }

    public function digits(Request $request, int $callFlow, int $node): Response
    {
        return $this->handle($request, $callFlow, $node, 'twilio:call-flow-digits',
            fn (CallFlowResolver $r, $flow, $n) => $r->digitsTwiml($flow, $n, $request));
    }

    public function dial(Request $request, int $callFlow, int $node): Response
    {
        return $this->handle($request, $callFlow, $node, 'twilio:call-flow-dial',
            fn (CallFlowResolver $r, $flow, $n) => $r->dialResultTwiml($flow, $n, $request));
    }

    /** The AI agent conversation ended: bill and file it, then hand over to the ring group if the agent asked for that. */
    public function aiAgent(Request $request, int $callFlow, int $node): Response
    {
        return $this->handle($request, $callFlow, $node, 'twilio:ai-agent',
            fn (CallFlowResolver $r, $flow, $n) => $r->aiAgentContinueTwiml($flow, $n, $request));
    }

    private function handle(Request $request, int $callFlowId, int $nodeId, string $log, \Closure $build): Response
    {
        /** @var VoiceProviderInterface $voice */
        $voice = app('number.twilio');
        $verified = $voice->verifyWebhook($request, $this->signedUrl($request));

        WebhookLog::create([
            'provider' => $log,
            'event_type' => $request->input('DialCallStatus') ?: $request->input('CallStatus'),
            'payload' => $request->all(),
            'signature' => $request->header('X-Twilio-Signature'),
            'verified' => $verified,
            'processed' => false,
        ]);

        if (! $verified) {
            return $this->twiml('<Reject/>', 403);
        }

        $flow = CallFlow::query()->where('status', CallFlow::ACTIVE)->find($callFlowId);
        $node = $flow?->nodes()->find($nodeId);

        if (! $flow || ! $node) {
            return $this->twiml('<Response><Say>Sorry, this line is not available right now. Goodbye.</Say><Hangup/></Response>');
        }

        return $this->twiml($build(new CallFlowResolver($voice), $flow, $node));
    }

    /**
     * Twilio signs the URL byte-for-byte as WE gave it, query string included.
     * Request::fullUrl() re-sorts query keys, which would break the HMAC for
     * any URL whose params weren't already alphabetical — so rebuild it from
     * the raw QUERY_STRING instead.
     */
    private function signedUrl(Request $request): string
    {
        $query = (string) $request->server->get('QUERY_STRING', '');

        return $query === '' ? $request->url() : $request->url().'?'.$query;
    }

    private function twiml(string $xml, int $status = 200): Response
    {
        if (! str_starts_with($xml, '<?xml')) {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>'.(str_starts_with($xml, '<Response') ? $xml : '<Response>'.$xml.'</Response>');
        }

        return response($xml, $status)->header('Content-Type', 'text/xml');
    }
}
