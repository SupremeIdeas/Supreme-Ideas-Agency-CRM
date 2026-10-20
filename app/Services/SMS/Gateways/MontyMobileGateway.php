<?php

namespace App\Services\SMS\Gateways;

use App\Exceptions\SmsException;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

/**
 * Monty Mobile: an A2P messaging platform (SMS, WhatsApp, Push). Used here as a SEND-ONLY SMS gateway for platform notices and
 * verification messages from a registered sender ID, never as a lane member (it owns no leasable number and cannot take replies).
 *
 * Confirmed from the developer portal (montymobile.com/messaging-api-doc, 2026-10-18): POST {sms_base}/api/v2/sms/send-sms (single)
 * or /send-otp (single, verification), JSON body Source / Message / Destination (international format, country code + subscriber
 * number), headers `api-key`, `Tenant` (organisation UUID) and Content-Type. Success reply: {success:true, code:"8_10_200",
 * data:{messageId, errorCode:0, destination}}; failure carries data.errorCode (2 Invalid Sender, 5 Unauthorized, 10 No Template) and
 * a 401/403 for bad credentials. Calls are accepted only from IP addresses whitelisted in the portal.
 *
 * NOT confirmed: SMS delivery-receipt callbacks are not documented for the SMS product (only WhatsApp has webhooks), so a send
 * stays `sent` until a receipt path exists; live sends were not run (read-only rule), and the egress IP must be whitelisted first.
 *
 * Money rules: this class never sets a price; cost is admin-set and the caller charges retail from PricingEngine.
 */
class MontyMobileGateway implements OutboundSmsGateway
{
    public function key(): string
    {
        return 'montymobile_sms';
    }

    public function available(): bool
    {
        return filled(config('services.montymobile.sms_api_key')) && filled(config('services.montymobile.tenant'));
    }

    /** Dial codes (eg 961,971) the admin has enabled for this gateway; none until set. */
    public function coveredDialCodes(): array
    {
        $raw = (string) Setting::getValue('sms.monty.dial_codes', '');

        return array_values(array_filter(array_map(fn ($c) => ltrim(trim($c), '+'), explode(',', $raw)), fn ($c) => $c !== ''));
    }

    public function coversDestination(string $e164): bool
    {
        if (! (bool) Setting::getValue('sms.monty.enabled', false) || ! $this->available()) {
            return false;
        }
        $digits = ltrim($e164, '+');
        foreach ($this->coveredDialCodes() as $code) {
            if (str_starts_with($digits, $code)) {
                return true;
            }
        }

        return false;
    }

    public function costPerPart(string $e164): float
    {
        $digits = ltrim($e164, '+');
        for ($len = min(4, strlen($digits)); $len >= 1; $len--) {
            $v = Setting::getValue('pricing.sms_send_cost.montymobile.cc'.substr($digits, 0, $len), null);
            if ($v !== null && $v !== '' && (float) $v > 0) {
                return (float) $v;
            }
        }

        return (float) Setting::getValue('pricing.sms_send_cost.montymobile', (float) config('services.montymobile.default_sms_cost', 0.05));
    }

    public function send(string $e164, string $body, ?string $senderId = null, bool $otp = false): array
    {
        if (! $this->available()) {
            throw new SmsException('The Monty Mobile SMS gateway is not configured.');
        }
        if (! preg_match('/^\+[1-9]\d{6,14}$/', $e164)) {
            throw new SmsException('Enter a valid destination in international format.');
        }
        $source = $senderId ?? (string) config('services.montymobile.sender_id');
        if ($source === '') {
            throw new SmsException('No approved sender ID is set for this gateway.');
        }

        try {
            $res = Http::withHeaders(['api-key' => (string) config('services.montymobile.sms_api_key'), 'Tenant' => (string) config('services.montymobile.tenant')])
                ->acceptJson()->asJson()
                ->baseUrl(rtrim((string) config('services.montymobile.sms_base_url'), '/'))
                ->connectTimeout(3)->timeout(20)
                ->post($otp ? '/api/v2/sms/send-otp' : '/api/v2/sms/send-sms', ['Source' => $source, 'Message' => $body, 'Destination' => ltrim($e164, '+')]);
        } catch (\Throwable $e) {
            throw new SmsException('The Monty Mobile SMS gateway is unreachable.', 0, $e);
        }
        if ($res->failed()) {
            throw new SmsException('The Monty Mobile SMS gateway rejected the message.');
        }
        $json = (array) $res->json();
        // Success needs the flag, a zero domain code and a message id; anything else (invalid sender, unauthorised, template) is a failure to refund.
        if (empty($json['success']) || (int) data_get($json, 'data.errorCode', 1) !== 0 || empty(data_get($json, 'data.messageId'))) {
            throw new SmsException('The message could not be sent to that number.');
        }

        return ['provider_ref' => (string) data_get($json, 'data.messageId'), 'status' => 'sent', 'parts' => max(1, (int) ceil(mb_strlen($body) / 160))];
    }
}
