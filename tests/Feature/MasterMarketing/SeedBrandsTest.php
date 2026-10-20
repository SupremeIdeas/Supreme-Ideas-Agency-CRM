<?php

namespace Tests\Feature\MasterMarketing;

use App\Models\BrandPartner;
use App\Models\User;
use App\Services\Brands\BrandSubscriptionService;
use App\Services\Brands\BrandVisibility;
use App\Support\SocialLinks;
use Database\Seeders\BrandPlanSeeder;
use Database\Seeders\DefaultAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeedBrandsTest extends TestCase
{
    use RefreshDatabase;

    private function prepare(): void
    {
        (new \Database\Seeders\RoleSeeder)->run();
        (new DefaultAdminSeeder)->run();
        (new BrandPlanSeeder)->run();
    }

    public function test_seeds_four_live_pro_listings_and_site_links(): void
    {
        $this->prepare();
        $this->artisan('naara:seed-brands')->assertSuccessful();

        $this->assertSame(4, BrandPartner::query()->count());
        foreach (BrandPartner::with('subscription', 'handles')->get() as $b) {
            $this->assertTrue($b->hasProGrant(), $b->brand_name);
            $this->assertNull($b->pro_grant_expires_at, 'lifetime');
            $this->assertTrue(BrandVisibility::passes($b), $b->brand_name.' must pass the content gate: '.implode(',', BrandVisibility::missing($b)));
            $this->assertSame('live', BrandVisibility::reason($b) === 'live' ? 'live' : BrandVisibility::reason($b), $b->brand_name);
            $this->assertFileExists(public_path(ltrim($b->logo_path, '/')));
            $this->assertFileExists(public_path(ltrim($b->hero_image_path, '/')));
        }
        $this->assertSame(DefaultAdminSeeder::EMAIL, BrandPartner::where('brand_name', 'Naara')->first()->owner->email);
        $u = User::where('email', 'whytenationmusic@gmail.com')->first();
        $this->assertTrue(Hash::check('whytenationmusic@gmail.com', $u->password));
        $this->assertSame('https://x.com/NaaraFamily', SocialLinks::all()['x']);
        $this->assertSame(7, BrandPartner::where('brand_name', 'Whyte Nation Music')->first()->handles->count());
    }

    public function test_naara_is_in_the_follow_band_and_the_directory_without_double_pay(): void
    {
        $this->prepare();
        $this->artisan('naara:seed-brands')->assertSuccessful();
        $this->assertSame(5, \App\Models\SocialFollowHandle::active()->count());
        $naara = BrandPartner::where('brand_name', 'Naara')->first();
        $this->assertTrue($naara->is_featured);
        $this->assertTrue(BrandPartner::listed()->whereKey($naara->id)->exists());
        foreach ($naara->handles as $h) {
            $this->assertSame(0.0, $h->effectiveCreditReward(), 'an official handle must not pay twice');
        }
        $other = BrandPartner::where('brand_name', 'Essential Farm')->first()->handles->first();
        $this->assertGreaterThanOrEqual(0.0, $other->effectiveCreditReward());
        $this->artisan('naara:seed-brands')->assertSuccessful();
        $this->assertSame(5, \App\Models\SocialFollowHandle::count());
    }

    public function test_whyte_nation_gets_a_confirmed_music_profile_with_every_link_and_embed_and_the_others_get_youtube_previews(): void
    {
        $this->prepare();
        $this->artisan('naara:seed-brands')->assertSuccessful();
        $w = BrandPartner::where('brand_name', 'Whyte Nation Music')->first();
        $this->assertNotNull($w->music_verified_at);
        $this->assertSame(['apple_music', 'boomplay', 'spotify', 'youtube'], \App\Models\N2N\BrandMusicLink::where('brand_partner_id', $w->id)->pluck('platform')->sort()->values()->all());
        $embeds = \App\Models\N2N\BrandEmbed::where('brand_partner_id', $w->id)->get();
        $this->assertSame(6, $embeds->count());
        $this->assertEqualsCanonicalizing(['apple_music', 'spotify', 'spotify', 'boomplay', 'boomplay', 'amazon_music'], $embeds->pluck('provider')->all());
        foreach ($embeds as $e) {
            $this->assertNotNull(\App\Services\N2N\Brands\EmbedRenderer::iframe($e), $e->provider);
        }
        $farm = BrandPartner::where('brand_name', 'Essential Farm')->first();
        $this->assertSame(['https://youtube.com/shorts/aU_Q6VV6xt4', 'https://youtu.be/Hgpm75G961I'], $farm->videos()->orderBy('sort_order')->pluck('video_url')->all());
        foreach ($farm->videos as $v) {
            $this->assertNotNull($v->embedUrl());
        }
        $this->assertSame(2, BrandPartner::where('brand_name', 'Supreme Ideas Agency')->first()->videos()->count());
        $this->artisan('naara:seed-brands')->assertSuccessful();   // a re-run adds nothing
        $this->assertSame(6, \App\Models\N2N\BrandEmbed::where('brand_partner_id', $w->id)->count());
        $this->assertSame(2, $farm->videos()->count());
    }

    public function test_old_demo_listings_are_removed_but_a_members_same_name_brand_is_kept(): void
    {
        $this->prepare();
        BrandPartner::create(['brand_name' => 'Sahara Airways', 'listing_status' => 'active', 'is_active' => true]);
        $mine = BrandPartner::create(['brand_name' => 'Lagos Eats', 'owner_user_id' => User::factory()->create()->id, 'listing_status' => 'active', 'is_active' => true]);
        $this->artisan('naara:seed-brands')->assertSuccessful();
        $this->assertSame(0, BrandPartner::where('brand_name', 'Sahara Airways')->count());
        $this->assertTrue(BrandPartner::whereKey($mine->id)->exists());
        $this->assertSame(5, BrandPartner::count());
    }

    public function test_rerun_never_overwrites_edits_or_passwords(): void
    {
        $this->prepare();
        $this->artisan('naara:seed-brands')->assertSuccessful();
        $b = BrandPartner::where('brand_name', 'Essential Farm')->first();
        $b->update(['short_description' => 'Edited by the owner']);
        $b->owner->forceFill(['password' => Hash::make('NewSecret#1')])->save();
        SocialLinks::save(['x' => 'https://x.com/edited'] + SocialLinks::all());

        $this->artisan('naara:seed-brands')->assertSuccessful();

        $this->assertSame(4, BrandPartner::count());
        $this->assertSame('Edited by the owner', $b->fresh()->short_description);
        $this->assertTrue(Hash::check('NewSecret#1', $b->owner->fresh()->password));
        $this->assertSame('https://x.com/edited', SocialLinks::all()['x']);
        $this->assertSame(4, $b->handles()->count());
    }

    public function test_expiring_grant_stops_counting_as_pro(): void
    {
        $this->prepare();
        $admin = User::first();
        $m = User::factory()->create();
        $plan = \App\Models\BrandSubscriptionPlan::first();
        $b = app(BrandSubscriptionService::class)->grantPro($m, $plan, $admin, null, now()->addYear());
        $this->assertTrue($b->fresh()->hasProGrant());
        $b->forceFill(['pro_grant_expires_at' => now()->subDay()])->save();
        $this->assertFalse($b->fresh()->hasProGrant());
        $this->assertSame('none', BrandVisibility::entitlement($b->fresh()));
    }

    public function test_a_fresh_install_seed_fills_the_brand_directory_with_the_owners_brands_and_whyte_albums(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $names = BrandPartner::query()->orderBy('brand_name')->pluck('brand_name')->all();
        $this->assertSame(['Essential Farm', 'Naara', 'Supreme Ideas Agency', 'Whyte Nation Music'], $names);

        $whyte = BrandPartner::query()->where('brand_name', 'Whyte Nation Music')->firstOrFail();
        $this->assertGreaterThanOrEqual(3, $whyte->handles()->count(), 'Whyte Nation social handles');
        $this->assertGreaterThanOrEqual(1, \App\Models\BrandLink::query()->where('brand_partner_id', $whyte->id)->count(), 'Whyte Nation website link');
        $this->assertGreaterThanOrEqual(3, \App\Models\N2N\BrandMusicLink::query()->where('brand_partner_id', $whyte->id)->count(), 'Whyte Nation streaming links');
        $this->assertGreaterThanOrEqual(3, \App\Models\N2N\BrandAlbum::query()->where('brand_partner_id', $whyte->id)->count(), 'Whyte Nation albums');
        $this->assertTrue(BrandVisibility::passes($whyte->fresh('subscription', 'handles')));
        $this->assertFileExists(public_path(ltrim($whyte->logo_path, '/')));
    }
}
