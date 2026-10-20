<?php

namespace App\Jobs\N2N;

use App\Models\User;
use App\Notifications\N2NInvite;
use App\Services\N2N\Identity\OtpSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

/** Delivers a plain invite by email or SMS (external call, so queued; the clear address lives only in this transient payload). One attempt. */
class SendInviteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $inviterId, public string $kind, public string $to) {}

    public function handle(OtpSender $sms): void
    {
        $inviter = User::query()->find($this->inviterId);
        if ($inviter === null) {
            return;
        }
        $link = url('/?ref='.$inviter->referral_code);
        $name = $inviter->name ?: 'A friend';
        if ($this->kind === 'email') {
            Notification::route('mail', $this->to)->notify(new N2NInvite($name, $link));
        } else {
            $sms->sendText($this->to, "{$name} invited you to chat on Naara. Join: {$link}");
        }
    }
}
