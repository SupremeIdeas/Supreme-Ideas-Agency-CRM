<?php

namespace Tests\Feature;

use App\Livewire\Admin\MasterMarketing\ContentLock as ContentLockPage;
use App\Models\MasterMarketingVideoSection;
use App\Models\Post;
use App\Models\User;
use App\Support\MasterMarketing\ContentLock;
use App\Support\MasterMarketing\MegaFooter;
use App\Support\MasterMarketing\MenuIcons;
use App\Support\MasterMarketing\VideoSlots;
use App\Support\MasterMarketing\VideoVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Private self-hosted video, the carousel and orientation, the content lock switch, the mega footer and the new nav icons (MASTER ONLY). */
class MasterMarketingVideoVaultAndLockTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('super_admin');

        return $u;
    }

    private function vaulted(string $bytes = 'FAKEVIDEOBYTES0123456789'): string
    {
        Storage::fake(VideoVault::disk());
        $tmp = tempnam(sys_get_temp_dir(), 'v');
        file_put_contents($tmp, $bytes);

        return VideoVault::store($tmp, 'mp4');
    }

    public function test_a_vault_video_has_no_public_address_and_streams_only_to_a_browser_media_element(): void
    {
        $ref = $this->vaulted();
        $this->assertStringStartsWith('vault:site/video/', $ref);
        $url = VideoVault::url($ref);

        $this->assertStringNotContainsString('site/video', $url);   // the file's own path is never in the link
        $this->get($url, ['Sec-Fetch-Dest' => 'video'])->assertOk()->assertHeader('Content-Type', 'video/mp4');
        $this->get($url, ['Sec-Fetch-Dest' => 'document'])->assertForbidden();                    // pasted in the address bar / save link as
        $this->get($url, ['Sec-Fetch-Dest' => 'video', 'Referer' => 'https://evil.example/'])->assertForbidden();
        $this->get(preg_replace('/signature=[^&]+/', 'signature=bad', $url))->assertForbidden();  // tampered
        $this->get(route('mm.video.stream', ['v' => 'nonsense']))->assertForbidden();             // unsigned
    }

    public function test_the_stream_never_serves_a_path_outside_the_vault(): void
    {
        $this->assertNull(VideoVault::pathFromToken(\Illuminate\Support\Facades\Crypt::encryptString('../.env')));
        $this->assertNull(VideoVault::pathFromToken(\Illuminate\Support\Facades\Crypt::encryptString('site/video/../../.env')));
        $this->assertNull(VideoVault::pathFromToken(\Illuminate\Support\Facades\Crypt::encryptString('private/secret.mp4')));
        $this->assertSame('site/video/a.mp4', VideoVault::pathFromToken(\Illuminate\Support\Facades\Crypt::encryptString('site/video/a.mp4')));
    }

    public function test_blog_videos_make_a_carousel_with_orientation_and_a_protected_player(): void
    {
        $post = Post::create(['title' => 'Video post', 'slug' => 'video-post', 'category' => 'News', 'body' => 'Body', 'status' => 'published', 'published_at' => now()->subDay()]);
        $ref = $this->vaulted();
        MasterMarketingVideoSection::create(['slot' => 'blog:'.$post->id, 'title' => 'First', 'source_type' => 'upload', 'video_url' => $ref, 'is_visible' => true, 'position' => 1, 'orientation' => 'landscape', 'protected' => true]);
        MasterMarketingVideoSection::create(['slot' => 'blog:'.$post->id, 'title' => 'Second tall', 'source_type' => 'url', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'is_visible' => true, 'position' => 2, 'orientation' => 'portrait']);

        $this->assertTrue(VideoSlots::known('blog:'.$post->id));
        $this->assertFalse(VideoSlots::known('blog:abc'));
        $deck = VideoSlots::deck('blog:'.$post->id);
        $this->assertSame(['landscape', 'portrait'], array_column($deck, 'orientation'));
        $this->assertTrue($deck[0]['protect']);
        $this->assertFalse($deck[1]['protect']);   // a YouTube link is not ours to protect

        $html = $this->get(route('blog.show', $post))->assertOk()->assertSee('data-video-deck="blog:'.$post->id.'"', false)->getContent();
        $this->assertStringContainsString('nodownload', $html);
        $this->assertStringNotContainsString('site/video/', $html);   // the vault path is not in the page
    }

    public function test_the_content_lock_is_on_by_default_and_the_admin_can_turn_it_off(): void
    {
        $this->assertTrue(ContentLock::active('marketing'));
        $this->assertFalse(ContentLock::active('app'));   // marketing only unless the admin widens it
        $this->get(route('home'))->assertOk()->assertSee('nx-lock-img', false);

        Livewire::actingAs($this->admin())->test(ContentLockPage::class)->set('enabled', false)->call('save')->assertHasNoErrors();
        $this->assertFalse(ContentLock::active('marketing'));
        $this->get(route('home'))->assertOk()->assertDontSee('nx-lock-img', false);

        Livewire::actingAs($this->admin())->test(ContentLockPage::class)->set('enabled', true)->set('scope', 'all')->set('shield', true)->call('save');
        $this->assertTrue(ContentLock::active('app'));
        $this->assertTrue(ContentLock::settings()['shield']);
    }

    public function test_the_mega_footer_lists_real_pages_only_and_keeps_admin_columns(): void
    {
        $groups = collect(MegaFooter::groups());
        $this->assertGreaterThanOrEqual(4, $groups->count());
        $urls = $groups->flatMap(fn ($g) => collect($g['links'])->pluck('url'));
        $this->assertGreaterThan(25, $urls->count());
        $this->assertTrue($urls->every(fn ($u) => str_starts_with($u, 'http')));
        $this->assertCount(20, MegaFooter::destinations());

        $this->get(route('about'))->assertOk()->assertSee('data-mega-footer', false)->assertSee('Popular eSIM destinations')->assertSee('Naara Chat');
    }

    public function test_the_floating_nav_uses_the_custom_icon_set_not_the_old_line_icons(): void
    {
        $this->assertSame('menu.app.more.home', MenuIcons::navSlotKey('home', 'globe', false));
        $this->assertSame('menu.app.more.esims', MenuIcons::navSlotKey('pricing', 'wifi', false));
        $this->assertSame('menu.app.more.numbers', MenuIcons::navSlotKey('pricing', 'phone', false));
        $this->assertSame('menu.marketing.mega.pricing', MenuIcons::navSlotKey('pricing', 'wifi', true));
        $this->assertNull(MenuIcons::navSlotKey('whatever', 'unmapped-icon', false));
    }

    public function test_earlier_public_uploads_can_be_moved_into_the_vault(): void
    {
        Storage::fake(\App\Support\MediaStorage::disk());
        Storage::fake(VideoVault::disk());
        Storage::disk(\App\Support\MediaStorage::disk())->put('marketing-video/old.mp4', 'OLDBYTES');
        $row = MasterMarketingVideoSection::create(['slot' => 'home.hero', 'title' => 'Old', 'source_type' => 'upload', 'video_url' => '/storage/marketing-video/old.mp4', 'is_visible' => true, 'position' => 1]);

        $this->assertSame(1, VideoVault::lockExisting());
        $row->refresh();
        $this->assertStringStartsWith('vault:site/video/', $row->video_url);
        Storage::disk(\App\Support\MediaStorage::disk())->assertMissing('marketing-video/old.mp4');
        $this->assertSame(0, VideoVault::lockExisting());   // nothing left to move
    }

    public function test_the_wave_is_steady_per_video_in_range_and_real_loudness_is_shaped_into_it(): void
    {
        $a = \App\Support\MasterMarketing\VideoWave::synthetic('7');
        $this->assertCount(96, $a);
        $this->assertSame($a, \App\Support\MasterMarketing\VideoWave::synthetic('7'));        // the same video always draws the same wave
        $this->assertNotSame($a, \App\Support\MasterMarketing\VideoWave::synthetic('8'));
        $this->assertGreaterThanOrEqual(0.12, min($a));
        $this->assertEqualsWithDelta(1.0, max($a), 0.001);

        $loud = array_merge(array_fill(0, 40, 9000.0), array_fill(0, 40, 300.0));                // loud first half, quiet second
        $shaped = \App\Support\MasterMarketing\VideoWave::shape($loud);
        $this->assertCount(96, $shaped);
        $this->assertGreaterThan(array_sum(array_slice($shaped, 60)), array_sum(array_slice($shaped, 0, 36)));

        $deck = VideoSlots::deck('home.hero');
        $this->assertSame([], $deck);
        MasterMarketingVideoSection::create(['slot' => 'home.hero', 'title' => 'T', 'source_type' => 'url', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'is_visible' => true, 'position' => 1]);
        $this->assertCount(96, VideoSlots::deck('home.hero')[0]['wave']);
    }

    public function test_travel_handbooks_get_a_hero_video_and_the_blog_carousel_sits_above_the_footer(): void
    {
        $this->assertSame('handbooks.hero', VideoSlots::heroFor('handbooks.store'));
        $this->assertTrue(VideoSlots::known('handbooks.hero'));

        foreach (range(1, 4) as $i) {
            Post::create(['title' => "Story $i", 'slug' => "story-$i", 'category' => 'News', 'body' => 'Body', 'status' => 'published', 'published_at' => now()->subDays($i)]);
        }
        \Illuminate\Support\Facades\Cache::forget('mm.blog_carousel.v1');
        $this->get(route('about'))->assertOk()->assertSee('data-blog-carousel', false)->assertSee('From the Naara blog')->assertSee('Story 1');
        $this->get(route('blog'))->assertOk()->assertDontSee('data-blog-carousel', false);
    }
}
