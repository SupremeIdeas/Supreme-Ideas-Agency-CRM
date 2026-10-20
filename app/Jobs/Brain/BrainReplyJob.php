<?php

namespace App\Jobs\Brain;

use App\Models\Brain\BrainMessage;
use App\Services\Brain\BrainResponder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** One NaaraBrain answer, off the request cycle (MASTER-ONLY). Single attempt: the gateway already retries transient errors, and a repeat could double the cost. */
class BrainReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 150;

    public function __construct(public int $messageId) {}

    public function handle(BrainResponder $responder): void
    {
        $m = BrainMessage::query()->where('status', 'pending')->where('role', 'assistant')->find($this->messageId);
        if ($m !== null) {
            $responder->answer($m);
        }
    }

    public function failed(\Throwable $e): void
    {
        BrainMessage::query()->where('id', $this->messageId)->where('status', 'pending')->update(['status' => 'error', 'body' => __('brain.reply_failed')]);
    }
}
