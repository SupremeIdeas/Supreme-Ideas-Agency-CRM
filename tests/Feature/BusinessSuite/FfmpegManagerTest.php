<?php

namespace Tests\Feature\BusinessSuite;

use App\Models\Setting;
use App\Services\Media\FfmpegManager;
use App\Services\Media\WebmOpusToOgg;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The media converter on shared hosting and a VPS: finding it, installing a managed copy safely, refusing a bad upload, and the pure-PHP voice-note fallback. */
class FfmpegManagerTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->tmp = sys_get_temp_dir().'/ffm-'.bin2hex(random_bytes(4));
        mkdir($this->tmp, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmp.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->tmp);
        parent::tearDown();
    }

    private function fakeBinary(string $banner = 'ffmpeg version 9.9-test'): string
    {
        $p = $this->tmp.'/ffmpeg-fake';
        file_put_contents($p, "#!/bin/sh\nif [ \"\$1\" = \"-encoders\" ] || [ \"\$2\" = \"-encoders\" ]; then echo ' V..... libx264  H.264'; echo ' A..... aac  AAC'; echo ' A..... libopus Opus'; exit 0; fi\necho \"{$banner}\"\nexit 0\n");
        chmod($p, 0755);

        return $p;
    }

    public function test_this_host_is_checked_for_the_ability_to_run_programs(): void
    {
        $s = app(FfmpegManager::class)->execStatus();
        $this->assertArrayHasKey('ok', $s);
        $this->assertSame($s['ok'] ? null : true, $s['ok'] ? null : ($s['reason'] !== null));
    }

    public function test_an_admin_typed_path_wins_and_is_proven_by_running_it(): void
    {
        $m = app(FfmpegManager::class);
        Setting::setValue(FfmpegManager::SETTING_PATH, $this->fakeBinary(), 'business');
        $m->forget();

        $this->assertSame($this->tmp.'/ffmpeg-fake', $m->binary());
        $info = $m->info(true);
        $this->assertTrue($info['available']);
        $this->assertSame('custom', $info['source']);
        $this->assertSame('9.9-test', $info['version']);
        $this->assertTrue($info['h264'] && $info['aac'] && $info['opus']);
    }

    public function test_a_path_that_does_not_run_is_ignored_rather_than_trusted(): void
    {
        $m = app(FfmpegManager::class);
        file_put_contents($this->tmp.'/notaprogram', 'hello');
        chmod($this->tmp.'/notaprogram', 0755);
        Setting::setValue(FfmpegManager::SETTING_PATH, $this->tmp.'/notaprogram', 'business');
        $m->forget();

        $this->assertNotSame($this->tmp.'/notaprogram', $m->binary());
    }

    public function test_download_sources_are_tried_in_order_and_an_admin_link_goes_first(): void
    {
        $m = app(FfmpegManager::class);
        $ids = array_column($m->sources(), 'id');
        $this->assertContains('npm', $ids);

        Setting::setValue(FfmpegManager::SETTING_MIRROR, 'https://files.example.com/ffmpeg-static.tar.xz', 'business');
        $first = $m->sources()[0];
        $this->assertSame('mirror', $first['id']);
        $this->assertSame('tar.xz', $first['format']);
    }

    public function test_when_every_download_fails_the_install_ends_with_a_plain_sentence_pointing_at_the_upload(): void
    {
        $m = app(FfmpegManager::class);
        if ($m->installBlocker() !== null) {
            $this->markTestSkipped('This machine cannot install a managed copy: '.$m->installBlocker());
        }
        Http::fake(['*' => Http::response('nope', 500)]);
        $keep = FfmpegManager::managedPath();
        $existing = is_file($keep) ? file_get_contents($keep) : null;

        $s = $m->installRun();

        $this->assertSame('failed', $s['phase']);
        $this->assertStringContainsString('upload a static ffmpeg', (string) $s['error']);
        if ($existing !== null && ! is_file($keep)) {   // a failed install must never destroy a working copy
            $this->fail('The managed copy was deleted by a failed install.');
        }
    }

    public function test_an_uploaded_file_that_is_not_a_linux_program_is_rejected(): void
    {
        $m = app(FfmpegManager::class);
        if ($m->installBlocker() !== null) {
            $this->markTestSkipped('This machine cannot install a managed copy.');
        }
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not look like an ffmpeg program');
        $m->installUploaded(UploadedFile::fake()->create('ffmpeg', 10, 'application/octet-stream'));   // 10 KB: far too small
    }

    public function test_a_big_file_without_the_linux_program_header_is_rejected_too(): void
    {
        $m = app(FfmpegManager::class);
        if ($m->installBlocker() !== null) {
            $this->markTestSkipped('This machine cannot install a managed copy.');
        }
        $f = UploadedFile::fake()->createWithContent('ffmpeg.zip', 'PK'.str_repeat('x', 6 * 1048576));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not a Linux program');
        $m->installUploaded($f);
    }

    // ── pure-PHP voice-note rewrap ──────────────────────────────────────────

    public function test_a_browser_webm_voice_note_is_rewrapped_as_valid_ogg_opus_without_ffmpeg(): void
    {
        $webm = (string) file_get_contents(base_path('tests/fixtures/voice-opus.webm'));
        $ogg = WebmOpusToOgg::convert($webm);

        $this->assertNotNull($ogg);
        $this->assertSame('OggS', substr($ogg, 0, 4));
        $this->assertStringContainsString('OpusHead', substr($ogg, 0, 120));
        $this->assertStringContainsString('OpusTags', substr($ogg, 0, 400));

        // every page: right capture pattern, and a checksum that matches (a wrong CRC is rejected by WhatsApp and Telegram)
        $i = 0;
        $pages = 0;
        $last = 0;
        while ($i < strlen($ogg)) {
            $this->assertSame('OggS', substr($ogg, $i, 4), 'page boundary at '.$i);
            $segs = ord($ogg[$i + 26]);
            $len = 27 + $segs;
            for ($k = 0; $k < $segs; $k++) {
                $len += ord($ogg[$i + 27 + $k]);
            }
            $page = substr($ogg, $i, $len);
            $crc = unpack('V', substr($page, 22, 4))[1];
            $zeroed = substr($page, 0, 22)."\0\0\0\0".substr($page, 26);
            $this->assertSame($crc, self::oggCrc($zeroed), 'CRC of page '.$pages);
            $last = ord($page[5]);
            $pages++;
            $i += $len;
        }
        $this->assertGreaterThanOrEqual(3, $pages);
        $this->assertSame(0x04, $last & 0x04, 'the last page carries the end-of-stream flag');
    }

    public function test_the_rewrapped_voice_note_decodes_with_a_real_ffmpeg_when_one_is_available(): void
    {
        $m = app(FfmpegManager::class);
        if (! $m->available()) {
            $this->markTestSkipped('No ffmpeg on this machine to decode with.');
        }
        $ogg = WebmOpusToOgg::convert((string) file_get_contents(base_path('tests/fixtures/voice-opus.webm')));
        file_put_contents($this->tmp.'/v.ogg', $ogg);

        $r = $m->run([$m->binary(), '-hide_banner', '-v', 'error', '-i', $this->tmp.'/v.ogg', '-f', 'null', '-'], 30);

        $this->assertTrue($r['ok'], $r['err']);
        $this->assertSame('', trim($r['err']));
    }

    public function test_anything_that_is_not_opus_in_webm_is_declined_so_the_caller_can_fall_back(): void
    {
        $this->assertNull(WebmOpusToOgg::convert('not a webm file at all, just text'));
        $this->assertNull(WebmOpusToOgg::convert(''));
        $this->assertNull(WebmOpusToOgg::convert("\x1A\x45\xDF\xA3".str_repeat("\0", 200)));
    }

    private static function oggCrc(string $data): int
    {
        static $t = null;
        if ($t === null) {
            for ($i = 0; $i < 256; $i++) {
                $r = $i << 24;
                for ($j = 0; $j < 8; $j++) {
                    $r = ($r & 0x80000000) ? (($r << 1) ^ 0x04C11DB7) : ($r << 1);
                    $r &= 0xFFFFFFFF;
                }
                $t[$i] = $r;
            }
        }
        $crc = 0;
        for ($i = 0, $n = strlen($data); $i < $n; $i++) {
            $crc = (($crc << 8) & 0xFFFFFFFF) ^ $t[(($crc >> 24) & 0xFF) ^ ord($data[$i])];
        }

        return $crc;
    }
}
