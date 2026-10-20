<?php

namespace App\Services\SMS\Gateways;

use App\Exceptions\SmsException;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

/**
 * Africa's Talking: a pan-African SMS aggregator with direct operator connections (Nigeria, Kenya, Ghana, Uganda, Tanzania,
 * Rwanda, South Africa and others). Chosen as the regional gateway because it is the one candidate whose public API surface
 * is well documented and whose sandbox needs no spend.
 *
 * Confirmed (docs and SDK references, 2026-10-06): POST {base}/version1/messaging, form fields username / to (comma-separated
 * E.164) / message / from (optional sender ID), headers `apiKey` and `Accept: application/json`; reply
 * SMSMessageData.Recipients[] each with statusCode (101 = sent), number, status, cost, messageId. Delivery reports POST to a
 * configured callback with id / status / phoneNumber / failureReason / networkCode.
 *
 * NOT confirmed, verify in the sandbox first: that delivery-report callbacks are unsigned (so the callback carries a secret
 * token we check, see SmsDeliveryWebhookController); the sender-ID registration rules for Nigeria; the live price per
 * message (not in this research: enter the real rate as pricing.sms_send_cost.africastalking before enabling).
 *
 * Money rules: this class never sets a price. Cost comes from the admin setting; the caller charges retail from PricingEngine.
 */
class AfricasTalkingGateway implements OutboundSmsGateway
{
    public function key(): string
    {
        return 'africastalking';
    }

    public function available(): bool
    {
        return ! empty(config('services.africastalking.username')) && ! empty(config('services.africastalking.api_key'));
    }

    /** The dial codes (e.g. 234,254) the admin has enabled for this gateway; none until set. */
    public function coveredDialCodes(): array
    {
        $raw = (string) Setting::getValue('sms.regional.dial_codes', '');

        return array_values(array_filter(array_map(fn ($c) => ltrim(trim($c), '+'), explode(',', $raw)), fn ($c) => $c !== ''));
    }

    public function coversDestination(string $e164): bool
    {
        if (! (bool) Setting::getValue('sms.regional.enabled', false) || ! $this->available()) {
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
            $v = Setting::getValue('pricing.sms_send_cost.africastalking.cc'.substr($digits, 0, $len), null);
            if ($v !== null && $v !== '' && (float) $v > 0) {
                return (float) $v;
            }
        }

        return (float) Setting::getValue('pricing.sms_send_cost.africastalking', (float) config('services.africastalking.default_sms_cost', 0.05));
    }

    public function send(string $e164, string $body, ?string $senderId = null): array
    {
        if (! $this->available()) {
            throw new SmsException('The regional SMS gateway is not configured.');
        }
        if (! preg_match('/^\+[1-9]\d{6,14}$/', $e164)) {
            throw new SmsException('Enter a valid destination in international format.');
        }

        $sender = $senderId ?? (string) config('services.africastalking.sender_id');
        $payload = array_filter([
            'username' => (string) config('services.africastalking.username'),
            'to' => $e164,
            'message' => $body,
            'from' => $sender !== '' ? $sender : null,
        ], fn ($v) => $v !== null);

        try {
            $res = Http::withHeaders(['apiKey' => (string) config('services.africastalking.api_key'), 'Accept' => 'application/json'])
                ->baseUrl(rtrim((string) config('services.africastalking.base_url'), '/'))
                ->connectTimeout(3)->timeout(20)
                ->asForm()->post('/version1/messaging', $payload);
        } catch (\Throwable $e) {
            throw new SmsException('The regional SMS gateway is unreachable.', 0, $e);
        }
        if ($res->failed()) {
            throw new SmsException('The regional SMS gateway rejected the message.');
        }

        $recipient = (array) data_get($res->json(), 'SMSMessageData.Recipients.0', []);
        // 101 = Sent; anything else (invalid number, risk hold, insufficient balance) is a failure the caller must refund.
        if ((int) ($recipient['statusCode'] ?? 0) !== 101 || empty($recipient['messageId'])) {
            throw new SmsException('The message could not be sent to that number.');
        }

        return [
            'provider_ref' => (string) $recipient['messageId'],
            'status' => 'sent',
            'parts' => max(1, (int) ($recipient['messageParts'] ?? 1)),
        ];
    }
}
