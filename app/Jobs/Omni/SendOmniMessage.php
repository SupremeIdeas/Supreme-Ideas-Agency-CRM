<?php

namespace App\Jobs\Omni;

use App\Services\Omni\Outbox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Send one queued omni message (MASTER-ONLY). One attempt: a provider refusal is recorded on the message, never retried blindly. */
class SendOmniMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $messageId)
    {
    }

    public function handle(Outbox $outbox): void
    {
        $outbox->deliver($this->messageId);
    }
}
