<?php

namespace Tests\Feature\N2N;

use App\Livewire\N2N\BrandProfileManage;
use App\Models\BrandPartner;
use App\Models\BrandPartnerImage;
use App\Models\BrandPartnerVideo;
use App\Models\N2N\BrandAlbum;
use App\Models\N2N\BrandEmbed;
use App\Models\N2N\BrandMusicLink;
use App\Models\Setting;
use App\Services\N2N\Brands\BrandExtras;
use App\Services\N2N\Brands\BrandLimitException;
use App\Services\N2N\Brands\EmbedParser;
use App\Services\N2N\Brands\EmbedRenderer;
use App\Services\N2N\Brands\LinkValidator;
use Livewire\Livewire;

/** N2N Batch 8: limits, host validation, embed parsing/XSS, albums, moderation (MASTER-ONLY). */
class BrandProfileTest extends N2NTestCase
{
    private function brand(array $attrs = [], ?int $ownerId = null): BrandPartner
    {
        return BrandPartner::create(array_merge(['owner_user_id' => $ownerId ?? $this->user()->id, 'brand_name' => 'Brand '.uniqid(), 'listing_status' => 'active', 'is_active' => true], $attrs));
    }

    private function music(BrandPartner $b): BrandPartner
    {
        app(BrandExtras::class)->requestMusic($b);
        app(BrandExtras::class)->confirmMusic($b->fresh(), $this->user()->id);

        return $b->fresh();
    }

    private function img(BrandPartner $b): BrandPartnerImage
    {
        return BrandPartnerImage::create(['brand_partner_id' => $b->id, 'image_path' => 'x.webp', 'sort_order' => 0]);
    }

    // ---- limits ------------------------------------------------------------------------------------------------

    public function test_general_profile_rejects_the_sixth_image_on_the_server(): void
    {
        $b = $this->brand();
        for ($i = 0; $i < 5; $i++) {
            $this->img($b);
        }
        $this->expectException(BrandLimitException::class);
        $this->img($b);
    }

    public function test_the_sixth_image_is_not_persisted(): void
    {
        $b = $this->brand();
        for ($i = 0; $i < 5; $i++) {
            $this->img($b);
        }
        try {
            $this->img($b);
        } catch (BrandLimitException) {
        }
        $this->assertSame(5, BrandPartnerImage::where('brand_partner_id', $b->id)->count());
    }

    public function test_requesting_music_unlocks_nothing_until_an_admin_confirms(): void
    {
        $b = $this->brand();
        app(BrandExtras::class)->requestMusic($b);
        $b = $b->fresh();
        for ($i = 0; $i < 5; $i++) {
            $this->img($b);
        }
        $this->expectException(BrandLimitException::class);
        $this->img($b);
    }

    public function test_confirmed_music_profile_allows_ten_images_and_rejects_the_eleventh(): void
    {
        $b = $this->music($this->brand());
        for ($i = 0; $i < 10; $i++) {
            $this->img($b);
        }
        $this->assertSame(10, BrandPartnerImage::where('brand_partner_id', $b->id)->count());
        $this->expectException(BrandLimitException::class);
        $this->img($b);
    }

    public function test_video_limits_are_per_platform_and_admin_settings_change_them(): void
    {
        $b = $this->brand();
        for ($i = 0; $i < 5; $i++) {
            BrandPartnerVideo::create(['brand_partner_id' => $b->id, 'video_url' => 'https://youtu.be/abcdefghi'.$i, 'platform' => 'youtube', 'sort_order' => $i]);
        }
        BrandPartnerVideo::create(['brand_partner_id' => $b->id, 'video_url' => 'https://vimeo.com/12345678', 'platform' => 'vimeo', 'sort_order' => 9]);   // vimeo has its own count
        try {
            BrandPartnerVideo::create(['brand_partner_id' => $b->id, 'video_url' => 'https://youtu.be/zzzzzzzzzz', 'platform' => 'youtube', 'sort_order' => 10]);
            $this->fail('6th youtube video should be refused');
        } catch (BrandLimitException) {
        }
        Setting::setValue('n2n.brand_limits.general.youtube', 6);
        BrandPartnerVideo::create(['brand_partner_id' => $b->id, 'video_url' => 'https://youtu.be/zzzzzzzzzz', 'platform' => 'youtube', 'sort_order' => 10]);
        $this->assertSame(6, BrandPartnerVideo::where('brand_partner_id', $b->id)->where('platform', 'youtube')->count());
    }

