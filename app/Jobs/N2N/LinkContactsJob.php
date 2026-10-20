<?php

namespace App\Jobs\N2N;

use App\Services\N2N\Identity\ContactLinker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Re-links contacts to registered users (N2N, MASTER-ONLY). Idempotent, so retrying is safe. */
class LinkContactsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public ?int $ownerId = null, public ?string $e164 = null) {}

    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(ContactLinker $linker): void
    {
        if ($this->ownerId !== null) {
            $linker->linkOwner($this->ownerId);
        }
        if ($this->e164 !== null && $this->e164 !== '') {
            $linker->relinkNumber($this->e164);
        }
    }
}
