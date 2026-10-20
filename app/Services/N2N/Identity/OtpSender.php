<?php

namespace App\Services\N2N\Identity;

use App\Services\SMS\Numbers\TwilioService;
use App\Support\N2N\N2NSettings;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/** Sends the phone-link OTP through the platform's existing SMS provider (N2N, MASTER-ONLY). Returns the measured cost, or null if unknown. */
class OtpSender
{
    public function send(string $e164, string $code): ?float
    {
        return $this->sendText($e164, "Your Naara verification code is {$code}. It expires in ".N2NSettings::otpTtlMinutes().' minutes. Never share it.');
    }

    /** Any short platform SMS (verification, gift invite). Same provider, same cost reporting. */
    public function sendText(string $e164, string $body): ?float
    {

        if (config('n2n.otp.driver') === 'log') {
            Log::info('N2N OTP (log driver): code issued', ['to_hash' => substr(hash('sha256', $e164), 0, 12)]);   // never log the code or the number

            return null;
        }
        $from = N2NSettings::otpFrom();
        $twilio = app(TwilioService::class);
        if ($from === null || ! $twilio->available()) {
            throw new RuntimeException('Phone verification SMS is not configured.');
        }
        $cost = null;
        try {
            $cost = $twilio->outboundSmsCost($e164);
        } catch (\Throwable) {
            // cost reporting must never block a send
        }
        $twilio->sendSms($from, $e164, $body);

        return $cost;
    }
}
