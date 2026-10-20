<?php

namespace App\Console\Commands;

use App\Models\BrandLink;
use App\Models\BrandPartner;
use App\Models\BrandPartnerHandle;
use App\Models\BrandPartnerVideo;
use App\Models\BrandSubscriptionPlan;
use App\Models\SocialFollowHandle;
use App\Models\User;
use App\Services\BrandLinks\PublicSuffix;
use App\Services\Brands\BrandSubscriptionService;
use App\Services\Brands\BrandVisibility;
use App\Support\Auditor;
use App\Support\SocialLinks;
use Database\Seeders\DefaultAdminSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds the owner's own brand listings and the Naara site-wide social links (MASTER-ONLY, run once after install: `php artisan naara:seed-brands`).
 *
 * Everything here is a starting point, never an overwrite: an existing account keeps its password, an existing listing keeps whatever was edited, a social link already saved stays.
 * New member accounts start with the owner's chosen first password (their own email address). CHANGE EACH ONE after the first login; the command says so every run.
 * Naara itself belongs to the existing super admin and gets lifetime Pro; the other three get lifetime Pro from that admin too (change the term any time in Admin > Brand Directory).
 */
class MasterMarketingSeedBrandsCommand extends Command
{
    protected $signature = 'naara:seed-brands {--dry : Show what would be created without writing}';

    protected $description = 'Seed the Naara, Supreme Ideas Agency, Whyte Nation Music and Essential Farm listings (idempotent, never overwrites edits)';

    public function handle(BrandSubscriptionService $subs): int
    {
        $admin = User::query()->where('email', DefaultAdminSeeder::EMAIL)->first() ?? User::query()->where('role', 'super_admin')->orderBy('id')->first();
        if (! $admin) {
            $this->error('No super admin exists yet. Finish the installer, then run this again.');

            return self::FAILURE;
        }
        $plan = BrandSubscriptionPlan::query()->where('is_active', true)->orderByDesc('sort_order')->first();
        if (! $plan) {
            $this->error('There is no Pro plan yet. Run `php artisan db:seed --class=BrandPlanSeeder` first.');

            return self::FAILURE;
        }
        $dry = (bool) $this->option('dry');
        $created = [];
        if (! $dry && ($gone = $this->purgeDemoBrands()) > 0) {
            $this->info("Removed {$gone} old demo brand listing(s).");
        }

        foreach (self::brands() as $b) {
            $owner = $b['owner_email'] === null ? $admin : User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($b['owner_email'])])->first();
            if (! $owner) {
                if ($dry) {
                    $this->line("[dry] would create account {$b['owner_email']} and the {$b['name']} listing");

                    continue;
                }
                $owner = User::create(['name' => $b['name'], 'email' => $b['owner_email'], 'password' => Hash::make($b['owner_email']), 'is_active' => true, 'email_verified_at' => now(), 'referral_code' => Str::upper(Str::random(8))]);
                $created[] = $b['owner_email'];
            }
            if ($dry) {
                $this->line("[dry] would seed the {$b['name']} listing for {$owner->email}");

                continue;
            }

