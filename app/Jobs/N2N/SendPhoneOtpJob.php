<?php

namespace App\Jobs\N2N;

use App\Models\N2N\PhoneVerification;
use App\Services\N2N\Identity\OtpSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Sends one phone-link OTP (external SMS call = always a queued job). No blind retry: a duplicate OTP would double the SMS cost. */
class SendPhoneOtpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $verificationId, public string $code) {}

    public function handle(OtpSender $sender): void
    {
        $row = PhoneVerification::query()->find($this->verificationId);
        if ($row === null || $row->suppressed || $row->verified_at !== null || $row->expires_at->isPast()) {
            return;
        }
        $cost = $sender->send($row->e164, $this->code);
        if ($cost !== null) {
            $row->forceFill(['cost_usd' => $cost])->save();
        }
    }
}
