<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\StoreCallRecordingJob;
use App\Models\CallFlow;
use App\Models\WebhookLog;
use App\Services\SMS\VoiceProviderInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Twilio callbacks for a recorded business call (MASTER-ONLY, Business Suite Prompt 15). Same discipline as TwilioCallFlowController: the signature
 * is verified over the exact URL we gave (query string included) BEFORE anything in it is trusted. The consent evidence travels in that signed query
 * string, so it cannot be forged or altered by a caller.
 */
class TwilioCallRecordingController extends Controller
{
    /** Recording finished. Queue the store step; never download or bill in the request (money-safety rule 8). */
    public function status(Request $request, int $callFlow, int $node): Response
    {
        /** @var VoiceProviderInterface $voice */
        $voice = app('number.twilio');
        $verified = $voice->verifyWebhook($request, $this->signedUrl($request));

        WebhookLog::create([
            'provider' => 'twilio:call-recording',
            'event_type' => (string) $request->input('RecordingStatus'),
            'payload' => $request->except(['RecordingUrl']),
            'signature' => $request->header('X-Twilio-Signature'),
            'verified' => $verified,
            'processed' => false,
        ]);

        if (! $verified) {
            return response('', 403);
        }

        $flow = CallFlow::query()->find($callFlow);
        $ok = $flow && $flow->nodes()->whereKey($node)->exists();
        if ($ok && $request->input('RecordingStatus') === 'completed' && $request->filled('RecordingSid')) {
            $regime = (string) $request->query('rg');
            $timestamp = (int) $request->query('t', 0);

            StoreCallRecordingJob::dispatch([
                'flow_id' => $flow->id,
                'node_id' => $node,
                'call_sid' => (string) $request->input('CallSid'),
                'recording_sid' => (string) $request->input('RecordingSid'),
                'recording_url' => (string) $request->input('RecordingUrl'),
                'duration' => (int) $request->input('RecordingDuration', 0),
                'from' => (string) $request->query('from', ''),
                'to' => (string) $request->query('to', ''),
                'consent' => in_array($regime, ['notice', 'consent'], true) && $timestamp > 0 ? [
                    'regime' => $regime,
                    'action' => in_array($request->query('ca'), ['notice', 'pressed_1'], true) ? (string) $request->query('ca') : 'notice',
                    'announcement_version' => mb_substr((string) $request->query('av', ''), 0, 16),
                    'from_country' => preg_match('/^[A-Z]{2}$/', (string) $request->query('fc')) ? (string) $request->query('fc') : null,
                    'to_country' => preg_match('/^[A-Z]{2}$/', (string) $request->query('tc')) ? (string) $request->query('tc') : null,
                    'announced_at' => date('c', $timestamp),
                ] : null,
            ]);
        }

        return response('', 204);
    }

    /** Played to the person who answers, before they are connected. Static text, but verified like everything else on this surface. */
    public function whisper(Request $request): Response
    {
        /** @var VoiceProviderInterface $voice */
        $voice = app('number.twilio');
        if (! $voice->verifyWebhook($request, $this->signedUrl($request))) {
            return response('', 403);
        }

        $text = (string) config('call_recording.agent_notice');
        // A hand-off from the AI agent: tell the person who answers what it already covered (the session id is signed, so it cannot be guessed).
        $sid = (int) $request->query('s', 0);
        if ($sid > 0 && \App\Services\Business\AiVoice\AgentToken::ok($sid, (string) $request->query('t', ''))) {
            $summary = \App\Models\AiVoiceAgentSession::query()->whereKey($sid)->value('summary');
            $text = 'Call transferred from the assistant.'.($summary ? ' '.$summary : '');
        }
        $say = htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return response('<?xml version="1.0" encoding="UTF-8"?><Response><Say>'.$say.'</Say></Response>', 200)->header('Content-Type', 'text/xml');
    }

    private function signedUrl(Request $request): string
    {
        $query = (string) $request->server->get('QUERY_STRING', '');

        return $query === '' ? $request->url() : $request->url().'?'.$query;
    }
}