            $brand = BrandPartner::query()->where('owner_user_id', $owner->id)->first();
            if (! $brand) {
                $brand = BrandPartner::create([
                    'owner_user_id' => $owner->id, 'brand_name' => $b['name'], 'short_description' => $b['description'], 'category' => $b['category'], 'listing_status' => BrandPartner::STATUS_ACTIVE,
                    'is_active' => true, 'is_featured' => $b['slug'] === 'naara', 'background_color' => $b['color'], 'hero_image_path' => '/images/brand-partners/'.$b['slug'].'/cover.webp', 'logo_path' => '/images/brand-partners/'.$b['slug'].'/logo.webp',
                    'current_plan_id' => $plan->id, 'gate_version' => BrandVisibility::GATE_V2, 'published_at' => now(),
                ]);
            }
            foreach ($b['handles'] as $i => [$platform, $label, $url]) {
                BrandPartnerHandle::query()->firstOrCreate(['brand_partner_id' => $brand->id, 'handle_url' => $url], ['platform' => $platform, 'handle_label' => $label, 'credit_reward' => 0, 'verification' => 'self', 'sort_order' => $i, 'is_active' => true]);
            }
            foreach ($b['links'] as $i => [$label, $url]) {
                $this->addLink($brand, $label, $url, $i);
            }
            $this->seedVideos($brand, $b['videos'] ?? []);
            $this->seedMusicProfile($brand, $b, $admin);
            if (! $brand->hasProGrant()) {
                $subs->grantPro($owner, $plan, $admin, 'Owner brand: lifetime Naara Pro', null);
            }
            $this->info("Seeded {$b['name']} ({$owner->email}).");
        }

        if (! $dry) {
            $official = $this->officialFollowHandles();
            $this->info($official === 0 ? 'Official Naara follow handles were already set; left as they are.' : "Added {$official} official Naara follow handle(s) to the first spot of the rewards Hunt.");
            if (isset(\App\Support\ServiceIcons::overrides()['google'])) {
                \App\Support\ServiceIcons::removeOverride('google');   // the committed Google mark is the only one used (Verify, Rent, login); an old custom upload must not override it
                $this->info('Removed an old custom Google icon override; the official mark is used everywhere.');
            }
            if (! filled(\App\Models\Setting::getValue('brand.name'))) {
                \App\Models\Setting::setValue('brand.name', 'Naara', 'brand');   // the product is Naara; editable in Admin > Branding
                \App\Support\BrandSettings::flush();
                $this->info('Product name set to Naara.');
            }
            $filled = $this->siteSocialLinks();
            $this->info($filled === [] ? 'Site-wide social links were already set; left as they are.' : 'Site-wide social links added: '.implode(', ', $filled).'.');
            Auditor::log('brands.owner_seeded', 'System', 0, ['new_accounts' => count($created)]);
        }
        foreach ($created as $email) {
            $this->warn("New account {$email}: its first password is its own email address. Change it after the first login.");
        }

        return self::SUCCESS;
    }

    /** The four placeholder brands the old demo seeder created. Only ones with no owner are removed, so a real member's listing that shares a name is never touched. */
    private function purgeDemoBrands(): int
    {
        $n = 0;
        foreach (BrandPartner::query()->whereNull('owner_user_id')->whereIn('brand_name', ['Sahara Airways', 'Lagos Eats', 'Naija Fashion Co', 'Sahara Connect Shop'])->get() as $b) {
            $b->delete();
            $n++;
        }

        return $n;
    }

    /** YouTube previews shown on the profile (Hunt video previews). Matched by video id so a re-run never adds a second copy. */
    private function seedVideos(BrandPartner $brand, array $urls): void
    {
        foreach ($urls as $i => $url) {
            $id = \App\Services\N2N\Brands\EmbedParser::fromUrl($url)['id'] ?? null;
            if ($id === null || BrandPartnerVideo::query()->where('brand_partner_id', $brand->id)->where('video_url', 'like', '%'.$id.'%')->exists()) {
                continue;
            }
            BrandPartnerVideo::query()->create(['brand_partner_id' => $brand->id, 'video_url' => $url, 'platform' => 'youtube', 'sort_order' => $i]);
        }
    }

    /** Premium music profile: confirm it, then add the artist's streaming links and embedded releases through the same validators the owner uses. Needs the N2N module; skipped when it is absent. */
    private function seedMusicProfile(BrandPartner $brand, array $b, User $admin): void
    {
        if (empty($b['music']) && empty($b['embeds']) && empty($b['albums'])) {
            return;
        }
        if (! class_exists(\App\Services\N2N\Brands\BrandExtras::class)) {
            return;
        }
        $x = app(\App\Services\N2N\Brands\BrandExtras::class);
        if ($brand->music_verified_at === null) {
            $x->confirmMusic($brand, $admin->id);
            $brand->refresh();
        }
        foreach ((array) ($b['music'] ?? []) as [$platform, $url]) {
            if (\App\Models\N2N\BrandMusicLink::query()->where('brand_partner_id', $brand->id)->where('platform', $platform)->exists()) {
                continue;
            }
            try {
                $x->addMusicLink($brand, $platform, $url);
            } catch (\App\Services\N2N\Brands\BrandLimitException $e) {
                $this->warn("Music link {$platform} skipped: ".$e->getMessage());
            }
        }
        foreach ((array) ($b['embeds'] ?? []) as $url) {
            try {
                $x->addEmbed($brand, $url);
            } catch (\App\Services\N2N\Brands\BrandLimitException $e) {
                if ($e->getMessage() !== 'That player is already on your profile.') {
                    $this->warn('Embed skipped: '.$e->getMessage());
                }
            }
        }
        $this->seedAlbums($brand, (array) ($b['albums'] ?? []));
    }

    /** Albums with committed cover art, matched by slug so a re-run never duplicates or overwrites the artist's edits. The distributor smart link is left empty for the owner to add. */
    private function seedAlbums(BrandPartner $brand, array $albums): void
    {
        foreach ($albums as $i => $a) {
            if (\App\Models\N2N\BrandAlbum::query()->where('brand_partner_id', $brand->id)->where('slug', $a['slug'])->exists()) {
                continue;
            }
            $album = \App\Models\N2N\BrandAlbum::query()->create([
                'brand_partner_id' => $brand->id, 'title' => $a['title'], 'slug' => $a['slug'], 'description' => $a['description'] ?? null, 'cover_path' => '/images/brand-partners/'.$this->slugOf($brand).'/albums/'.$a['slug'].'.webp',
                'distributor' => $a['distributor'] ?? null, 'release_date' => $a['release'] ?? null, 'published_at' => now(), 'sort_order' => $i + 1,
            ]);
            foreach ((array) ($a['links'] ?? []) as $j => [$platform, $url]) {
                \App\Models\N2N\BrandAlbumLink::query()->create(['album_id' => $album->id, 'platform' => $platform, 'url' => $url, 'sort_order' => $j]);
            }
        }
    }

    private function slugOf(BrandPartner $brand): string
    {
        foreach (self::brands() as $b) {
            if ($b['name'] === $brand->brand_name) {
                return $b['slug'];
            }
        }

        return Str::slug($brand->brand_name);
    }

    /** The "Follow Naara" band at the top of the rewards Hunt. Added only when that URL is missing; the reward amount is the admin's default and editable in Admin > Social Hunt. */
    private function officialFollowHandles(): int
    {
        $added = 0;
        foreach (self::brands()[0]['handles'] as $i => [$platform, $label, $url]) {
            $row = SocialFollowHandle::query()->firstOrCreate(['handle_url' => $url], ['platform' => $platform, 'handle_label' => $label, 'credit_reward' => 5, 'verification' => 'self', 'is_active' => true, 'sort_order' => $i]);
            $added += $row->wasRecentlyCreated ? 1 : 0;
        }

        return $added;
    }

    /** Fill only the footer platforms that are empty, so an admin's own edits are never replaced. @return list<string> */
    private function siteSocialLinks(): array
    {
        $have = SocialLinks::all();
        $seed = ['x' => 'https://x.com/NaaraFamily', 'facebook' => 'https://www.facebook.com/NaaraFamily', 'instagram' => 'https://instagram.com/naara.app', 'tiktok' => 'https://www.tiktok.com/@naara.app', 'youtube' => 'https://www.youtube.com/@NaaraFamily'];
        $add = array_diff_key($seed, array_filter($have));
        if ($add !== []) {
            SocialLinks::save($add + $have);
        }

        return array_keys($add);
    }

    private function addLink(BrandPartner $brand, string $label, string $url, int $i): void
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $parts = PublicSuffix::parse($host);
        BrandLink::query()->firstOrCreate(['brand_partner_id' => $brand->id, 'normalized_url' => $url], [
            'label' => mb_substr($label, 0, 40), 'url' => $url, 'host' => $host, 'registrable_domain' => $parts->registrable ?? $host, 'tld' => $parts->tld ?? Str::afterLast($host, '.'), 'subdomain' => ($parts->subdomain ?? '') ?: null,
            'resemblance_score' => 100, 'status' => BrandLink::APPROVED, 'last_checked_at' => now(), 'sort_order' => $i + 1,
        ]);
    }

    /** @return list<array<string,mixed>> Edit here or, better, in the app once installed: nothing below is re-applied over an existing listing. */
    public static function brands(): array
    {
        return [
            ['slug' => 'naara', 'name' => 'Naara', 'owner_email' => null, 'color' => '#0A6E6E', 'category' => 'Tech & Apps',
                'description' => 'Premium eSIM data and phone numbers for African travellers and global professionals. 190+ countries, instant activation, no roaming surprises. Stay connected, no borders, no swaps.',
                'handles' => [['x', '@NaaraFamily', 'https://x.com/NaaraFamily'], ['facebook', 'NaaraFamily', 'https://www.facebook.com/NaaraFamily'], ['instagram', '@naara.app', 'https://instagram.com/naara.app'], ['tiktok', '@naara.app', 'https://www.tiktok.com/@naara.app'], ['youtube', '@NaaraFamily', 'https://www.youtube.com/@NaaraFamily']],
                'links' => [['Website', 'https://naara.app']]],
            ['slug' => 'supreme-ideas-agency', 'name' => 'Supreme Ideas Agency', 'owner_email' => 'supremeideasofficial@gmail.com', 'color' => '#0D1B2A', 'category' => 'Business & Entrepreneurship',
                'description' => 'Supreme Ideas is the mastermind behind your brand: a creative and digital agency building connected brand, website and marketing systems that turn online attention into revenue. Branding, web design, paid ads, content and video, AI and workflow automation. Onitsha, Nigeria.',
                'handles' => [['facebook', 'SupremeIdeasAgency', 'https://www.facebook.com/SupremeIdeasAgency'], ['x', '@supremeideasx', 'https://x.com/supremeideasx'], ['instagram', '@supreme_ideas_creative_agency', 'https://www.instagram.com/supreme_ideas_creative_agency'],
                    ['tiktok', '@iamfrankcharles', 'https://www.tiktok.com/@iamfrankcharles'], ['linkedin', 'Frank Charles Ebubedike', 'https://www.linkedin.com/in/frank-charles-ebubedike-1b5205376']],
                'links' => [['Website', 'https://supremeideas.agency']],
                'videos' => ['https://youtu.be/oSgk5B2g2CQ', 'https://youtu.be/ejCGF-H_PrM']],
            ['slug' => 'whyte-nation-music', 'name' => 'Whyte Nation Music', 'owner_email' => 'whytenationmusic@gmail.com', 'color' => '#2A0F3F', 'category' => 'Music & Artists',
                'description' => 'Whyte Nation is a music brand led by artist Zayon Whyte, blending Afrobeat and Reggae with message-driven songs. When the world goes silent, we speak through music. Music x Tech.',
                'handles' => [['facebook', 'Whyte Nation Music', 'https://web.facebook.com/profile.php?id=61573555964944'], ['tiktok', '@whytenationmusic', 'https://www.tiktok.com/@whytenationmusic'], ['tiktok', '@iamzayonwhyte', 'https://www.tiktok.com/@iamzayonwhyte'],
                    ['instagram', '@whyte_nation_official', 'https://www.instagram.com/whyte_nation_official/'], ['instagram', '@zayon_whyte', 'https://instagram.com/zayon_whyte'], ['youtube', '@WhyteNationMusic', 'https://www.youtube.com/@WhyteNationMusic'], ['x', '@Whyte_Nations', 'https://x.com/Whyte_Nations']],
                'links' => [['Website', 'https://whytenation.cc']],
                'music' => [['spotify', 'https://open.spotify.com/artist/7HsZny8KduJkth00cj91YZ'], ['apple_music', 'https://music.apple.com/gb/artist/zayon-whyte/1831445550'], ['boomplay', 'https://www.boomplay.com/artists/114796238'], ['youtube', 'https://www.youtube.com/@WhyteNationMusic']],
                'embeds' => ['https://embed.music.apple.com/gb/song/no-suffer-in-silence/1836345113', 'https://open.spotify.com/embed/album/0iAzlrcICl76d7uLvtkV8k', 'https://open.spotify.com/embed/album/1VM8w3GHE70exy4G8z1Gwb',
                    'https://www.boomplay.com/embed/215002137/MUSIC?colType=2&colID=114796238', 'https://www.boomplay.com/embed/116247626/COL', 'https://music.amazon.com/embed/B0FQ7XJWH5/?id=4tIwrkSYSu&marketplaceId=ATVPDKIKX0DER&musicTerritory=US'],
                'albums' => [
                    ['slug' => 'no-suffer-in-silence', 'title' => 'No Suffer in Silence', 'distributor' => 'ditto', 'release' => '2025-08-31', 'links' => [['apple_music', 'https://music.apple.com/gb/album/no-suffer-in-silence/1836345107']]],
                    ['slug' => 'the-smart-lover', 'title' => 'The Smart Lover', 'distributor' => 'ditto', 'links' => [['spotify', 'https://open.spotify.com/album/1VM8w3GHE70exy4G8z1Gwb']]],
                    ['slug' => 'relationship-palava-2', 'title' => 'Relationship Palava 2', 'distributor' => 'ditto', 'links' => [['spotify', 'https://open.spotify.com/album/0iAzlrcICl76d7uLvtkV8k']]],
                ]],
            ['slug' => 'essential-farm', 'name' => 'Essential Farm', 'owner_email' => 'myessentialfarm@gmail.com', 'color' => '#14532D', 'category' => 'Food & Beverage',
                'description' => 'A 2,500-bird egg production farm in Onitsha, Anambra, Nigeria. We do not guess, we systemize: documented poultry management systems, brooding, pullet development, layer production and flock health.',
                'handles' => [['tiktok', '@essential_farm', 'https://www.tiktok.com/@essential_farm'], ['facebook', 'Essential Farm', 'https://web.facebook.com/profile.php?id=61584143221143'], ['linkedin', 'essential-farm', 'https://www.linkedin.com/in/essential-farm/'], ['youtube', '@TheEssentialFarm', 'https://www.youtube.com/@TheEssentialFarm']],
                'links' => [['Website', 'https://essential-farm.com']],
                'videos' => ['https://youtube.com/shorts/aU_Q6VV6xt4', 'https://youtu.be/Hgpm75G961I']],
        ];
    }
}
