<?php

namespace Tests\Feature\MasterMarketing;

use App\Models\User;
use App\Support\MasterMarketing\VideoSource;
use App\Support\SiteContent;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The White Label page video: source parsing, the public player, and the resumable chunked upload (MASTER-ONLY). */
class WhiteLabelVideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_links_are_parsed_into_a_safe_player_description(): void
    {
        $yt = VideoSource::parseLink('https://youtu.be/dQw4w9WgXcQ?t=4');
        $this->assertSame('youtube', $yt['kind']);
        $this->assertStringStartsWith('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?', $yt['embed']);
        $this->assertSame('youtube', VideoSource::parseLink('https://www.youtube.com/shorts/dQw4w9WgXcQ')['kind']);
        $this->assertSame('youtube', VideoSource::parseLink('https://m.youtube.com/watch?v=dQw4w9WgXcQ&feature=share')['kind']);

        $vm = VideoSource::parseLink('https://vimeo.com/123456789/abcdef1234');
        $this->assertSame('vimeo', $vm['kind']);
        $this->assertStringContainsString('player.vimeo.com/video/123456789', $vm['embed']);
        $this->assertStringContainsString('h=abcdef1234', $vm['embed']);

        $file = VideoSource::parseLink('https://cdn.example.com/promo.webm?x=1');
        $this->assertSame(['file', 'video/webm'], [$file['kind'], $file['mime']]);
    }

    public function test_anything_unsafe_or_unrecognised_is_refused(): void
    {
        foreach (['javascript:alert(1)', 'http://cdn.example.com/a.mp4', 'https://evil.test/watch?v=dQw4w9WgXcQ', 'https://example.com/page', '//evil.test/a.mp4', ''] as $bad) {
            $this->assertNull(VideoSource::parseLink($bad), $bad);
        }
        $this->assertNull(VideoSource::resolve(['video_source' => 'upload', 'video_file' => 'javascript:alert(1)']));
        $this->assertNull(VideoSource::resolve(['video_source' => 'upload', 'video_file' => '']));
        $this->assertSame('#0A6E6E', VideoSource::color(['player_color' => 'red; x']));
        $this->assertSame('#112233', VideoSource::color(['player_color' => '#112233']));
    }

    public function test_the_section_stays_hidden_until_a_video_exists_then_shows_the_player(): void
    {
        $this->get('/white-label')->assertOk()->assertDontSee('mmPlayer', false);

        SiteContent::saveOverrides('white-label', ['video' => ['video_source' => 'link', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'player_color' => '#aa3300']]);
        $this->get('/white-label')->assertOk()
            ->assertSee('mmPlayer', false)
            ->assertSee('nocookie.com', false)->assertSee('dQw4w9WgXcQ', false)
            ->assertSee('#aa3300', false);

        SiteContent::saveOverrides('white-label', ['video' => ['video_source' => 'link', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'visible' => false]]);
        $this->get('/white-label')->assertDontSee('mmPlayer', false);
    }

    public function test_a_video_uploads_in_chunks_and_is_verified_before_it_is_stored(): void
    {
        Storage::fake('public');
        Storage::fake(\App\Support\MasterMarketing\VideoVault::disk());
        config(['filesystems.default' => 'public']);
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');

        $bytes = "\x00\x00\x00\x18ftypmp42".str_repeat('v', 700 * 1024);
        $init = $this->actingAs($admin)->postJson(route('admin.mm-video.init'), ['name' => 'promo.mp4', 'size' => strlen($bytes), 'mtime' => 1])->assertOk();
        $id = $init->json('id');
        $this->assertGreaterThanOrEqual(256 * 1024, (int) $init->json('chunk'));

        $parts = str_split($bytes, 300 * 1024);
        foreach ($parts as $n => $part) {
            $this->call('PUT', route('admin.mm-video.chunk', ['id' => $id, 'n' => $n]), [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], $part)->assertOk();
        }
        // Asking again for the same file reports what has already arrived (resume).
        $this->actingAs($admin)->postJson(route('admin.mm-video.init'), ['name' => 'promo.mp4', 'size' => strlen($bytes), 'mtime' => 1])
            ->assertOk()->assertJsonPath('id', $id)->assertJsonCount(count($parts), 'received');

        $done = $this->actingAs($admin)->postJson(route('admin.mm-video.complete', ['id' => $id]))->assertOk();
        $this->assertSame(strlen($bytes), $done->json('bytes'));
        $this->assertSame('mp4', $done->json('type'));
        // stored in the private vault (no public address); the page plays it through a signed link
        $this->assertStringStartsWith('vault:site/video/', $done->json('url'));
        $this->assertCount(1, Storage::disk(\App\Support\MasterMarketing\VideoVault::disk())->files('site/video'));
        $this->assertCount(0, Storage::disk('public')->files('site/video'));
        $this->assertDirectoryDoesNotExist(storage_path('app/private/mm-video-uploads/'.$id));
    }

    public function test_a_renamed_non_video_and_a_missing_piece_are_refused(): void
    {
        Storage::fake('public');
        config(['filesystems.default' => 'public']);
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');

        $fake = '%PDF-1.4 '.str_repeat('x', 400 * 1024);
        $id = $this->actingAs($admin)->postJson(route('admin.mm-video.init'), ['name' => 'doc.mp4', 'size' => strlen($fake), 'mtime' => 2])->json('id');
        $this->call('PUT', route('admin.mm-video.chunk', ['id' => $id, 'n' => 0]), [], [], [], [], $fake)->assertOk();
        $this->actingAs($admin)->postJson(route('admin.mm-video.complete', ['id' => $id]))->assertStatus(422);
        $this->assertSame([], Storage::disk('public')->files('site/video'));

        $video = "\x00\x00\x00\x18ftypmp42".str_repeat('v', 600 * 1024);
        $id = $this->actingAs($admin)->postJson(route('admin.mm-video.init'), ['name' => 'a.mp4', 'size' => strlen($video), 'mtime' => 3])->json('id');
        $this->call('PUT', route('admin.mm-video.chunk', ['id' => $id, 'n' => 0]), [], [], [], [], substr($video, 0, 300 * 1024))->assertOk();
        $this->actingAs($admin)->postJson(route('admin.mm-video.complete', ['id' => $id]))->assertStatus(409);

        $this->actingAs($admin)->postJson(route('admin.mm-video.init'), ['name' => 'notes.docx', 'size' => 10])->assertStatus(422);
        $this->actingAs($admin)->postJson(route('admin.mm-video.init'), ['name' => 'huge.mp4', 'size' => (VideoSource::maxMb() + 1) * 1024 * 1024])->assertStatus(422);
    }

    public function test_only_admins_can_use_the_upload_endpoints_and_the_poster_is_an_image(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user)->postJson(route('admin.mm-video.init'), ['name' => 'a.mp4', 'size' => 1000])->assertStatus(404);

        Storage::fake('public');
        config(['filesystems.default' => 'public']);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin)->postJson(route('admin.mm-video.poster'), ['poster' => UploadedFile::fake()->image('p.jpg', 640, 360)])->assertOk()->assertJsonStructure(['url']);
        $this->actingAs($admin)->postJson(route('admin.mm-video.poster'), ['poster' => UploadedFile::fake()->create('p.exe', 10)])->assertStatus(422);
    }
}
