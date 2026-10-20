{{-- Admin chrome (blueprint Sections 13, 15, 17, 25, 27). Same premium shell as
     the customer app — Apple-inspired side menu on desktop, mobile bottom nav —
     but role-scoped: staff see only what they may use; admin config is
     super_admin/admin; staff management is super_admin only. --}}
@props(['preloaderType' => 'admin'])
@php
    $u = auth()->user();
    $isPrivileged = $u->hasAnyRole(['super_admin', 'admin']);
    $isSuper = $u->hasRole('super_admin');

    $primary = [['route' => 'admin.dashboard', 'label' => 'Overview', 'icon' => 'signal']];
    $more = [];

    // `More` is grouped with ['heading' => ...] separators so complementary
    // tools sit together; the app-shell renders headings in the desktop sidebar
    // and skips them in the mobile grid.
    if ($isPrivileged) {
        // Bottom-bar core differs slightly by role (super gets Staff, admin gets
        // Errors); the rest live in the "More" sheet / lower sidebar.
        $primary[] = ['route' => 'admin.pricing', 'label' => 'Pricing', 'icon' => 'credit-card'];
        $primary[] = $isSuper
            ? ['route' => 'admin.staff', 'label' => 'Staff', 'icon' => 'id-card']
            : ['route' => 'admin.errors', 'label' => 'Errors', 'icon' => 'file-text'];

        // Store & pricing.
        $more[] = ['heading' => 'Store & pricing'];
        $more[] = ['route' => 'admin.esim', 'label' => 'eSIM Control Center', 'icon' => 'signal'];
        $more[] = ['route' => 'admin.pricing-architect', 'label' => 'Price with Claude', 'icon' => 'zap'];
        $more[] = ['route' => 'admin.coupons', 'label' => 'Coupons', 'icon' => 'gift'];
        $more[] = ['route' => 'admin.announcements', 'label' => 'Announcements', 'icon' => 'bell'];
        $more[] = ['route' => 'admin.notices', 'label' => 'Login notices', 'icon' => 'bell'];
        $more[] = ['route' => 'admin.guides', 'label' => 'User guides', 'icon' => 'help-circle'];
        $more[] = ['route' => 'admin.banners', 'label' => 'Banners', 'icon' => 'image'];
        // Distinct from "Banners" above (Module 31 — admin-uploaded promo
        // artwork/coupons): this is the "More from Naara" curated
        // feature-highlight carousel's own placement toggle (Frontend-UX-fix
        // blueprint Phase G).
        $more[] = ['route' => 'admin.banner-placements', 'label' => 'More from Naara banners', 'icon' => 'grid'];
        if (\App\Support\FeatureEntitlements::isMaster()) {
            $more[] = ['route' => 'admin.more-apps', 'label' => 'More Apps', 'icon' => 'grid'];
        }
        $more[] = ['route' => 'admin.esim-hero', 'label' => 'eSIM hero', 'icon' => 'signal'];
        $more[] = ['route' => 'admin.numbers-hero', 'label' => 'Numbers hero', 'icon' => 'phone'];
        $more[] = ['route' => 'admin.numbers-cards', 'label' => 'Numbers cards', 'icon' => 'grid'];
        $more[] = ['route' => 'admin.bento-icons', 'label' => 'Bento icons', 'icon' => 'image'];
        $more[] = ['route' => 'admin.credits', 'label' => 'NaaraCredits', 'icon' => 'gift'];
        $more[] = ['route' => 'admin.journey-goals', 'label' => 'Journey Goals', 'icon' => 'star'];
        $more[] = ['route' => 'admin.social-hunt', 'label' => 'Social Hunt', 'icon' => 'star'];
        $more[] = ['route' => 'admin.brand-directory', 'label' => 'Brand Directory', 'icon' => 'grid'];
        $more[] = ['route' => 'admin.gift-cards', 'label' => 'Naara Gift', 'icon' => 'gift'];
        if (class_exists(\App\Livewire\Admin\Handbooks\HandbookManager::class)) {
            $more[] = ['route' => 'admin.handbooks', 'label' => 'Handbooks', 'icon' => 'book-open'];
        }
        $more[] = ['route' => 'admin.gift-hero', 'label' => 'Gift hero', 'icon' => 'gift'];
        $more[] = ['route' => 'admin.hunt-hero', 'label' => 'Hunt hero', 'icon' => 'star'];

        // Money & partners.
        $more[] = ['heading' => 'Money & partners'];
        $more[] = ['route' => 'admin.analytics', 'label' => 'Analytics', 'icon' => 'signal'];
        $more[] = ['route' => 'admin.payouts', 'label' => 'Payouts', 'icon' => 'credit-card'];
        $more[] = ['route' => 'admin.payout-health', 'label' => 'Payout health', 'icon' => 'signal'];
        if ($isSuper) {
            $more[] = ['route' => 'admin.payout-settings', 'label' => 'Payout settings', 'icon' => 'settings'];
        }
        $more[] = ['route' => 'admin.global-payout-rail', 'label' => 'Global payout rail', 'icon' => 'signal'];
        $more[] = ['route' => 'admin.refunds', 'label' => 'Refunds & Disputes', 'icon' => 'refresh'];
        $more[] = ['route' => 'admin.reconciliation', 'label' => 'Reconciliation', 'icon' => 'wallet'];
        $more[] = ['route' => 'admin.exchange-rates', 'label' => 'Exchange rate (NGN)', 'icon' => 'refresh'];
        $more[] = ['route' => 'admin.tax-rates', 'label' => 'Tax / VAT rates', 'icon' => 'wallet'];
        $more[] = ['route' => 'admin.gateways', 'label' => 'Payment Gateways', 'icon' => 'credit-card'];
        $more[] = ['route' => 'admin.kyc', 'label' => 'Identity (KYC)', 'icon' => 'shield'];
        $more[] = ['route' => 'admin.merchants', 'label' => 'Merchants', 'icon' => 'id-card'];
        $more[] = ['route' => 'admin.gif-library', 'label' => 'GIF library', 'icon' => 'star'];
        if (class_exists(\App\Livewire\Admin\Brain\Settings::class)) {
            $more[] = ['route' => 'admin.brain', 'label' => 'NaaraBrain', 'icon' => 'sparkles'];
        }
        if (class_exists(\App\Livewire\Admin\Brain\AiCosts::class)) {
            $more[] = ['route' => 'admin.ai-costs', 'label' => 'AI costs', 'icon' => 'sparkles'];
        }
        if (class_exists(\App\Livewire\Admin\BusinessSuiteSettingsPage::class)) {
            $more[] = ['route' => 'admin.business-suite', 'label' => 'Business Suite', 'icon' => 'briefcase'];
            $more[] = ['route' => 'admin.business-suite.workspace-page', 'label' => 'Business workspace page', 'icon' => 'image'];
            $more[] = ['route' => 'admin.business-suite.brain-economics', 'label' => 'Brain economics', 'icon' => 'trending-up'];
        }
        if (class_exists(\App\Livewire\Admin\N2N\Settings::class)) {
            $more[] = ['route' => 'admin.n2n', 'label' => 'N2N', 'icon' => 'users'];
            $more[] = ['route' => 'admin.n2n.brands', 'label' => 'Brand profiles', 'icon' => 'star'];
            $more[] = ['route' => 'admin.n2n.calls', 'label' => 'Voice calls', 'icon' => 'phone'];
            $more[] = ['route' => 'admin.n2n.group-calls', 'label' => 'Group calls', 'icon' => 'users'];
            $more[] = ['route' => 'admin.n2n.notices', 'label' => 'Chat notices', 'icon' => 'bell'];
            $more[] = ['route' => 'admin.n2n.chat-controls', 'label' => 'Chat controls', 'icon' => 'settings'];
            $more[] = ['route' => 'admin.n2n.status', 'label' => 'Chat status', 'icon' => 'clock'];
            $more[] = ['route' => 'admin.n2n.reports', 'label' => 'Chat reports', 'icon' => 'shield'];
        }
        if (class_exists(\App\Livewire\Admin\MerchantWidgetControls::class)) {
            $more[] = ['route' => 'admin.merchant-widgets', 'label' => 'Widgets', 'icon' => 'globe'];
        }
        $more[] = ['route' => 'admin.partners', 'label' => 'Partners', 'icon' => 'users'];
        $more[] = ['route' => 'admin.developer-api', 'label' => 'Developer API', 'icon' => 'key'];
        // One entry: the hub bar at the top of every White Label page links Purchases, Payments, Licences, Invoices and Payment setup.
        if (class_exists(\App\Livewire\Admin\WlFunnelPaymentReview::class)) {
            $more[] = ['route' => 'admin.white-label.funnel', 'label' => 'White Label', 'icon' => 'globe'];
        } else {
            $more[] = ['route' => 'admin.white-label', 'label' => 'White Label', 'icon' => 'globe'];
        }

        // Website (public front end + branding).
        $more[] = ['heading' => 'Website'];
        $more[] = ['route' => 'admin.builder', 'label' => 'Page builder', 'icon' => 'grid'];
        $more[] = ['route' => 'admin.copy-studio', 'label' => 'Copy Studio (AI)', 'icon' => 'zap'];
        $more[] = ['route' => 'admin.nav', 'label' => 'Floating nav', 'icon' => 'grid'];
        $more[] = ['route' => 'admin.bottom-nav', 'label' => 'Bottom nav', 'icon' => 'grid'];
        $more[] = ['route' => 'admin.app-builder', 'label' => 'App Builder', 'icon' => 'package'];
        if (class_exists(\App\Livewire\Admin\MasterMarketing\Installers::class)) {
            $more[] = ['route' => 'admin.installers', 'label' => 'Installers', 'icon' => 'download'];
        }
        $more[] = ['route' => 'admin.site', 'label' => 'Marketing site', 'icon' => 'globe'];
        $more[] = ['route' => 'admin.product-lines', 'label' => 'Product lines', 'icon' => 'package'];
        $more[] = ['route' => 'admin.email-studio', 'label' => 'Email Studio', 'icon' => 'mail'];
        $more[] = ['route' => 'admin.email-broadcast', 'label' => 'Email broadcast', 'icon' => 'send'];
        $more[] = ['route' => 'admin.home-media', 'label' => 'Homepage media', 'icon' => 'play'];
        if (class_exists(\App\Livewire\Admin\MasterMarketingVideoSections::class)) {
            $more[] = ['route' => 'admin.marketing-videos', 'label' => 'Marketing videos', 'icon' => 'play'];
        }
        if (class_exists(\App\Livewire\Admin\MasterMarketing\ContentLock::class)) {
            $more[] = ['route' => 'admin.content-lock', 'label' => 'Content protection', 'icon' => 'shield'];
        }
        $more[] = ['route' => 'admin.sidebar-menu', 'label' => 'Sidebar menu', 'icon' => 'menu'];
        $more[] = ['route' => 'admin.pages', 'label' => 'Custom pages', 'icon' => 'file-text'];
        if (\App\Support\FeatureEntitlements::isMaster()) {
            $more[] = ['route' => 'admin.faq', 'label' => 'FAQ', 'icon' => 'help-circle'];
        }
        $more[] = ['route' => 'admin.trustpilot', 'label' => 'Trustpilot reviews', 'icon' => 'star'];
        $more[] = ['route' => 'admin.blog', 'label' => 'Blog', 'icon' => 'file-text'];
        $more[] = ['route' => 'admin.blog-reader', 'label' => 'Blog reader', 'icon' => 'share'];
        $more[] = ['route' => 'admin.tours', 'label' => 'Guided tours', 'icon' => 'compass'];
        if (class_exists(\App\Livewire\Admin\BrandLinks::class)) {
            $more[] = ['route' => 'admin.brand-links', 'label' => 'Business links', 'icon' => 'globe'];
        }
        if (class_exists(\App\Livewire\Admin\BrandTrials::class)) {
            $more[] = ['route' => 'admin.brand-trials', 'label' => 'Naara Pro trials', 'icon' => 'sparkles'];
        }
        if (class_exists(\App\Livewire\Admin\FollowRewards::class)) {
            $more[] = ['route' => 'admin.follow-rewards', 'label' => 'Follow rewards', 'icon' => 'gift'];
        }
        if (class_exists(\App\Livewire\Admin\ConversationRouterSimulator::class)) {
            $more[] = ['route' => 'admin.who-pays', 'label' => 'Who pays?', 'icon' => 'wallet'];
        }
        $more[] = ['route' => 'admin.legal', 'label' => 'Legal', 'icon' => 'shield'];
        $more[] = ['route' => 'admin.cookies', 'label' => 'Cookies', 'icon' => 'shield-check'];
        $more[] = ['route' => 'admin.incidents', 'label' => 'Status incidents', 'icon' => 'bell'];
        $more[] = ['route' => 'admin.branding', 'label' => 'Branding', 'icon' => 'image'];
        $more[] = ['route' => 'admin.link-previews', 'label' => 'Link previews', 'icon' => 'share'];
        $more[] = ['route' => 'admin.chrome', 'label' => 'Auth & footer', 'icon' => 'image'];
        $more[] = ['route' => 'admin.appearance', 'label' => 'Splash', 'icon' => 'zap'];
        $more[] = ['route' => 'admin.preloader-studio', 'label' => 'Preloader Studio', 'icon' => 'refresh'];
        $more[] = ['route' => 'admin.notification-tones', 'label' => 'Sounds & ringtones', 'icon' => 'bell'];
        $more[] = ['route' => 'admin.theme-toggle-studio', 'label' => 'Theme Toggle', 'icon' => 'sun'];
        $more[] = ['route' => 'admin.welcome-settings', 'label' => 'Welcome animation', 'icon' => 'zap'];
        $more[] = ['route' => 'admin.theme', 'label' => 'Theme', 'icon' => 'star'];
        $more[] = ['route' => 'admin.dashboard-theme', 'label' => 'Dashboard theme', 'icon' => 'image'];
        $more[] = ['route' => 'admin.page-motion', 'label' => 'Page transitions', 'icon' => 'bolt'];
        $more[] = ['route' => 'admin.user-appearance', 'label' => 'User appearance', 'icon' => 'sparkles'];
        $more[] = ['route' => 'admin.service-icons', 'label' => 'Service icons', 'icon' => 'grid'];
        $more[] = ['route' => 'admin.brand-icons', 'label' => 'Brand icons', 'icon' => 'grid'];
        if (class_exists(\App\Livewire\Admin\MasterMarketingMenuIcons::class)) {
            $more[] = ['route' => 'admin.menu-icons', 'label' => 'Menu icons', 'icon' => 'grid'];
        }
        $more[] = ['route' => 'admin.integrations', 'label' => 'Integrations', 'icon' => 'link'];
        $more[] = ['route' => 'admin.features', 'label' => 'Features', 'icon' => 'zap'];
    }

    // People & support — ticket-workers (staff scope) and admins.
    $support = [];
    if ($isPrivileged) {
        $support[] = ['route' => 'admin.users', 'label' => 'Users', 'icon' => 'id-card'];
        $support[] = ['route' => 'admin.support-agent', 'label' => 'Support agent', 'icon' => 'message-circle'];
        $support[] = ['route' => 'admin.deletions', 'label' => 'Deletions', 'icon' => 'trash'];
        $support[] = ['route' => 'admin.legal-hold', 'label' => 'Legal hold records', 'icon' => 'lock'];
        $support[] = ['route' => 'admin.port-in-requests', 'label' => 'Port-in requests', 'icon' => 'phone-forwarded'];
    }
    if ($isPrivileged || $u->can('tickets.manage')) {
        $support[] = ['route' => 'admin.tickets', 'label' => 'Tickets', 'icon' => 'message-circle'];
    }
    // Delegated eSIM managers (esim.manage scope) who aren't full admins still
    // reach the Control Center (BUILD-8 §4.7).
    if (! $isPrivileged && $u->can('esim.manage')) {
        $more[] = ['heading' => 'Store & pricing'];
        $more[] = ['route' => 'admin.esim', 'label' => 'eSIM Control Center', 'icon' => 'signal'];
    }
    if ($support) {
        $more[] = ['heading' => 'People & support'];
        $more = array_merge($more, $support);
    }

    // NCI Operations Center (BUILD-17) — admin/super_admin, plus staff with nci.view.
    if ($isPrivileged || $u->can('nci.view')) {
        $more[] = ['heading' => 'Operations (NCI)'];
        $more[] = ['route' => 'admin.nci.registry', 'label' => 'Provider Registry', 'icon' => 'package'];
        if ($isPrivileged && \Illuminate\Support\Facades\Route::has('admin.provider-logins')) {
            $more[] = ['route' => 'admin.provider-logins', 'label' => 'Provider Logins', 'icon' => 'lock'];
        }
        if ($isPrivileged && \Illuminate\Support\Facades\Route::has('admin.respond-setup-guide')) {
            $more[] = ['route' => 'admin.respond-setup-guide', 'label' => 'Respond Setup Guide', 'icon' => 'book-open'];
        }
        $more[] = ['route' => 'admin.nci.health', 'label' => 'Health Monitor', 'icon' => 'signal'];
        $more[] = ['route' => 'admin.nci.routing', 'label' => 'Routing Console', 'icon' => 'grid'];
        $more[] = ['route' => 'admin.nci.wallets', 'label' => 'Wallets', 'icon' => 'wallet'];
    }

    // System — super-admin only.
    if ($isSuper) {
        $more[] = ['heading' => 'System'];
        $more[] = ['route' => 'admin.system-health', 'label' => 'System health', 'icon' => 'signal'];
        if (class_exists(\App\Livewire\JourneyEngine\Studio::class)) {
            $more[] = ['route' => 'admin.journey-studio', 'label' => 'Journey Studio', 'icon' => 'layers'];
        }
        if (class_exists(\App\Livewire\Admin\Bootstrap\SetupHealth::class)) {
            $more[] = ['route' => 'admin.setup-health', 'label' => 'Setup health', 'icon' => 'check'];
        }
        $more[] = ['route' => 'admin.api-keys', 'label' => 'API keys', 'icon' => 'key'];
        $more[] = ['route' => 'admin.email', 'label' => 'Email', 'icon' => 'mail'];
        $more[] = ['route' => 'admin.errors', 'label' => 'Error log', 'icon' => 'file-text'];
        $more[] = ['route' => 'admin.backups', 'label' => 'Backups', 'icon' => 'package'];
        $more[] = ['route' => 'admin.updater', 'label' => 'Platform updater', 'icon' => 'upload'];
        $more[] = ['route' => 'admin.maintenance', 'label' => 'Maintenance', 'icon' => 'refresh'];
        $more[] = ['route' => 'admin.ui-kit', 'label' => 'UI Kit', 'icon' => 'grid'];
    }

    // Security is a first-class admin destination: it takes the 4th bottom-bar slot (never hidden behind a fifth item that phones can't
    // show), and "My account" sits beside it in its own More group.
    $primary[] = ['route' => 'admin.security', 'label' => 'Security', 'icon' => 'shield'];
    $more[] = ['heading' => 'My account'];
    $more[] = ['route' => 'admin.account', 'label' => 'My account', 'icon' => 'id-card'];
    // Everyone in the panel can hop back to the end-user app.
    $more[] = ['heading' => 'Shortcuts'];
    $more[] = ['route' => 'admin.my-appearance', 'label' => 'My appearance', 'icon' => 'sparkles'];
    $more[] = ['route' => 'dashboard', 'label' => 'Storefront', 'icon' => 'globe'];
    // Admin-assignable "Download the app" slot (App Export §1).
    if (\App\Support\AppExport::placementActive('admin_menu')) {
        $more[] = ['route' => 'download', 'label' => \App\Support\AppExport::placementLabel('admin_menu'), 'icon' => 'download'];
    }
@endphp

<x-layouts.app :title="($title ?? 'Admin').' — NaaraSim'" :page-type="$preloaderType" :body-class="\App\Support\PlatformTheme::bodyClass()">
    <x-app-shell :primary="$primary" :more="$more" brand-label="NaaraSim Admin" brand-icon="settings" :brand-route="route('admin.dashboard')">
        {{-- Content/billing/hot-menu blueprint Phase G — the admin "Hot Menu"
             quick-access trigger, same header-action shape as the customer
             notification bell. Two keyed instances so mobile + desktop each
             get their own Livewire id. --}}
        <x-slot:headerActions>
            <livewire:admin.hot-menu :key="'hot-menu-mobile'" />
            <x-lang-switcher />
        </x-slot:headerActions>
        <x-slot:headerActionsDesktop>
            <livewire:admin.hot-menu :key="'hot-menu-desktop'" />
            <x-lang-switcher />
        </x-slot:headerActionsDesktop>

        {{ $slot }}
    </x-app-shell>

    {{-- One modal engine for every API-key help icon (Section 15.1). --}}
    <livewire:admin.api-guide-modal />
</x-layouts.app>
