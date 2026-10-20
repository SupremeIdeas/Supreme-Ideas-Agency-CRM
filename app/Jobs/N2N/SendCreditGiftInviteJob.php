<?php

namespace App\Jobs\N2N;

use App\Models\N2N\CreditGift;
use App\Notifications\N2NCreditGiftInvite;
use App\Services\N2N\Credit\Money;
use App\Services\N2N\Identity\OtpSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

/**
 * Delivers the gift invite by email or SMS (an external call, so always a queued job). The clear address lives only in this transient job
 * payload; the gift row stores a keyed hash and a masked display. One attempt: a duplicate invite helps nobody.
 */
class SendCreditGiftInviteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $giftId, public string $kind, public string $to) {}

    public function handle(OtpSender $sms): void
    {
        $gift = CreditGift::query()->with('sender')->find($this->giftId);
        if ($gift === null || $gift->status !== CreditGift::PENDING || $gift->sender === null) {
            return;
        }
        $link = url('/?ref='.$gift->sender->referral_code);
        $amount = Money::credits(Money::cents($gift->getRawOriginal('amount')));
        $hours = max(1, (int) round(now()->diffInHours($gift->expires_at, false)));
        $name = $gift->sender->name ?: 'A friend';
        if ($this->kind === 'email') {
            Notification::route('mail', $this->to)->notify(new N2NCreditGiftInvite($name, $amount, $link, $hours));
        } else {
            $sms->sendText($this->to, "{$name} sent you {$amount} NaaraCredit. Join with {$link} and verify this number within {$hours}h to receive it.");
        }
    }
}
