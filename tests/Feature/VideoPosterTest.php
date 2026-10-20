<?php

namespace Tests\Feature;

use App\Jobs\GenerateVideoPosterJob;
use App\Models\HomeVideo;
use App\Services\Media\FfmpegManager;
use App\Services\Media\VideoPoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Blueprint 7.1: a self-hosted video always has something to show first. */
class VideoPosterTest extends TestCase
{
    use RefreshDatabase;

    private function sampleMp4(): ?string
    {
        $ff = app(FfmpegManager::class);
        if (! $ff->available()) {
            return null;
        }
        $out = sys_get_temp_dir().'/vp_sample_'.uniqid().'.mp4';
        $r = $ff->run([$ff->binary(), '-y', '-f', 'lavfi', '-i', 'color=c=teal:s=320x180:d=2', '-pix_fmt', 'yuv420p', $out], 60);

        return $r['ok'] && is_file($out) ? $out : null;
    }

    public function test_a_frame_becomes_a_webp_poster_and_the_job_attaches_it(): void
    {
        $mp4 = $this->sampleMp4();
        if ($mp4 === null) {
            $this->markTestSkipped('ffmpeg is not available on this machine.');
        }
        Storage::fake('public');
        config(['filesystems.default' => 'public']);
        Storage::disk('public')->put('home-videos/clip.mp4', file_get_contents($mp4));
        $url = Storage::disk('public')->url('home-videos/clip.mp4');
        $v = HomeVideo::create(['title' => 'Clip', 'source_type' => 'upload', 'video_url' => $url, 'orientation' => 'landscape', 'is_active' => true, 'sort_order' => 1]);

        (new GenerateVideoPosterJob($v->id))->handle(app(VideoPoster::class));
        $v->refresh();
        $this->assertNotNull($v->poster_url);
        $this->assertStringEndsWith('.webp', $v->poster_url);
        $this->assertNotEmpty(Storage::disk('public')->allFiles('home-videos'));
    }

    public function test_without_ffmpeg_nothing_breaks_and_the_placeholder_shows(): void
    {
        $mock = \Mockery::mock(FfmpegManager::class);
        $mock->shouldReceive('available')->andReturn(false);
        $mock->shouldReceive('binary')->andReturn(null);
        $this->app->instance(FfmpegManager::class, $mock);
        $v = HomeVideo::create(['title' => 'Clip', 'source_type' => 'upload', 'video_url' => 'https://x.test/a.mp4', 'orientation' => 'landscape', 'is_active' => true, 'sort_order' => 1]);
        (new GenerateVideoPosterJob($v->id))->handle(app(VideoPoster::class));
        $this->assertNull($v->fresh()->poster_url);
        $this->assertStringContainsString('video-poster-placeholder.webp', (string) $v->fresh()->posterOrFallback());
    }

    public function test_an_admin_poster_always_wins_and_the_job_never_overwrites_it(): void
    {
        $v = HomeVideo::create(['title' => 'Clip', 'source_type' => 'upload', 'video_url' => 'https://x.test/a.mp4', 'poster_url' => 'https://x.test/mine.jpg', 'orientation' => 'landscape', 'is_active' => true, 'sort_order' => 1]);
        (new GenerateVideoPosterJob($v->id))->handle(app(VideoPoster::class));
        $this->assertSame('https://x.test/mine.jpg', $v->fresh()->posterOrFallback());
    }

    public function test_youtube_still_uses_its_thumbnail(): void
    {
        $v = new HomeVideo(['source_type' => 'youtube', 'youtube_id' => 'dQw4w9WgXcQ']);
        $this->assertStringContainsString('ytimg.com', $v->posterOrFallback());
    }
}
