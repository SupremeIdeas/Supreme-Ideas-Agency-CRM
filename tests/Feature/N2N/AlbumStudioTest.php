<?php

namespace Tests\Feature\N2N;

use App\Livewire\N2N\BrandProfileManage;
use App\Models\BrandPartner;
use App\Models\BrandSubscriptionPlan;
use App\Models\N2N\BrandAlbum;
use App\Services\N2N\Brands\BrandExtras;
use App\Services\N2N\Brands\BrandLimitException;
use App\Services\N2N\Brands\BrandLimits;
use App\Services\N2N\Brands\LinkValidator;
use App\Support\N2N\MusicLogos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** The artist album studio (N2N, MASTER-ONLY): cover, distributor smart link, logos, publish, caps, order, carousel and album page. */
class AlbumStudioTest extends N2NTestCase
{
    private function music(?int $ownerId = null, array $attrs = []): BrandPartner
    {
        $b = BrandPartner::create(array_merge(['owner_user_id' => $ownerId ?? $this->user()->id, 'brand_name' => 'Zayon '.uniqid(), 'listing_status' => 'active', 'is_active' => true, 'category' => 'Music & Artists'], $attrs));
        app(BrandExtras::class)->requestMusic($b);
        app(BrandExtras::class)->confirmMusic($b->fresh(), $this->user()->id);

        return $b->fresh();
    }

    private function cover(int $w = 800, int $h = 800, string $name = 'cover.png'): UploadedFile
    {
        $im = imagecreatetruecolor($w, $h);
        ob_start();
        imagepng($im);

        return UploadedFile::fake()->createWithContent($name, (string) ob_get_clean());
    }

    private function data(array $over = []): array
    {
        return $over + ['title' => 'Relationship Palava 2', 'description' => 'Afrobeat and reggae.', 'release_date' => '2026-03-14', 'distributor' => 'ditto', 'smart_url' => 'https://ditto.fm/relationship-palava-2',
            'links' => [['platform' => 'spotify', 'url' => 'https://open.spotify.com/album/0iAzlrcICl76d7uLvtkV8k']], 'publish' => false];
    }

    public function test_a_draft_needs_only_a_title_and_publishing_needs_a_cover_and_a_way_to_listen(): void
    {
        Storage::fake(\App\Support\MediaStorage::disk());
        $b = $this->music();
        $x = app(BrandExtras::class);
        $draft = $x->saveAlbum($b, null, ['title' => 'Untitled demo']);
        $this->assertNull($draft->published_at);
        $this->get(route('n2n.album', ['brand' => $b->id, 'slug' => $draft->slug]))->assertNotFound();

        try {
            $x->saveAlbum($b, $draft->id, $this->data(['publish' => true, 'smart_url' => null, 'links' => []]));
            $this->fail('no cover');
        } catch (BrandLimitException $e) {
            $this->assertStringContainsString('cover', $e->getMessage());
        }
        try {
            $x->saveAlbum($b, $draft->id, $this->data(['publish' => true, 'smart_url' => null, 'links' => []]), $this->cover());
            $this->fail('no links');
        } catch (BrandLimitException $e) {
            $this->assertStringContainsString('smart link', $e->getMessage());
        }
        $live = $x->saveAlbum($b, $draft->id, $this->data(['publish' => true]), $this->cover());
        $this->assertNotNull($live->published_at);
        $this->assertNotEmpty($live->cover_path);
        $this->assertSame('ditto', $live->distributor);
        $this->assertSame('https://ditto.fm/relationship-palava-2', $live->smart_url);
        $this->assertSame(1, $live->links->count());

        $page = $this->get(route('n2n.album', ['brand' => $b->id, 'slug' => $live->slug]))->assertOk();
        $page->assertSee('Listen everywhere', false)->assertSee('via Ditto Music', false)->assertSee('images/music/distributors/ditto-white.svg', false)->assertSee('images/music/platforms/spotify.webp', false)->assertSee($live->cover_path, false);
        $this->actingAs($this->user())->get(route('rewards.hunt.profile', $b))->assertSee('data-album-carousel', false)->assertSee('Relationship Palava 2');
    }

