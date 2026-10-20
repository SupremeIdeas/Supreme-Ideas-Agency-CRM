<?php

namespace App\Jobs;

use App\Models\HomeVideo;
use App\Services\Media\VideoPoster;
use App\Support\HomeVideos;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Make a poster for a self-hosted home video that has none (off the request cycle; a no-op where ffmpeg cannot run). */
class GenerateVideoPosterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public int $homeVideoId) {}

    public function handle(VideoPoster $poster): void
    {
        $v = HomeVideo::query()->find($this->homeVideoId);
        if ($v === null || $v->source_type !== 'upload' || filled($v->poster_url) || ! filled($v->video_url)) {
            return;
        }
        $url = $poster->generate((string) $v->video_url, 'home-videos');
        if ($url !== null) {
            $v->update(['poster_url' => $url]);
            HomeVideos::flush();
        }
    }
}
