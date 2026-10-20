<?php

namespace App\Support\MasterMarketing;

use App\Support\IconOverrides;

/**
 * MASTER-ONLY. The Naara custom menu icon set (More sheet, marketing mega menu, admin menus): every menu item has its OWN stable slot key
 * (menu.app.more.security, menu.marketing.mega.chat, menu.admin.payout-settings), so two items that used to share one generic glyph
 * (Chat and Contact were both "message-circle") now look different.
 *
 * Resolution order, one place: admin slot override (Admin > Brand icons, name = the slot with dots turned into dashes) -> the bundled default
 * for the slot (public/images/master-marketing/menu-icons) -> null, and the caller falls back to its old generic sprite icon.
 * Light and dark are separate files; the app has its own dark toggle, so the page swaps them with the html.dark class, never prefers-color-scheme.
 */
final class MenuIcons
{
    public const BASE = 'images/master-marketing/menu-icons';

    /** Dashboard "More" sheet and bottom bar: BottomNav route key => file slot. */
    public const MORE = [
        'catalogue' => 'esims',
        'numbers' => 'numbers',
        'gift-cards' => 'gifts',
        'wallet' => 'wallet',
        'dashboard' => 'home',
        'numbers.contacts' => 'contacts',
        'numbers.dialer' => 'internet-calls',
        'brain.chat' => 'naarabrain',
        'help.center' => 'help-support',
        'developer' => 'developer-api',
        'merchant.clients' => 'clients',
        'merchant.invoices' => 'invoices',
        'merchant.dashboard' => 'my-storefront',
        'partner.earnings' => 'partner-earnings',
        'rewards' => 'rewards',
        'journey' => 'my-journey',
        'receipts' => 'receipts',
        'brand.get-listed' => 'list-my-brand',
        'referrals' => 'referrals',
        'data-estimator' => 'estimator',
        'profile' => 'profile',
        'account.appearance' => 'appearance',
        'account' => 'account',
        'security' => 'security',
        'merchant.apply' => 'become-merchant',
        'admin.dashboard' => 'admin',
        'download' => 'download-app',
        'guide' => 'guide-policy',
        'account.tours' => 'guided-tours',
        'business' => 'business-suite',
        'business.workspace' => 'business-suite',
        'numbers.forwarding' => 'forwarding',
        'numbers.messages' => 'messages',
        'numbers.port-in' => 'port-in',
        'n2n.identity' => 'naara-id',
    ];

    /** Marketing mega menu: the slot keys that have a bundled icon. */
    public const MARKETING = ['family', 'esim', 'how-it-works', 'gift', 'chat', 'brain', 'handbooks', 'business', 'developers', 'pricing', 'faq', 'about', 'blog', 'contact'];

