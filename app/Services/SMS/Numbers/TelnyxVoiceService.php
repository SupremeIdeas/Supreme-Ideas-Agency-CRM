<?php

namespace App\Services\SMS\Numbers;

use App\Exceptions\OutOfStockException;
use App\Models\Setting;
use App\Services\SMS\VoiceProviderInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Telnyx voice (TeXML) behind the same VoiceProviderInterface Twilio implements, so call forwarding and voicemail work on a
 * Telnyx Naara Line. Bound as `voice.telnyx`; resolved through App\Services\Voice\VoiceProviders.
 *
 * How it maps (endpoints confirmed against developers.telnyx.com, 2026-10-06):
 *  - TeXML is Telnyx's TwiML-compatible XML, so forwardTwiml() emits the same <Dial>/<Say>/<Record> document as Twilio.
 *  - Telnyx has no per-number voice URL: the URL lives on the TeXML Application, and a number points at the application.
 *    attachVoiceWebhook() therefore syncs the application's voice_url (PATCH /texml_applications/{id}) and assigns the
 *    number to it (PATCH /phone_numbers/{id}/voice {connection_id}). detach clears the assignment.
 *  - Outbound bridge: POST /texml/Accounts/{account_sid}/Calls {To, From, ApplicationSid, Url}.
 *  - Webhooks are signed with Ed25519 (telnyx-signature-ed25519 + telnyx-timestamp, signed "{timestamp}|{raw body}") and
 *    checked against the public key from the Mission Control Portal.
 *  - Browser WebRTC login: POST /telephony_credentials, then POST /telephony_credentials/{id}/token (a JWT, valid 24 h).
 *
 * NOT verified without a Telnyx sandbox (do these before enabling, same rule as every provider here): that TeXML webhooks
 * use the same Ed25519 signing and the "|" joiner as event webhooks; the exact response field that carries the call id on
 * the outbound-call POST; that connection_id may be cleared with null. Every one is isolated to one method below.
 *
 * The in-browser dialer is deliberately still Twilio-only: it loads Twilio's Voice SDK. accessToken() is here and tested so
 * that swapping the browser SDK later is a front-end task, not a back-end one.
 */
class TelnyxVoiceService implements VoiceProviderInterface
{
    private const SIGNATURE_TOLERANCE_SECONDS = 300;

    public function available(): bool
    {
        return ! empty(config('services.telnyx.api_key')) && ! empty(config('services.telnyx.texml_app_id'));
    }

    public function attachVoiceWebhook(string $numberSid, string $voiceUrl): void
    {
        if (! $this->available()) {
            return;
        }
        $app = (string) config('services.telnyx.texml_app_id');
        $this->client()->patch('/texml_applications/'.$app, ['voice_url' => $voiceUrl, 'voice_method' => 'post'])->throw();
        $this->client()->patch('/phone_numbers/'.$numberSid.'/voice', ['connection_id' => $app])->throw();
    }

    public function detachVoiceWebhook(string $numberSid): void
    {
        if (! $this->available()) {
            return;
        }
        $this->client()->patch('/phone_numbers/'.$numberSid.'/voice', ['connection_id' => null])->throw();
    }

    /**
     * Ed25519 over "{telnyx-timestamp}|{raw body}". Rejects a missing key, a malformed signature, a stale timestamp (replay
     * window 5 min) and anything that fails the check. Never throws: false means "do not trust this request".
     */
    public function verifyWebhook(Request $request, string $url): bool
    {
        $signature = $request->header('telnyx-signature-ed25519');
        $timestamp = $request->header('telnyx-timestamp');
        $publicKey = (string) config('services.telnyx.public_key');
        if (! is_string($signature) || ! is_string($timestamp) || $publicKey === '' || ! ctype_digit($timestamp)) {
            return false;
        }
        if (abs(time() - (int) $timestamp) > self::SIGNATURE_TOLERANCE_SECONDS) {
            return false;
        }

        $sig = base64_decode($signature, true);
        $key = base64_decode($publicKey, true);
        if ($sig === false || $key === false || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES || strlen($key) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached($sig, $timestamp.'|'.$request->getContent(), $key);
        } catch (\Throwable) {
            return false;
        }
    }

