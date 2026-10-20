<?php

namespace App\Jobs;

use App\Services\SMS\VoiceProviderInterface;
use App\Services\Voice\VoiceProviders;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Point (or clear) a provisioned number's inbound voice handling (Twilio Voice URL, or a Telnyx TeXML application) at our
 * TwiML webhook (Live Voice — Part A). Runs on the queue so the external Twilio config
 * call never blocks the request cycle (money-safety rule 8). Idempotent — safe
 * to re-run; a failure retries with backoff.
 */
class SyncVoiceWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $numberSid, public string $voiceUrl, public bool $attach = true, public string $provider = 'twilio')
    {
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(): void
    {
        /** @var VoiceProviderInterface $voice */
        $voice = VoiceProviders::for($this->provider);
        if (! $voice->available() || $this->numberSid === '') {
            return;
        }

        $this->attach
            ? $voice->attachVoiceWebhook($this->numberSid, $this->voiceUrl)
            : $voice->detachVoiceWebhook($this->numberSid);
    }
}