    public function test_smart_links_must_belong_to_the_distributor_or_a_smart_link_service(): void
    {
        $this->assertSame('https://ditto.fm/x', LinkValidator::validateSmartLink('ditto', 'https://ditto.fm/x#frag'));
        $this->assertSame('https://songwhip.com/a/b', LinkValidator::validateSmartLink('ditto', 'https://songwhip.com/a/b'));
        $this->assertSame('https://distrokid.com/hyperfollow/a/b', LinkValidator::validateSmartLink('distrokid', 'https://distrokid.com/hyperfollow/a/b'));
        foreach (['https://ditto.fm.evil.tld/x', 'https://evilditto.fm/x', 'http://ditto.fm/x', 'https://ditto.fm@evil.tld/x', 'https://example.com/x', 'javascript:alert(1)', 'https://ditto.fm:8443/x'] as $bad) {
            try {
                LinkValidator::validateSmartLink('ditto', $bad);
                $this->fail($bad);
            } catch (BrandLimitException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_cover_rules_and_ownership(): void
    {
        Storage::fake(\App\Support\MediaStorage::disk());
        $b = $this->music();
        $x = app(BrandExtras::class);
        foreach ([$this->cover(300, 300), UploadedFile::fake()->createWithContent('x.png', 'not an image')] as $bad) {
            try {
                $x->saveAlbum($b, null, ['title' => 'T'], $bad);
                $this->fail('bad cover');
            } catch (BrandLimitException $e) {
                $this->assertSame('cover', $e->reason);
            }
        }
        $this->assertSame(0, BrandAlbum::count(), 'a refused cover never creates the album');
        $theirs = $this->music();
        $album = $x->saveAlbum($theirs, null, ['title' => 'Theirs']);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $x->saveAlbum($b, $album->id, ['title' => 'Hijack']);   // an id from another brand is never editable
    }

    public function test_the_album_cap_applies_and_the_top_plan_is_unlimited(): void
    {
        $b = $this->music();
        $x = app(BrandExtras::class);
        \App\Models\Setting::setValue('n2n.brand_limits.music.albums', 2);
        $x->saveAlbum($b, null, ['title' => 'One']);
        $x->saveAlbum($b, null, ['title' => 'Two']);
        $this->assertSame(2, BrandLimits::albumCap($b));
        try {
            $x->saveAlbum($b, null, ['title' => 'Three']);
            $this->fail('cap');
        } catch (BrandLimitException $e) {
            $this->assertStringContainsString('at most 2 albums', $e->getMessage());
        }
        $low = BrandSubscriptionPlan::create(['name' => 'Starter', 'price_usd_per_month' => 19, 'handles_included' => 1, 'guaranteed_followers_per_handle_per_month' => 50, 'video_previews_allowed' => 0, 'credit_reward_per_follow' => 1, 'sort_order' => 1, 'is_active' => true]);
        $top = BrandSubscriptionPlan::create(['name' => 'Spotlight', 'price_usd_per_month' => 99, 'handles_included' => 5, 'guaranteed_followers_per_handle_per_month' => 350, 'video_previews_allowed' => 2, 'credit_reward_per_follow' => 8, 'sort_order' => 3, 'is_active' => true]);
        $b->forceFill(['current_plan_id' => $low->id, 'pro_granted_at' => now()])->save();
        $this->assertSame(2, BrandLimits::albumCap($b->fresh()), 'a lower plan keeps the cap');
        $b->forceFill(['current_plan_id' => $top->id])->save();
        $this->assertSame(PHP_INT_MAX, BrandLimits::albumCap($b->fresh()));
        $x->saveAlbum($b->fresh(), null, ['title' => 'Three']);
        $this->assertSame(3, BrandAlbum::where('brand_partner_id', $b->id)->count());
    }

    public function test_albums_can_be_reordered_and_the_carousel_follows_that_order(): void
    {
        Storage::fake(\App\Support\MediaStorage::disk());
        $b = $this->music();
        $x = app(BrandExtras::class);
        $a = $x->saveAlbum($b, null, $this->data(['title' => 'First', 'publish' => true]), $this->cover());
        $c = $x->saveAlbum($b, null, $this->data(['title' => 'Second', 'publish' => true]), $this->cover());
        $this->actingAs($this->user())->get(route('rewards.hunt.profile', $b))->assertSeeInOrder(['First', 'Second']);
        $x->moveAlbum($b, $c->id, -1);
        $this->actingAs($this->user())->get(route('rewards.hunt.profile', $b))->assertSeeInOrder(['Second', 'First']);
        $x->moveAlbum($b, $c->id, -1);   // already first: nothing changes
        $this->assertSame([$c->id, $a->id], BrandAlbum::where('brand_partner_id', $b->id)->orderBy('sort_order')->pluck('id')->all());
    }

    public function test_the_studio_screen_saves_a_draft_then_publishes_from_the_form(): void
    {
        Storage::fake(\App\Support\MediaStorage::disk());
        $owner = $this->user();
        $b = $this->music($owner->id);
        $this->actingAs($owner);
        $t = Livewire::test(BrandProfileManage::class)->call('newAlbum')->assertSee('Your distributor')->assertSee('Distributor smart link')
            ->set('albumTitle', 'The Smart Lover')->set('albumDistributor', 'ditto')->set('albumSmartUrl', 'https://example.com/nope')->call('saveAlbum', true)
            ->assertSet('error', fn ($e) => str_contains((string) $e, 'cover') || str_contains((string) $e, 'smart link'));
        $t->set('albumCover', $this->cover())->set('albumSmartUrl', 'https://ditto.fm/the-smart-lover')->call('saveAlbum', true)->assertSet('error', null)->assertSet('albumId', null);
        $a = BrandAlbum::where('brand_partner_id', $b->id)->first();
        $this->assertNotNull($a->published_at);
        $t->call('unpublishAlbum', $a->id);
        $this->assertNull($a->fresh()->published_at);
        $this->assertNotNull($a->fresh()->cover_path, 'unpublishing keeps the draft and its cover');
    }

    public function test_logos_come_from_committed_files_and_a_missing_mark_never_invents_one(): void
    {
        foreach (['spotify', 'apple_music', 'amazon_music', 'youtube', 'youtube_music', 'audiomack', 'deezer', 'tidal', 'soundcloud', 'x', 'tiktok', 'instagram', 'facebook'] as $p) {
            $this->assertNotNull(MusicLogos::platform($p)['url'], $p);
            $this->assertFileExists(public_path('images/music/platforms/'.$p.'.webp'));
        }
        $this->assertNull(MusicLogos::platform('boomplay')['url']);
        $this->assertSame('B', MusicLogos::platform('boomplay')['initial']);
        $this->assertTrue(MusicLogos::distributor('ditto')['dark']);
        $this->assertNotNull(MusicLogos::distributor('distrokid')['url']);
        $this->assertNull(MusicLogos::distributor('tunecore')['url'], 'no bundled mark: the name is shown');
        $this->assertNull(MusicLogos::distributor('nope'));
    }
}
