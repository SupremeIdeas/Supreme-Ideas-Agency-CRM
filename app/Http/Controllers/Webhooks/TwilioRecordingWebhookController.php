<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\RecordVoicemailJob;
use App\Models\CallFlow;
use App\Models\CallForwardingRule;
use App\Models\WebhookLog;
use App\Services\SMS\VoiceProviderInterface;
use App\Services\Voice\VoiceProviders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Voicemail recording webhook (Prompt 11) — Twilio POSTs here when a
 * <Record> started by TwilioVoiceWebhookController finishes. Verified BEFORE
 * trusting it (money-safety rule 9), same as the voice webhook. Resolves the
 * owning user via the forwarding rule for the called number and queues the
 * download + storage (never synchronous — rule 8).
 */
class TwilioRecordingWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $provider = (string) ($request->route()?->defaults['voice_provider'] ?? 'twilio');
        /** @var VoiceProviderInterface $voice */
        $voice = VoiceProviders::for($provider);
        $verified = $voice->verifyWebhook($request, $request->url());

        $to = (string) $request->input('To', '');
        $from = (string) $request->input('From', '');
        $recordingUrl = (string) $request->input('RecordingUrl', '');
        $recordingSid = (string) $request->input('RecordingSid', '');
        $duration = (int) $request->input('RecordingDuration', 0);

        WebhookLog::create([
            'provider' => $provider.':recording',
            'event_type' => 'voicemail',
            'payload' => $request->all(),
            'signature' => $request->header($provider === 'telnyx' ? 'telnyx-signature-ed25519' : 'X-Twilio-Signature'),
            'verified' => $verified,
            'processed' => false,
        ]);

        if (! $verified) {
            return $this->twiml('<Reject/>', 403);
        }

        $rule = CallForwardingRule::query()
            ->where('twilio_number', $to)
            ->where('status', CallForwardingRule::ACTIVE)
            ->forProvider($provider)
            ->first();

        if ($rule !== null && $recordingUrl !== '' && $recordingSid !== '') {
            RecordVoicemailJob::dispatch(
                userId: $rule->user_id,
                virtualNumberId: $rule->virtual_number_id,
                fromNumber: $from,
                toNumber: $to,
                recordingUrl: $recordingUrl,
                recordingSid: $recordingSid,
                durationSeconds: max(0, $duration),
                provider: $provider,
            );
        }

        // Business Phone Core (Prompt 14): a voicemail node on an org number
        // has no forwarding rule — file it in the workspace owner's inbox.
        if ($rule === null && $recordingUrl !== '' && $recordingSid !== '') {
            $flow = CallFlow::query()
                ->whereHas('virtualNumber', fn ($q) => $q->where('phone_number', $to))
                ->where('status', CallFlow::ACTIVE)
                ->first();

            if ($flow?->walletGroup) {
                RecordVoicemailJob::dispatch(
                    userId: $flow->walletGroup->owner_user_id,
                    virtualNumberId: $flow->virtual_number_id,
                    fromNumber: $from,
                    toNumber: $to,
                    recordingUrl: $recordingUrl,
                    recordingSid: $recordingSid,
                    durationSeconds: max(0, $duration),
                );
            }
        }

        return $this->twiml('<Hangup/>');
    }

    private function twiml(string $xml, int $status = 200): Response
    {
        if (! str_starts_with($xml, '<?xml')) {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>'.(str_starts_with($xml, '<Response') ? $xml : '<Response>'.$xml.'</Response>');
        }

        return response($xml, $status)->header('Content-Type', 'text/xml');
    }
}
