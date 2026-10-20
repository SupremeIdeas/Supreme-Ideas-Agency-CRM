<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,          // must run before DefaultAdminSeeder
            BusinessRoleSeeder::class,  // Business Suite Prompt 13 §2: owner/admin/agent/viewer
            PricingSettingsSeeder::class,
            AdminPathSeeder::class,     // seeds admin_path from config('admin.path') (Prompt 22)
            DefaultAdminSeeder::class,  // seeds the default super_admin
            DefaultBannerSeeder::class, // ships the five brand promo banners
            MarketingCouponsSeeder::class, // welcome + comeback marketing coupons
            EsimCompatibleDeviceSeeder::class, // eSIM device compatibility catalogue
            EsimImageSeeder::class,     // eSIM country/region navigation imagery (BUILD-8)
            NumbersBentoSeeder::class,  // six Numbers landing bento cards
            GiftCardLogoSeeder::class,  // shipped gift-card brand logos (a fresh install already has them)
            BrandPlanSeeder::class,     // brand-directory subscription starter plans (BUILD-9)
            ProviderRegistrySeeder::class, // provider registry metadata + URLs (BUILD-14)
            ProviderExpansionSeeder::class, // new adapters, enabled=false (BUILD-18)
            AppearancePresetSeeder::class,  // per-user skins/accents (admin-governed presets)
            ThemePresetSeeder::class,       // switchable visual skins — built-in naara-official (Theme Batch 1)
            WhiteLabelLicensePlanSeeder::class, // Basic/Medium/Extended/Extended V2 catalog (Prompt 21-EXT §1)
            WhiteLabelGuideLinkSeeder::class,   // merchant guide reference links (admin can swap for affiliate URLs)
            BlogPostSeeder::class,      // 40 launch posts — the /blog index + homepage 'From the Blog' band are empty without them
            FaqSeeder::class,           // dedicated /faq page's 12 topics + Q&A (master-only, like the page itself)
        ]);

        // MASTER-ONLY: extra blog posts and FAQ topics about Naara Chat, Brands, Naara Pro, the Business Suite, Respond, handbooks, White Label and the developer tools.
        foreach ([\Database\Seeders\MasterMarketingBlogSeeder::class, \Database\Seeders\MasterMarketingFaqSeeder::class] as $extra) {
            if (class_exists($extra)) {
                $this->call($extra);
            }
        }

        // MASTER-ONLY: the provider login manager's defaults (dashboard links + email hints). Absent in a white-label fork.
        if (class_exists(\Database\Seeders\ProviderLoginSeeder::class) && class_exists(\App\Support\ProviderLogins::class)) {
            $this->call(\Database\Seeders\ProviderLoginSeeder::class);
        }

        // MASTER-ONLY: the owner's own brand listings (Naara, Supreme Ideas Agency, Whyte Nation Music, Essential Farm) with logos, links,
        // social handles and the Whyte albums, so the Brand directory is never empty on a fresh install. A white-label fork has no such command.
        if (class_exists(\App\Console\Commands\MasterMarketingSeedBrandsCommand::class)) {
            try {
                $this->command?->call('naara:seed-brands');
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
