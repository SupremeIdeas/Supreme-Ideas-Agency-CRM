<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\LogCallEventJob;
use App\Models\CallFlow;
use App\Support\Business\BusinessSettings;
use App\Models\CallForwardingRule;
use App\Models\WebhookLog;
use App\Services\SMS\VoiceProviderInterface;
use App\Services\Voice\VoiceProviders;
use App\Services\Voice\CallFlowResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Inbound-call webhook for forwarded permanent numbers (Live Voice — Part A).
 * Twilio POSTs here when a call reaches a NaaraSim number; we verify the request
 * signature (money-safety rule 9) BEFORE trusting it, resolve the forwarding
 * rule for the called number, and return TwiML that dials the user's target
 * (with a fallback on no-answer). Call activity is logged in a queued job so the
 * response stays instant (rule 8). No active rule → a polite reject.
 */
class TwilioVoiceWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // One controller serves both routes: TeXML (Telnyx) is TwiML-compatible, so only the driver and the signature differ.
        $provider = (string) ($request->route()?->defaults['voice_provider'] ?? 'twilio');
        /** @var VoiceProviderInterface $voice */
        $voice = VoiceProviders::for($provider);
        $verified = $voice->verifyWebhook($request, $request->url());

        $to = (string) $request->input('To', '');
        $from = (string) $request->input('From', '');

        WebhookLog::create([
            'provider' => $provider.':voice',
            'event_type' => $request->input('CallStatus'),
            'payload' => $request->all(),
            'signature' => $request->header($provider === 'telnyx' ? 'telnyx-signature-ed25519' : 'X-Twilio-Signature'),
            'verified' => $verified,
            'processed' => false,
        ]);

        if (! $verified) {
            return $this->twiml('<Reject/>', 403);
        }

        // Scoped to the provider that owns the number: a call arriving on one stack never triggers a rule on another.
        // Business Phone Core (Prompt 14 §3.1): an org-owned number with an
        // ACTIVE call flow is answered by that flow and never falls through
        // to the forwarding-rule lookup. No active flow → everything below
        // runs exactly as it always has.
        // Master switch off, or the Call flows module off => this block is skipped entirely and the forwarding path below runs as it always did.
        $callFlow = BusinessSettings::module('call_flows')
            ? CallFlow::query()
                ->whereHas('virtualNumber', fn ($q) => $q->where('phone_number', $to)->where('provider', $provider))
                ->where('status', CallFlow::ACTIVE)
                ->first()
            : null;

        // A workspace whose owner's plan does not include call flows (or has ended) falls back to plain forwarding, exactly like a switched-off module.
        if ($callFlow !== null && $callFlow->walletGroup !== null && ! app(\App\Services\Business\BusinessAccess::class)->allowsWorkspace($callFlow->walletGroup, 'call_flows')) {
            $callFlow = null;
        }

        if ($callFlow !== null) {
            return $this->twiml((new CallFlowResolver($voice))->buildTwiml($callFlow, $request));
        }

        $rule = CallForwardingRule::query()
            ->where('twilio_number', $to)
            ->where('status', CallForwardingRule::ACTIVE)
            ->forProvider($provider)
            ->first();

        if ($rule === null) {
            // Nobody's forwarding this number — reject gracefully.
            return $this->twiml('<Response><Reject reason="rejected"/></Response>');
        }

        LogCallEventJob::dispatch([
            'user_id' => $rule->user_id, 'rule_id' => $rule->id, 'call_sid' => $request->input('CallSid'),
            'from' => $from, 'to' => $to, 'status' => 'ringing',
        ]);

        // Preserve the original caller's number as caller ID so the user knows
        // who's actually calling. Voicemail (Prompt 11): if neither the primary
        // nor the fallback number answers, TwiML falls through to a recording.
        return $this->twiml($voice->forwardTwiml(
            $rule->forward_to_number,
            $from ?: null,
            $rule->fallback_number,
            route('webhooks.'.$provider.'.recording'),
        ));
    }

    private function twiml(string $xml, int $status = 200): Response
    {
        if (! str_starts_with($xml, '<?xml')) {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>'.(str_starts_with($xml, '<Response') ? $xml : '<Response>'.$xml.'</Response>');
        }

        return response($xml, $status)->header('Content-Type', 'text/xml');
    }
}