    public function forwardTwiml(string $to, ?string $callerId = null, ?string $fallback = null, ?string $voicemailActionUrl = null): string
    {
        $caller = $callerId ? ' callerId="'.htmlspecialchars($callerId, ENT_QUOTES).'"' : '';
        $dial = '<Dial'.$caller.' timeout="20"><Number>'.htmlspecialchars($to, ENT_QUOTES).'</Number></Dial>';
        if ($fallback) {
            $dial .= '<Dial'.$caller.'><Number>'.htmlspecialchars($fallback, ENT_QUOTES).'</Number></Dial>';
        }
        if ($voicemailActionUrl) {
            $dial .= '<Say>Please leave a message after the tone.</Say>'
                .'<Record maxLength="120" playBeep="true" action="'.htmlspecialchars($voicemailActionUrl, ENT_QUOTES).'" />';
        }

        return '<?xml version="1.0" encoding="UTF-8"?><Response>'.$dial.'</Response>';
    }

    public function bridgeCall(string $from, string $to, string $twimlUrl): string
    {
        $account = (string) config('services.telnyx.account_sid');
        $app = (string) config('services.telnyx.texml_app_id');
        if (! $this->available() || $account === '') {
            throw new OutOfStockException('Telnyx voice is not configured.');
        }

        $json = $this->client()->post('/texml/Accounts/'.$account.'/Calls', [
            'To' => $to, 'From' => $from, 'Url' => $twimlUrl, 'ApplicationSid' => $app,
        ])->throw()->json();

        return (string) (data_get($json, 'call_sid') ?: data_get($json, 'data.call_sid') ?: data_get($json, 'sid') ?: data_get($json, 'data.sid'));
    }

    public function accessToken(string $identity, int $ttl = 3600): string
    {
        $connection = (string) config('services.telnyx.webrtc_connection_id');
        if (empty(config('services.telnyx.api_key')) || $connection === '') {
            throw new OutOfStockException('Telnyx dialer is not configured.');
        }

        $credential = $this->client()->post('/telephony_credentials', ['connection_id' => $connection, 'name' => $identity])->throw();
        $id = (string) data_get($credential->json(), 'data.id');
        if ($id === '') {
            throw new OutOfStockException('Telnyx dialer credential was not created.');
        }

        // The token endpoint returns the JWT itself as the body (24 h lifetime; $ttl is advisory for this provider).
        return trim((string) $this->client()->post('/telephony_credentials/'.$id.'/token')->throw()->body());
    }

    /**
     * Wholesale cost basis in USD per minute. Telnyx publishes per-country rates only on a price sheet (not an API), so
     * this reads admin-set values and NEVER invents a rate: the longest matching dial-code key
     * `pricing.voice_cost.telnyx.cc{dialcode}` (e.g. cc234 for Nigeria), then `pricing.voice_cost.telnyx.default`, then the
     * config fallback, which equals the Twilio fallback so enabling Telnyx cannot silently cut a price.
     */
    public function voiceRate(string $destination): float
    {
        $digits = ltrim(preg_replace('/\D+/', '', $destination) ?? '', '0');
        for ($len = min(4, strlen($digits)); $len >= 1; $len--) {
            $v = Setting::getValue('pricing.voice_cost.telnyx.cc'.substr($digits, 0, $len), null);
            if ($v !== null && $v !== '' && (float) $v > 0) {
                return (float) $v;
            }
        }

        return (float) Setting::getValue('pricing.voice_cost.telnyx.default', (float) config('services.telnyx.default_voice_cost', 0.02));
    }

    private function client()
    {
        return Http::withToken((string) config('services.telnyx.api_key'))
            ->baseUrl((string) config('services.telnyx.base_url', 'https://api.telnyx.com/v2'))
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(30);
    }
}