    /** Admin menus: route name => slot. */
    public const ADMIN = [
        'admin.dashboard' => 'overview',
        'admin.pricing' => 'pricing',
        'admin.staff' => 'staff',
        'admin.errors' => 'errors',
        'admin.security' => 'security',
        'admin.esim' => 'esim-control-center',
        'admin.pricing-architect' => 'pricing-architect',
        'admin.coupons' => 'coupons',
        'admin.announcements' => 'announcements',
        'admin.notices' => 'login-notices',
        'admin.guides' => 'user-guides',
        'admin.respond-setup-guide' => 'user-guides',
        'admin.banners' => 'banners',
        'admin.banner-placements' => 'banner-placements',
        'admin.more-apps' => 'more-apps',
        'admin.esim-hero' => 'esim-hero',
        'admin.numbers-hero' => 'numbers-hero',
        'admin.numbers-cards' => 'numbers-cards',
        'admin.bento-icons' => 'bento-icons',
        'admin.credits' => 'naara-credits',
        'admin.journey-goals' => 'journey-goals',
        'admin.social-hunt' => 'social-hunt',
        'admin.brand-directory' => 'brand-directory',
        'admin.gift-cards' => 'naara-gift',
        'admin.handbooks' => 'handbooks',
        'admin.gift-hero' => 'gift-hero',
        'admin.hunt-hero' => 'hunt-hero',
        'admin.analytics' => 'analytics',
        'admin.payouts' => 'payouts',
        'admin.payout-health' => 'payout-health',
        'admin.payout-settings' => 'payout-settings',
        'admin.global-payout-rail' => 'global-payout-rail',
        'admin.refunds' => 'refunds-disputes',
        'admin.reconciliation' => 'reconciliation',
        'admin.exchange-rates' => 'exchange-rates',
        'admin.tax-rates' => 'tax-rates',
        'admin.gateways' => 'payment-gateways',
        'admin.kyc' => 'identity-kyc',
        'admin.merchants' => 'merchants',
        'admin.gif-library' => 'gif-library',
        'admin.brain' => 'naarabrain',
        'admin.ai-costs' => 'ai-costs',
        'admin.business-suite' => 'business-suite',
        'admin.business-suite.workspace-page' => 'business-workspace-page',
        'admin.business-suite.brain-economics' => 'brain-economics',
        'admin.n2n' => 'n2n',
        'admin.n2n.brands' => 'n2n-brand-profiles',
        'admin.n2n.calls' => 'n2n-voice-calls',
        'admin.n2n.group-calls' => 'n2n-group-calls',
        'admin.n2n.notices' => 'n2n-chat-notices',
        'admin.n2n.chat-controls' => 'n2n-chat-controls',
        'admin.n2n.status' => 'n2n-chat-status',
        'admin.n2n.reports' => 'n2n-chat-reports',
        'admin.merchant-widgets' => 'merchant-widgets',
        'admin.partners' => 'partners',
        'admin.developer-api' => 'developer-api',
        'admin.white-label' => 'white-label',
        'admin.builder' => 'page-builder',
        'admin.copy-studio' => 'copy-studio',
        'admin.nav' => 'floating-nav',
        'admin.bottom-nav' => 'bottom-nav',
        'admin.app-builder' => 'app-builder',
        'admin.installers' => 'app-builder',   // same icon: installers are the other half of App Builder
        'admin.site' => 'marketing-site',
        'admin.product-lines' => 'product-lines',
        'admin.email-studio' => 'email-studio',
        'admin.email-broadcast' => 'email-broadcast',
        'admin.home-media' => 'homepage-media',
        'admin.marketing-videos' => 'marketing-videos',
        'admin.content-lock' => 'security',
        'admin.sidebar-menu' => 'sidebar-menu',
        'admin.pages' => 'custom-pages',
        'admin.faq' => 'faq',
        'admin.trustpilot' => 'trustpilot-reviews',
        'admin.blog' => 'blog',
        'admin.blog-reader' => 'blog-reader',
        'admin.tours' => 'guided-tours',
        'admin.brand-links' => 'business-links',
        'admin.brand-trials' => 'naara-pro-trials',
        'admin.follow-rewards' => 'follow-rewards',
        'admin.who-pays' => 'who-pays',
        'admin.legal' => 'legal',
        'admin.cookies' => 'cookies',
        'admin.incidents' => 'status-incidents',
        'admin.branding' => 'branding',
        'admin.link-previews' => 'link-previews',
        'admin.chrome' => 'auth-footer',
        'admin.appearance' => 'splash',
        'admin.preloader-studio' => 'preloader-studio',
        'admin.notification-tones' => 'sounds-ringtones',
        'admin.theme-toggle-studio' => 'theme-toggle-studio',
        'admin.welcome-settings' => 'welcome-animation',
        'admin.theme' => 'theme',
        'admin.dashboard-theme' => 'dashboard-theme',
        'admin.page-motion' => 'page-transitions',
        'admin.user-appearance' => 'user-appearance',
        'admin.service-icons' => 'service-icons',
        'admin.brand-icons' => 'brand-icons',
        'admin.menu-icons' => 'menu-icons',
        'admin.integrations' => 'integrations',
        'admin.features' => 'features',
        'admin.users' => 'users',
        'admin.support-agent' => 'support-agent',
        'admin.deletions' => 'deletions',
        'admin.legal-hold' => 'legal-hold-records',
        'admin.port-in-requests' => 'port-in-requests',
        'admin.tickets' => 'tickets',
        'admin.nci.registry' => 'provider-registry',
        'admin.provider-logins' => 'provider-logins',
        'admin.nci.health' => 'health-monitor',
        'admin.nci.routing' => 'routing-console',
        'admin.nci.wallets' => 'provider-wallets',
        'admin.system-health' => 'system-health',
        'admin.journey-studio' => 'journey-studio',
        'admin.setup-health' => 'setup-health',
        'admin.api-keys' => 'api-keys',
        'admin.email' => 'email',
        'admin.backups' => 'backups',
        'admin.updater' => 'platform-updater',
        'admin.maintenance' => 'maintenance',
        'admin.ui-kit' => 'ui-kit',
        'admin.account' => 'my-account',
        'admin.my-appearance' => 'my-appearance',
        'dashboard' => 'storefront',
        'download' => 'download-app',
        'admin.white-label.funnel' => 'white-label',
    ];

    /** Short labels for the narrow grid tiles (the full label stays in the list view, the tooltip and the screen reader). */
    public const SHORT = [
        'Appearance' => 'Theme', 'Business Suite' => 'Business', 'NaaraBusiness Suite' => 'Business', 'Internet calls' => 'Calls', 'Help & Support' => 'Support', 'Developer API' => 'Developers',
        'Guided tours' => 'Tours', 'Guide & policy' => 'Guide', 'My Journey' => 'Journey', 'Download the app' => 'Get the app', 'Partner earnings' => 'Earnings',
        'Become a Merchant' => 'Merchant', 'eSIM Control Center' => 'eSIM control', 'More from Naara banners' => 'Naara banners', 'Refunds & Disputes' => 'Refunds', 'Payment Gateways' => 'Gateways', 'Exchange rate (NGN)' => 'Exchange rate', 'Tax / VAT rates' => 'Tax rates', 'Copy Studio (AI)' => 'Copy Studio', 'Business workspace page' => 'Workspace', 'Legal hold records' => 'Legal hold', 'Port-in requests' => 'Port-ins', 'Identity (KYC)' => 'KYC', 'Trustpilot reviews' => 'Trustpilot', 'Provider Registry' => 'Registry', 'Global payout rail' => 'Payout rail', 'Marketing videos' => 'Videos', 'Homepage media' => 'Home media', 'Platform updater' => 'Updater', 'Payout settings' => 'Payout setup', 'My Storefront' => 'Storefront', 'Get listed' => 'List brand', 'List my brand' => 'List brand',
    ];