    public function test_limits_are_not_enforced_while_n2n_is_off(): void
    {
        Setting::setValue('n2n.enabled', false);
        $b = $this->brand();
        for ($i = 0; $i < 7; $i++) {
            $this->img($b);
        }
        $this->assertSame(7, BrandPartnerImage::where('brand_partner_id', $b->id)->count());
    }

    // ---- music-platform links ------------------------------------------------------------------------------------

    public function test_link_validator_accepts_real_hosts_and_normalises(): void
    {
        $this->assertSame('https://open.spotify.com/artist/abc', LinkValidator::validate('spotify', 'https://OPEN.spotify.com/artist/abc#frag'));
        $this->assertSame('https://music.apple.com/ng/album/x/1', LinkValidator::validate('apple_music', 'https://music.apple.com/ng/album/x/1'));
        $this->assertStringStartsWith('https://artist.bandcamp.com', LinkValidator::validate('bandcamp', 'https://artist.bandcamp.com/album/y'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badLinks')]
    public function test_link_validator_rejects_phishing_shorteners_and_odd_shapes(string $platform, string $url): void
    {
        $this->expectException(BrandLimitException::class);
        LinkValidator::validate($platform, $url);
    }

    public static function badLinks(): array
    {
        return [
            'suffix trick' => ['spotify', 'https://spotify.com.evil.tld/x'],
            'prefix trick' => ['spotify', 'https://evilspotify.com/x'],
            'userinfo' => ['spotify', 'https://open.spotify.com@evil.tld/x'],
            'http' => ['spotify', 'http://open.spotify.com/x'],
            'shortener' => ['spotify', 'https://bit.ly/abc'],
            'javascript' => ['spotify', 'javascript:alert(1)'],
            'wrong platform' => ['spotify', 'https://music.apple.com/x'],
            'unknown platform' => ['nope', 'https://open.spotify.com/x'],
            'odd port' => ['spotify', 'https://open.spotify.com:8443/x'],
            'space' => ['spotify', 'https://open.spotify.com/a b'],
            'quote' => ['spotify', 'https://open.spotify.com/"onmouseover=1'],
        ];
    }

    public function test_music_links_require_a_confirmed_profile_and_cap_at_twenty(): void
    {
        $b = $this->brand();
        try {
            app(BrandExtras::class)->addMusicLink($b, 'spotify', 'https://open.spotify.com/artist/a');
            $this->fail('general profile has no music links');
        } catch (BrandLimitException) {
        }
        $b = $this->music($b);
        for ($i = 0; $i < 20; $i++) {
            app(BrandExtras::class)->addMusicLink($b, 'spotify', 'https://open.spotify.com/artist/a'.$i);
        }
        $this->expectException(BrandLimitException::class);
        app(BrandExtras::class)->addMusicLink($b, 'spotify', 'https://open.spotify.com/artist/over');
    }

    // ---- embeds / XSS --------------------------------------------------------------------------------------------

    public function test_embed_parser_accepts_urls_and_a_single_clean_iframe(): void
    {
        $this->assertSame(['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ', 'kind' => 'video'], EmbedParser::parse('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        $this->assertSame(['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ', 'kind' => 'video'], EmbedParser::parse('<iframe width="560" src="https://www.youtube.com/embed/dQw4w9WgXcQ" allowfullscreen></iframe>'));
        $this->assertSame(['provider' => 'vimeo', 'id' => '123456789', 'kind' => 'video'], EmbedParser::parse('https://player.vimeo.com/video/123456789?h=abc'));
        $sp = EmbedParser::parse('<iframe src="https://open.spotify.com/embed/album/1DFixLWuPkv3KT3TnV35m3?utm_source=generator"></iframe>');
        $this->assertSame(['provider' => 'spotify', 'id' => 'album/1DFixLWuPkv3KT3TnV35m3', 'kind' => 'audio'], $sp);
        $this->assertSame('artist/track-name', EmbedParser::parse('https://soundcloud.com/artist/track-name')['id']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badEmbeds')]
    public function test_embed_parser_rejects_everything_unsafe(string $input): void
    {
        $this->expectException(BrandLimitException::class);
        EmbedParser::parse($input);
    }

    public static function badEmbeds(): array
    {
        $ok = 'https://www.youtube.com/embed/dQw4w9WgXcQ';

        return [
            'script tag' => ['<iframe src="'.$ok.'"></iframe><script>alert(1)</script>'],
            'onerror' => ['<iframe src="'.$ok.'" onerror="alert(1)"></iframe>'],
            'onload' => ['<iframe src="'.$ok.'" onload=alert(1)></iframe>'],
            'javascript scheme' => ['<iframe src="javascript:alert(1)"></iframe>'],
            'js url' => ['javascript:alert(1)'],
            'data url' => ['<iframe src="data:text/html,<script>alert(1)</script>"></iframe>'],
            'srcdoc' => ['<iframe srcdoc="<script>alert(1)</script>" src="'.$ok.'"></iframe>'],
            'nested iframe' => ['<iframe src="'.$ok.'"><iframe src="'.$ok.'"></iframe></iframe>'],
            'two iframes' => ['<iframe src="'.$ok.'"></iframe><iframe src="'.$ok.'"></iframe>'],
            'unknown provider' => ['https://evil.example/embed/abc12345'],
            'lookalike host' => ['https://www.youtube.com.evil.tld/embed/dQw4w9WgXcQ'],
            'http only' => ['http://www.youtube.com/embed/dQw4w9WgXcQ'],
            'img onerror' => ['<img src=x onerror=alert(1)>'],
            'object' => ['<object data="'.$ok.'"></object>'],
            'text around iframe' => ['hello <iframe src="'.$ok.'"></iframe>'],
            'empty' => ['   '],
            'huge' => [str_repeat('a', 2500)],
        ];
    }

    public function test_renderer_builds_its_own_sandboxed_iframe_and_never_echoes_input(): void
    {
        $f = EmbedRenderer::iframe(['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ']);
        $this->assertStringStartsWith('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $f['src']);
        $this->assertStringContainsString('allow-scripts', $f['sandbox']);
        $this->assertStringNotContainsString('allow-top-navigation', $f['sandbox']);
        $this->assertStringNotContainsString('allow-forms', $f['sandbox']);
        $this->assertNull(EmbedRenderer::iframe(['provider' => 'youtube', 'id' => '"><script>']));
        $this->assertNull(EmbedRenderer::iframe(['provider' => 'evil', 'id' => 'abcdefgh']));
        $this->assertNull(EmbedRenderer::iframe(['provider' => 'spotify', 'id' => '../../x']));
    }

    public function test_embeds_store_only_provider_and_id_and_cap_at_eight(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $b = $this->music($this->brand());
        $e = app(BrandExtras::class)->addEmbed($b, '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" title="x"></iframe>');
        $this->assertSame(['youtube', 'dQw4w9WgXcQ'], [$e->provider, $e->provider_id]);
        $this->assertStringNotContainsString('iframe', json_encode(BrandEmbed::first()->getAttributes()));
        for ($i = 0; $i < 7; $i++) {
            app(BrandExtras::class)->addEmbed($b, 'https://vimeo.com/12345670'.$i);
        }
        $this->expectException(BrandLimitException::class);
        app(BrandExtras::class)->addEmbed($b, 'https://vimeo.com/99999999');
    }

    public function test_the_csp_frame_src_gains_the_audio_players_only_while_n2n_is_on(): void
    {
        $base = "default-src 'self'; frame-src 'self' https://www.youtube-nocookie.com";
        $on = \App\Support\N2N\N2NEmbedCsp::apply($base);
        $this->assertStringContainsString('https://open.spotify.com', $on);
        $this->assertStringContainsString('https://w.soundcloud.com', $on);
        Setting::setValue('n2n.enabled', false);
        $this->assertSame($base, \App\Support\N2N\N2NEmbedCsp::apply($base));
    }

    // ---- albums, moderation, public pages -------------------------------------------------------------------------

    public function test_album_page_has_og_tags_validated_links_and_cascades_on_delete(): void
    {
        $b = $this->music($this->brand(['brand_name' => 'Nova Beats']));
        $x = app(BrandExtras::class);
        $album = $x->createAlbum($b, 'Lagos Nights', 'Ten songs.');
        $album->update(['published_at' => now()]);   // a new album is a draft until the artist publishes it
        $this->assertSame('lagos-nights', $album->slug);
        $this->assertSame('lagos-nights-2', $x->createAlbum($b, 'Lagos Nights')->slug);
        $x->addAlbumLink($album, 'spotify', 'https://open.spotify.com/album/abc');
        try {
            $x->addAlbumLink($album, 'spotify', 'https://spotify.com.evil.tld/a');
            $this->fail('bad host');
        } catch (BrandLimitException) {
        }

        $res = $this->get(route('n2n.album', ['brand' => $b->id, 'slug' => 'lagos-nights']));
        $res->assertOk()->assertSee('Lagos Nights')->assertSee('https://open.spotify.com/album/abc', false)->assertSee('og:title', false);

        $x->deleteAlbum($b, $album->id);
        $this->assertSame(0, \App\Models\N2N\BrandAlbumLink::count());
        $this->get(route('n2n.album', ['brand' => $b->id, 'slug' => 'lagos-nights']))->assertNotFound();
    }

    public function test_albums_belong_to_the_music_profile_only(): void
    {
        $this->expectException(BrandLimitException::class);
        app(BrandExtras::class)->createAlbum($this->brand(), 'Nope');
    }

    public function test_hidden_album_and_unlisted_brand_both_404_and_hidden_links_disappear_from_the_profile(): void
    {
        $b = $this->music($this->brand());
        $x = app(BrandExtras::class);
        $album = $x->createAlbum($b, 'Hide me');
        $album->update(['published_at' => now()]);
        $link = $x->addMusicLink($b, 'spotify', 'https://open.spotify.com/artist/seen');
        $viewer = $this->user();

        $this->actingAs($viewer)->get(route('rewards.hunt.profile', $b))->assertOk()->assertSee('open.spotify.com/artist/seen', false);
        $x->setHidden('music', $link->id, true, $viewer->id);
        $x->setHidden('album', $album->id, true, $viewer->id);
        $this->get(route('rewards.hunt.profile', $b))->assertOk()->assertDontSee('open.spotify.com/artist/seen', false);
        $this->get(route('n2n.album', ['brand' => $b->id, 'slug' => $album->slug]))->assertNotFound();
        $x->setHidden('album', $album->id, false, $viewer->id);
        $this->get(route('n2n.album', ['brand' => $b->id, 'slug' => $album->slug]))->assertOk();

        $b->forceFill(['listing_status' => 'paused_billing'])->save();
        $this->get(route('n2n.album', ['brand' => $b->id, 'slug' => $album->slug]))->assertNotFound();
    }

    public function test_message_cta_shows_only_for_a_naara_pro_owner(): void
    {
        $owner = $this->user(['naara_id' => \App\Services\N2N\Identity\NaaraId::generate()]);
        $b = $this->brand([], $owner->id);
        $viewer = $this->user();
        $this->actingAs($viewer)->get(route('rewards.hunt.profile', $b))->assertOk()->assertDontSee('Message '.$b->brand_name);
        $b->forceFill(['pro_granted_at' => now()])->save();
        $this->actingAs($viewer)->get(route('rewards.hunt.profile', $b))->assertOk()->assertSee('Message '.$b->brand_name);
    }

    // ---- owner UI ---------------------------------------------------------------------------------------------------

    public function test_the_owner_form_only_ever_touches_the_owners_own_brand(): void
    {
        $owner = $this->user();
        $mine = $this->brand([], $owner->id);
        $theirs = $this->music($this->brand());
        $theirAlbum = app(BrandExtras::class)->createAlbum($theirs, 'Theirs');

        $this->actingAs($owner);
        Livewire::test(BrandProfileManage::class)->set('socialUrl', 'https://instagram.com/me')->call('addSocial')->assertSet('message', 'Link added.');
        $this->assertSame($mine->id, \App\Models\N2N\BrandSocialLink::first()->brand_partner_id);

        // Even with the other brand's album id posted, nothing is added to it and nothing leaks.
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $mine = $this->music($mine);
        Livewire::test(BrandProfileManage::class)->call('editAlbum', $theirAlbum->id);
    }

    public function test_a_user_without_a_brand_gets_404_on_the_owner_page(): void
    {
        $this->actingAs($this->user())->get(route('n2n.brand.manage'))->assertNotFound();
    }

    public function test_the_owner_form_shows_the_limit_message_instead_of_failing(): void
    {
        $owner = $this->user();
        $b = $this->brand([], $owner->id);
        $b = $this->music($b);
        $this->actingAs($owner);
        Livewire::test(BrandProfileManage::class)->set('socialPlatform', 'instagram')->set('socialUrl', 'https://evil.tld/x')->call('addSocial')->assertSet('error', 'That link does not belong to Instagram.');
    }

    public function test_admin_can_confirm_and_revoke_music_profiles(): void
    {
        $admin = $this->user(['role' => 'super_admin']);
        $b = $this->brand();
        app(BrandExtras::class)->requestMusic($b);
        $this->actingAs($admin);
        Livewire::test(\App\Livewire\Admin\N2N\BrandProfiles::class)->call('confirmMusic', $b->id);
        $this->assertSame('music', \App\Services\N2N\Brands\BrandLimits::tier($b->fresh()));
        Livewire::test(\App\Livewire\Admin\N2N\BrandProfiles::class)->call('revokeMusic', $b->id);
        $this->assertSame('general', \App\Services\N2N\Brands\BrandLimits::tier($b->fresh()));
    }

    public function test_apple_boomplay_amazon_and_spotify_album_embeds_parse_and_render_safely(): void
    {
        $cases = [
            'https://embed.music.apple.com/gb/song/no-suffer-in-silence/1836345113' => ['apple_music', 'gb/song/no-suffer-in-silence/1836345113', 'https://embed.music.apple.com/gb/song/no-suffer-in-silence/1836345113', 175],
            'https://www.boomplay.com/embed/215002137/MUSIC?colType=2&colID=114796238' => ['boomplay', 'MUSIC:215002137:2:114796238', 'https://www.boomplay.com/embed/215002137/MUSIC?colType=2&colID=114796238', 300],
            'https://www.boomplay.com/embed/116247626/COL' => ['boomplay', 'COL:116247626', 'https://www.boomplay.com/embed/116247626/COL', 450],
            'https://open.spotify.com/embed/album/0iAzlrcICl76d7uLvtkV8k' => ['spotify', 'album/0iAzlrcICl76d7uLvtkV8k', 'https://open.spotify.com/embed/album/0iAzlrcICl76d7uLvtkV8k', 352],
            'https://music.amazon.com/embed/B0FQ7XJWH5/?id=4tIwrkSYSu&marketplaceId=ATVPDKIKX0DER&musicTerritory=US' => ['amazon_music', 'B0FQ7XJWH5|4tIwrkSYSu|ATVPDKIKX0DER|US', 'https://music.amazon.com/embed/B0FQ7XJWH5/?id=4tIwrkSYSu&marketplaceId=ATVPDKIKX0DER&musicTerritory=US', 352],
        ];
        foreach ($cases as $url => [$provider, $id, $src, $height]) {
            $p = \App\Services\N2N\Brands\EmbedParser::parse($url);
            $this->assertSame([$provider, $id, 'audio'], [$p['provider'], $p['id'], $p['kind']], $url);
            $f = \App\Services\N2N\Brands\EmbedRenderer::iframe(['provider' => $p['provider'], 'id' => $p['id']]);
            $this->assertSame($src, $f['src']);
            $this->assertSame($height, $f['height']);
        }
        foreach (['https://embed.music.apple.com.evil.tld/gb/song/x/1836345113', 'https://www.boomplay.com/embed/12/MUSIC', 'https://music.amazon.com/embed/B0FQ7XJWH5/?id=x', 'http://embed.music.apple.com/gb/song/a/1836345113', 'https://music.amazon.com@evil.tld/embed/B0FQ7XJWH5/'] as $bad) {
            $this->assertNull(\App\Services\N2N\Brands\EmbedParser::fromUrl($bad), $bad);
        }
        $this->assertStringContainsString('https://embed.music.apple.com', \App\Support\N2N\N2NEmbedCsp::ORIGINS[2]);
    }
}
