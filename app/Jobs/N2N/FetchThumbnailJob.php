<?php

namespace App\Jobs\N2N;

use App\Services\N2N\Brands\ThumbnailCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Fetches and caches one provider thumbnail (N2N Batch 8, MASTER-ONLY). External call, so queued with retry + backoff. */
class FetchThumbnailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public string $provider, public string $providerId)
    {
    }

    public function backoff(): array
    {
        return [30, 300];
    }

    public function handle(): void
    {
        ThumbnailCache::fetch($this->provider, $this->providerId);
    }
}