    /** @param 'app'|'admin' $surface */
    public static function slotForRoute(string $route, string $surface = 'app'): ?string
    {
        if ($surface === 'admin') {
            $key = self::ADMIN[$route] ?? null;

            return $key ? 'menu.admin.'.$key : null;
        }
        $key = self::MORE[$route] ?? null;

        return $key ? 'menu.app.more.'.$key : null;
    }

    /** Floating-nav icon name => the custom icon of the page it stands for (so Home, Plans, Numbers and the assistant all use the new set, not the old line icons). */
    private const NAV_BY_ICON = [
        'globe' => 'menu.app.more.home', 'home' => 'menu.app.more.home', 'wifi' => 'menu.app.more.esims', 'signal' => 'menu.app.more.esims', 'phone' => 'menu.app.more.numbers',
        'message-circle' => 'menu.app.more.naarabrain', 'sparkles' => 'menu.app.more.naarabrain', 'wallet' => 'menu.app.more.wallet', 'gift' => 'menu.app.more.gifts', 'user' => 'menu.app.more.profile',
    ];

    /** Floating-nav page target => custom icon, for targets that are not a mega-menu page. */
    private const NAV_BY_TARGET = ['home' => 'menu.app.more.home', 'catalogue' => 'menu.app.more.esims', 'numbers' => 'menu.app.more.numbers', 'gift-cards' => 'menu.app.more.gifts', 'dashboard' => 'menu.app.more.home', 'wallet' => 'menu.app.more.wallet'];

    /**
     * The custom icon slot for one floating-nav segment, or null to keep its generic icon. A page with its own mega-menu icon uses that when no other segment points at the same
     * page; otherwise the segment's own icon name decides, then its target.
     */
    public static function navSlotKey(string $target, string $icon, bool $uniqueTarget = true): ?string
    {
        if ($uniqueTarget && ($m = self::marketingSlot($target))) {
            return $m;
        }

        return self::NAV_BY_ICON[$icon] ?? self::NAV_BY_TARGET[$target] ?? null;
    }

    /** Marketing mega-menu slot from the nav item's own key (eg esim, chat, contact). */
    public static function marketingSlot(string $key): ?string
    {
        return in_array($key, self::MARKETING, true) ? 'menu.marketing.mega.'.$key : null;
    }

    /** The surface the current page belongs to: the admin panel's menus use the admin set, everything else the customer set. */
    public static function surface(): string
    {
        $name = (string) (request()->route()?->getName() ?? '');

        return str_starts_with($name, 'admin.') ? 'admin' : 'app';
    }

    /**
     * @return array{light:string,dark:string,custom:bool}|null  image URLs, or null to use the old generic icon
     */
    public static function resolve(?string $slot): ?array
    {
        if ($slot === null || ! preg_match('/^menu\.(app\.more|marketing\.mega|admin)\.([a-z0-9-]+)$/', $slot, $m)) {
            return null;
        }
        // An admin override for this exact slot wins (one image used in both modes: the admin chose it).
        $custom = IconOverrides::for(str_replace('.', '-', $slot));
        if ($custom) {
            return ['light' => $custom, 'dark' => $custom, 'custom' => true];
        }
        $dir = ['app.more' => 'more', 'marketing.mega' => 'marketing', 'admin' => 'admin'][$m[1]];
        $file = ['app.more' => 'app-more-', 'marketing.mega' => 'marketing-mega-', 'admin' => 'admin-more-'][$m[1]].$m[2];
        $path = self::BASE.'/'.$dir.'/svg/'.$file;
        if (! is_file(public_path($path.'.svg'))) {
            return null;
        }

        // The default pack serves the bundled files as they are (fastest); any other pack recolours the same files through the icon-pack route.
        $pack = \App\Support\IconPacks\IconPacks::activeSlug();
        if ($pack !== \App\Support\IconPacks\IconPacks::defaultSlug() && \Illuminate\Support\Facades\Route::has('icon-pack.svg')) {
            return ['light' => \App\Support\IconPacks\IconPacks::url($dir, $file, $pack), 'dark' => \App\Support\IconPacks\IconPacks::url($dir, $file.'-dark', $pack), 'custom' => false];
        }

        return ['light' => asset($path.'.svg'), 'dark' => asset($path.'-dark.svg'), 'custom' => false];
    }

    public static function short(string $label): string
    {
        return self::SHORT[$label] ?? $label;
    }
}
