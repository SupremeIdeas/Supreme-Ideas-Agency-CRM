<?php

/*
 * Appearance (skins x accents x mode), per account. Source of truth for KEYS, LABELS and defaults; the
 * `appearance_presets` table mirrors the admin-editable flags (enabled / default / access). Skin CSS lives in
 * resources/css/nx-skins/*.css and is ported from docs/appearance/wireframes/. See docs/appearance/README.md.
 */
return [
    // What a user with no saved choice gets (and the fallback when a preset is disabled or locked out).
    'default_skin' => 'surface',
    'default_accent' => 'teal',

    // Passport skin (§44): the country shown when a member has no active single-country eSIM and no profile country.
    'default_country' => 'ng',

    // Skins whose CSS has shipped. At seed time only the free skins are switched on (Pro skins stay off until the
    // access layer ships and an admin enables them). A skin listed in `skins` but not here is seeded DISABLED and cannot be enabled
    // from the admin page until its stylesheet exists (so no one can pick a skin that renders as plain Surface).
    'built' => ['surface', 'aurora', 'halo', 'glass', 'calm', 'vivid', 'dotgrid', 'ledger', 'daybreak', 'paper', 'noir', 'clay', 'neo', 'prism', 'receipt', 'wavelength', 'handset', 'harmattan', 'tide', 'pop', 'adire', 'blueprint', 'clarity', 'neon', 'passport', 'golden', 'vault', 'boarding', 'chipset', 'airmail', 'nebula', 'ankara', 'canopy', 'danfo', 'asooke'],

    'groups' => ['Core', 'Soft', 'Textured', 'Material', 'Bold', 'Culture', 'Themed', 'Access', 'Telecom', 'Atmosphere', 'Playful'],

    // key => label, group, blurb, default access (free|pro), min plan tier (Prompt 21 §6), sort
    'skins' => [
        'surface' => ['label' => 'Naara Surface', 'group' => 'Core', 'blurb' => 'Layered depth with one bold action card.', 'access' => 'free', 'min_plan_tier' => null, 'sort' => 1],
        'calm' => ['label' => 'Calm', 'group' => 'Core', 'blurb' => 'Flat and tonal. The quietest option.', 'access' => 'pro', 'min_plan_tier' => 1, 'sort' => 2],
        'vivid' => ['label' => 'Vivid', 'group' => 'Core', 'blurb' => 'Saturated gradient cards and solid accent rows.', 'access' => 'pro', 'min_plan_tier' => 1, 'sort' => 3],
        'ledger' => ['label' => 'Ledger', 'group' => 'Core', 'blurb' => 'Flat statement look: hairlines, left tone bars, dashed rules.', 'access' => 'pro', 'min_plan_tier' => 1, 'sort' => 4],
        'daybreak' => ['label' => 'Daybreak', 'group' => 'Soft', 'blurb' => 'Light-first pastel wash, soft shadows, round tiles.', 'access' => 'pro', 'min_plan_tier' => 1, 'sort' => 5],
        'paper' => ['label' => 'Paper', 'group' => 'Soft', 'blurb' => 'Warm editorial paper, serif headings, stamp pills.', 'access' => 'pro', 'min_plan_tier' => 1, 'sort' => 6],
        'clay' => ['label' => 'Clay', 'group' => 'Soft', 'blurb' => 'Soft inflated 3D cards and pressed inputs.', 'access' => 'free', 'min_plan_tier' => null, 'sort' => 7],
        'dotgrid' => ['label' => 'Dot-grid', 'group' => 'Textured', 'blurb' => 'Two-scale dot lattice fading from each card corner.', 'access' => 'free', 'min_plan_tier' => null, 'sort' => 8],
        'aurora' => ['label' => 'Aurora', 'group' => 'Textured', 'blurb' => 'Glow, orbit rings and a signature motif per card.', 'access' => 'pro', 'min_plan_tier' => 2, 'sort' => 9],
        'glass' => ['label' => 'Glass', 'group' => 'Material', 'blurb' => 'Frosted translucent layers over a soft colour mesh.', 'access' => 'free', 'min_plan_tier' => null, 'sort' => 10],
        'halo' => ['label' => 'Halo', 'group' => 'Material', 'blurb' => 'Glowing gradient borders that slowly orbit each card.', 'access' => 'pro', 'min_plan_tier' => 2, 'sort' => 11],
        'neo' => ['label' => 'Neo', 'group' => 'Bold', 'blurb' => 'Bold outlines and hard offset shadows.', 'access' => 'free', 'min_plan_tier' => null, 'sort' => 12],
        'noir' => ['label' => 'Noir', 'group' => 'Bold', 'blurb' => 'True-black OLED, hairlines, accent only where it counts.', 'access' => 'pro', 'min_plan_tier' => 1, 'sort' => 13],
        'adire' => ['label' => 'Adire', 'group' => 'Culture', 'blurb' => 'Indigo and cream resist-dye patterns, rooted in Nigerian textile.', 'access' => 'pro', 'min_plan_tier' => 3, 'sort' => 14],
        'blueprint' => ['label' => 'Blueprint', 'group' => 'Themed', 'blurb' => 'Technical drawing: grid paper, dashed outlines, corner ticks.', 'access' => 'pro', 'min_plan_tier' => 3, 'sort' => 15],
        'clarity' => ['label' => 'Clarity', 'group' => 'Access', 'blurb' => 'High-contrast, large-print accessibility preset.', 'access' => 'pro', 'min_plan_tier' => 3, 'sort' => 16],
        'neon' => ['label' => 'Neon Lagos', 'group' => 'Themed', 'blurb' => 'Night-city glow: neon edges, skyline, magenta sheet light.', 'access' => 'pro', 'min_plan_tier' => 3, 'sort' => 17],
        'passport' => ['label' => 'Passport', 'group' => 'Themed', 'blurb' => 'Visa-page frames, stamp pills; colour follows your active eSIM country.', 'access' => 'pro', 'min_plan_tier' => 3, 'sort' => 18],
        'golden' => ['label' => 'Golden Hour', 'group' => 'Themed', 'blurb' => 'Warm canvas that follows the time of day: dawn, day, dusk, night.', 'access' => 'pro', 'min_plan_tier' => 3, 'sort' => 19],
        'vault' => ['label' => 'Vault', 'group' => 'Themed', 'blurb' => 'Black and champagne foil with engraved guilloche lines.', 'access' => 'pro', 'min_plan_tier' => 3, 'sort' => 20],
        'boarding' => ['label' => 'Boarding Pass', 'group' => 'Telecom', 'blurb' => 'Ticket cards with side notches, tear lines and a barcode strip.', 'access' => 'pro', 'min_plan_tier' => 2, 'sort' => 21],
        'chipset' => ['label' => 'Chipset', 'group' => 'Telecom', 'blurb' => 'SIM-card corner cut, circuit traces and foil contact outlines.', 'access' => 'pro', 'min_plan_tier' => 2, 'sort' => 22],
        'wavelength' => ['label' => 'Wavelength', 'group' => 'Telecom', 'blurb' => 'Signal ripples spreading from every tile.', 'access' => 'pro', 'min_plan_tier' => 2, 'sort' => 23],
        'airmail' => ['label' => 'Airmail', 'group' => 'Telecom', 'blurb' => 'Striped envelope borders, stamp pills and postmark rings.', 'access' => 'pro', 'min_plan_tier' => 2, 'sort' => 24],
        'handset' => ['label' => 'Handset', 'group' => 'Telecom', 'blurb' => 'Retro LCD: pixel grid, bevelled panels and phosphor green.', 'access' => 'pro', 'min_plan_tier' => 1, 'sort' => 25],
        'prism' => ['label' => 'Prism', 'group' => 'Material', 'blurb' => 'Holographic iridescent borders and a soft light sweep.', 'access' => 'pro', 'min_plan_tier' => 3, 'sort' => 26],
        'receipt' => ['label' => 'Receipt', 'group' => 'Themed', 'blurb' => 'Thermal paper: monospace, torn edges and dashed rules.', 'access' => 'pro', 'min_plan_tier' => 1, 'sort' => 27],
        'nebula' => ['label' => 'Nebula', 'group' => 'Atmosphere', 'blurb' => 'Starfield, galaxy clouds and planet tiles.', 'access' => 'pro', 'min_plan_tier' => 3, 'sort' => 28],
        'harmattan' => ['label' => 'Harmattan', 'group' => 'Culture', 'blurb' => 'Dry-season haze: sand, ochre, a hazy sun and dust specks.', 'access' => 'pro', 'min_plan_tier' => 2, 'sort' => 29],
        'ankara' => ['label' => 'Ankara', 'group' => 'Culture', 'blurb' => 'Bold woven-print band across every card.', 'access' => 'pro', 'min_plan_tier' => 2, 'sort' => 30],
        'danfo' => ['label' => 'Danfo', 'group' => 'Culture', 'blurb' => 'Lagos transit signage: sign frames, bold type, hazard stripe.', 'access' => 'pro', 'min_plan_tier' => 2, 'sort' => 31],
        'asooke' => ['label' => 'Aso-oke', 'group' => 'Culture', 'blurb' => 'Woven textile strip along each card, wine, indigo and ochre.', 'access' => 'pro', 'min_plan_tier' => 2, 'sort' => 32],
        'tide' => ['label' => 'Tide', 'group' => 'Atmosphere', 'blurb' => 'Thin sea-green waves that drift slowly.', 'access' => 'pro', 'min_plan_tier' => 3, 'sort' => 33],
        'canopy' => ['label' => 'Canopy', 'group' => 'Atmosphere', 'blurb' => 'Leaf-shaped cards with soft vein lines in forest greens.', 'access' => 'pro', 'min_plan_tier' => 1, 'sort' => 34],
        'pop' => ['label' => 'Pop', 'group' => 'Playful', 'blurb' => 'Halftone dots, comic burst corners and bold outlines.', 'access' => 'pro', 'min_plan_tier' => 1, 'sort' => 35],
    ],

    // Accent presets (Prompt 20 §16). Dark values; `light` overrides only what differs in light mode.
    'accents' => [
        'teal' => ['label' => 'Naara Teal', 'dark' => ['teal' => '16 181 174', 'cta_a' => '12 130 124', 'cta_b' => '6 128 124', 'teal_ink' => '61 219 208', 'acc_a' => '12 130 126', 'acc_b' => '6 118 128', 'acc_c' => '4 84 99'],
            'light' => ['teal' => '10 155 148', 'cta_a' => '10 130 123', 'cta_b' => '5 118 114', 'teal_ink' => '0 103 98'], 'access' => 'free'],
        'ocean' => ['label' => 'Ocean', 'dark' => ['teal' => '40 150 235', 'cta_a' => '32 119 198', 'cta_b' => '22 96 184', 'teal_ink' => '125 194 255', 'acc_a' => '32 119 192', 'acc_b' => '22 96 176', 'acc_c' => '14 64 128'],
            'light' => ['teal_ink' => '16 94 172', 'cta_a' => '29 118 204', 'cta_b' => '18 88 170'], 'access' => 'pro'],
        'violet' => ['label' => 'Violet', 'dark' => ['teal' => '140 110 240', 'cta_a' => '123 96 213', 'cta_b' => '98 68 200', 'teal_ink' => '190 170 255', 'acc_a' => '123 94 223', 'acc_b' => '92 64 190', 'acc_c' => '58 40 130'],
            'light' => ['teal_ink' => '94 62 192', 'cta_a' => '112 80 214', 'cta_b' => '84 56 178'], 'access' => 'pro'],
        'emerald' => ['label' => 'Emerald', 'dark' => ['teal' => '34 197 94', 'cta_a' => '18 133 68', 'cta_b' => '10 110 54', 'teal_ink' => '110 231 160', 'acc_a' => '21 133 71', 'acc_b' => '14 110 60', 'acc_c' => '8 76 42'],
            'light' => ['teal_ink' => '10 112 55', 'cta_a' => '17 134 67', 'cta_b' => '10 106 52'], 'access' => 'pro'],
        'rose' => ['label' => 'Rose', 'dark' => ['teal' => '240 84 120', 'cta_a' => '203 63 97', 'cta_b' => '182 38 76', 'teal_ink' => '255 150 176', 'acc_a' => '207 60 96', 'acc_b' => '170 36 72', 'acc_c' => '116 24 52'],
            'light' => ['teal_ink' => '176 30 67', 'cta_a' => '206 54 90', 'cta_b' => '166 34 70'], 'access' => 'pro'],
        'graphite' => ['label' => 'Graphite', 'dark' => ['teal' => '130 150 170', 'cta_a' => '99 118 139', 'cta_b' => '66 84 104', 'teal_ink' => '190 206 220', 'acc_a' => '96 116 138', 'acc_b' => '62 80 100', 'acc_c' => '40 54 68'],
            'light' => ['teal_ink' => '50 72 90', 'cta_a' => '84 104 126', 'cta_b' => '56 74 94'], 'access' => 'pro'],
    ],

    // Personalisation dials (Prompt 20 §37). First value is the default. Typefaces are system stacks only (no web fonts).
    'dials' => [
        'round' => ['label' => 'Corner roundness', 'options' => ['def' => 'Default', 'sharp' => 'Sharp', 'round' => 'Round']],
        'dens' => ['label' => 'Density', 'options' => ['comf' => 'Comfortable', 'compact' => 'Compact']],
        'ts' => ['label' => 'Text size', 'options' => ['def' => 'Default', 's' => 'Small', 'l' => 'Large']],
        'depth' => ['label' => 'Card depth', 'options' => ['soft' => 'Soft', 'flat' => 'Flat', 'deep' => 'Deep']],
        'font' => ['label' => 'Typeface', 'options' => ['naara' => 'Naara', 'system' => 'System', 'serif' => 'Serif headings', 'mono' => 'Mono numbers']],
        'motion' => ['label' => 'Motion', 'options' => ['full' => 'Full', 'reduced' => 'Reduced']],
        'menucols' => ['label' => 'Menu columns', 'options' => ['c3' => '3 per row', 'c4' => '4 per row']],
    ],

    'modes' => ['light', 'dark', 'system'],

    // Surfaces that never get a user skin (marketing site, auth pages, emails, PDFs): they always use platform defaults.
    'throttle_per_minute' => 30,

    // Coverage manifest for `php artisan nx:coverage` (Prompt 20 §23). A view listed here is "converted": it must be built from
    // the x-nx.* components and tokens, with no hard-coded colour. Views not yet listed keep the old look and are reported, not hidden.
    'coverage' => [
        'batches' => [
            1 => [
                'label' => 'Numbers bento, Verify/Rent/Line sheets, More menu',
                'views' => [
                    'livewire/get-number', 'partials/numbers-bento', 'partials/numbers-modals',
                    'partials/numbers-modal/verify', 'partials/numbers-modal/verify-foot', 'partials/numbers-modal/rent',
                    'partials/numbers-modal/rent-foot', 'partials/numbers-modal/line', 'partials/numbers-modal/line-foot',
                    'components/more-sheet', 'livewire/checkout', 'livewire/account/appearance',
                ],
            ],
            2 => [
                'label' => 'Home, eSIM catalogue (+ detail), Wallet, Account & privacy',
                'views' => ['livewire/catalogue', 'livewire/dashboard', 'livewire/wallet', 'livewire/withdraw', 'livewire/payout-guide', 'livewire/payout-step-up', 'livewire/account'],
            ],
            3 => [
                'label' => 'Call abroad, Contacts, Messages, Rewards, My Journey, Data estimator, Naara Gift, Notifications',
                'views' => ['livewire/dialer', 'livewire/contacts', 'livewire/messages', 'livewire/rewards', 'livewire/journey', 'livewire/data-estimator', 'livewire/gift-cards', 'livewire/notifications', 'livewire/notification-preferences', 'livewire/receipts', 'livewire/referrals', 'livewire/payout-dashboard', 'livewire/account/sound-settings', 'livewire/account/header-settings'],
            ],
            4 => [
                'label' => 'Clients, Invoices, Developer API',
                'views' => ['livewire/merchant-clients', 'livewire/merchant-invoices', 'livewire/developer-portal'],
            ],
            5 => [
                'label' => 'Admin overview and admin sub-pages',
                'views' => ['livewire/admin/dashboard', 'livewire/admin/users', 'livewire/admin/payouts', 'livewire/admin/support-queue', 'livewire/admin/pricing', 'livewire/admin/merchants', 'livewire/admin/staff', 'livewire/admin/analytics', 'livewire/admin/announcements', 'livewire/admin/gift-cards', 'livewire/admin/coupons', 'livewire/admin/credits', 'livewire/admin/gateways', 'livewire/admin/provider-keys', 'livewire/admin/alerts', 'livewire/admin/system-health', 'livewire/admin/banners', 'livewire/admin/email-settings', 'livewire/admin/maintenance', 'livewire/admin/support-agent', 'livewire/admin/reconciliation', 'livewire/admin/email-broadcast', 'livewire/admin/marketing-copy-studio', 'livewire/admin/home-media', 'livewire/admin/master-marketing-video-sections', 'livewire/admin/journey-goals', 'livewire/admin/posts', 'livewire/admin/blog-reader', 'livewire/admin/tour-studio', 'livewire/guided-tours', 'livewire/admin/brand-links', 'livewire/admin/brand-trials', 'livewire/admin/follow-rewards', 'livewire/admin/cookie-settings', 'livewire/handbooks/store', 'livewire/handbooks/library', 'livewire/admin/handbooks/manager', 'livewire/admin/handbooks/editor', 'livewire/admin/handbooks/manuscript', 'handbooks/reader', 'handbooks/listen', 'livewire/handbooks/buy-panel', 'handbooks/show', 'handbooks/series', 'handbooks/_card', 'cookies/preferences', 'components/cookie-banner', 'livewire/wl-funnel-purchase', 'livewire/wl-funnel-pay', 'livewire/wl-balance-pay', 'livewire/wl-custom-order-request', 'livewire/wl-custom-order-view', 'livewire/admin/wl-custom-orders-admin', 'livewire/admin/wl-funnel-payment-review', 'livewire/admin/wl-invoice-settings-page', 'livewire/admin/wl-funnel-overview', 'wl-invoice/show', 'wl-funnel/setup', 'livewire/admin/n2n/chat-controls', 'livewire/admin/n2n/status-settings', 'livewire/n2n/status-composer', 'livewire/n2n/gesture-settings', 'livewire/admin/conversation-router-simulator', 'business-card/show', 'business-card/unavailable', 'livewire/connect-prompt-popup', 'livewire/brand-links-manager', 'livewire/brand-links-list', 'livewire/admin/site-chrome', 'livewire/admin/security', 'livewire/admin/page-builder', 'livewire/admin/branding', 'livewire/admin/esim-control-center', 'livewire/admin/app-builder', 'livewire/admin/updater', 'livewire/admin/global-payout-rail', 'livewire/admin/payout-health', 'livewire/admin/crypto-whitelist', 'livewire/admin/white-label-payments', 'livewire/admin/platform-theme', 'livewire/admin/preloader-studio', 'livewire/admin/white-label-registry', 'livewire/admin/bootstrap/setup-health', 'livewire/journey-engine/today-stack', 'livewire/journey-engine/alert-settings', 'livewire/journey-engine/studio'],
            ],
            6 => [
                'label' => 'Remaining member pages (queued; registered so no page can ship un-skinned unnoticed)',
                'views' => [
                    'livewire/my-lines', 'livewire/port-in', 'livewire/call-forwarding', 'livewire/profile', 'livewire/security-center', 'livewire/identity-verification',
                    'partials/my-connectivity', 'partials/my-lines-analytics', 'livewire/notification-center', 'livewire/country-picker', 'livewire/service-picker', 'livewire/wizard', 'livewire/send-message', 'livewire/support-chat', 'livewire/alert-popup', 'livewire/journey-launcher',
                    'livewire/guide', 'livewire/send-earnings', 'livewire/partner-earnings', 'livewire/become-merchant', 'livewire/merchant-dashboard',
                    'livewire/merchant-earnings', 'livewire/merchant-widgets', 'livewire/n2n/identity-settings', 'livewire/n2n/usage-credits', 'livewire/n2n/chat', 'livewire/n2n/partials/channel-line', 'livewire/n2n/member-profile', 'livewire/n2n/people', 'livewire/n2n/updates', 'livewire/n2n/calls', 'livewire/n2n/profile-settings', 'livewire/n2n/brand-profile-manage', 'livewire/n2n/partials/album-studio', 'components/music/logo', 'n2n/album', 'livewire/merchant-white-label', 'livewire/brand-hunt', 'livewire/brand-manage', 'livewire/brand-setup-wizard', 'livewire/brand-profile', 'livewire/brand-connections', 'livewire/business-card-share', 'livewire/get-listed',
                ],
            ],
            7 => [
                'label' => 'Public member pages, Help Center (Nia), pricing, status, blog and shared partials (A-Z audit 2026-10-05)',
                'views' => [
                    'livewire/status-page', 'livewire/esim-compatibility', 'livewire/marketing/plans-by-country', 'livewire/pricing-page', 'livewire/help-center', 'livewire/blog',
                    'livewire/merchant-join', 'livewire/post-reactions', 'livewire/partials/pricing-explainer', 'livewire/contact-form', 'livewire/partials/gift-cards/_hero',
                    'livewire/partials/dashboard/_hero', 'livewire/partials/hunt-handle-card', 'livewire/partials/dashboard-home-variant-a', 'livewire/partials/hunt-brand-card',
                    'livewire/partials/esim/head', 'livewire/partials/dashboard-home-variant-b', 'livewire/partials/dashboard/_lines', 'livewire/partials/dashboard/_greeting',
                    'livewire/partials/dashboard/_wallet', 'livewire/partials/dashboard/_showcase', 'livewire/partials/dashboard/_coupon', 'livewire/partials/dashboard/_analytics',
                    'livewire/partials/dashboard/_banners', 'livewire/n2n/planner', 'livewire/catalogue/_feature-strip', 'livewire/catalogue/_popular-destinations',
                    'livewire/catalogue/_region-row', 'livewire/catalogue/_country-row', 'livewire/catalogue/_plan-list', 'livewire/catalogue/_plan-row',
                ],
            ],
            8 => [
                'label' => 'Remaining admin pages A-Z (system, content, studios, NCI, payouts, N2N admin)',
                'views' => [
                    'livewire/admin/pricing-architect', 'livewire/admin/nci/routing-console', 'livewire/admin/nci/provider-detail', 'livewire/admin/backups', 'livewire/admin/partners',
                    'livewire/admin/user-appearance', 'livewire/admin/refunds', 'livewire/admin/payout-settings-page', 'livewire/admin/brand-directory', 'livewire/admin/exchange-rates',
                    'livewire/admin/product-lines', 'livewire/admin/port-in-requests', 'livewire/admin/features', 'livewire/admin/social-hunt', 'livewire/admin/splash',
                    'livewire/admin/site-editor', 'livewire/admin/kyc-review', 'livewire/admin/account', 'livewire/admin/sidebar-menu', 'livewire/admin/incidents',
                    'livewire/admin/account-deletions', 'livewire/admin/numbers-bento', 'livewire/admin/service-icons', 'livewire/admin/bento-icons', 'livewire/admin/brand-icons',
                    'livewire/admin/nci/health-monitor', 'livewire/admin/nci/provider-registry', 'livewire/admin/error-log', 'livewire/admin/legal-hold-records',
                    'livewire/admin/developer-api', 'livewire/admin/integrations', 'livewire/admin/custom-pages', 'livewire/admin/notification-tones', 'livewire/admin/esim-hero',
                    'livewire/admin/banner-placements', 'livewire/admin/more-apps', 'livewire/admin/legal-editor', 'livewire/admin/gift-hero', 'livewire/admin/tax-rates',
                    'livewire/admin/hunt-hero', 'livewire/admin/welcome-settings', 'livewire/admin/payout-rail-guide', 'livewire/admin/nav-slots', 'livewire/admin/recover-password',
                    'livewire/admin/guides', 'livewire/admin/numbers-hero', 'livewire/admin/nav-settings', 'livewire/admin/hot-menu', 'livewire/admin/link-previews',
                    'livewire/admin/faq-manager', 'livewire/admin/email-studio', 'livewire/admin/nci/wallets', 'livewire/admin/partials/app-studio', 'livewire/admin/ui-kit',
                    'livewire/admin/theme-toggle-studio', 'livewire/admin/page-builder/_editor-hero', 'livewire/admin/api-guide-modal', 'livewire/admin/page-builder/_editor-custom_html',
                    'livewire/admin/page-builder/_editor-two_column', 'livewire/admin/page-builder/_editor-storytelling', 'livewire/admin/page-builder/_editor-bento',
                    'livewire/admin/page-builder/_editor-carousel', 'livewire/admin/page-builder/_editor-faq', 'livewire/admin/page-builder/_editor-logo_showcase',
                    'livewire/admin/page-builder/_editor-testimonial', 'livewire/admin/page-builder/_editor-video', 'livewire/admin/page-builder/_editor-quote',
                    'livewire/admin/page-builder/_editor-code', 'livewire/admin/page-motion-settings', 'livewire/admin/n2n/identity-lookup', 'livewire/admin/n2n/group-call-settings', 'livewire/admin/n2n/chat-notices',
                    'livewire/admin/n2n/settings', 'livewire/admin/n2n/reports', 'livewire/admin/n2n/brand-profiles', 'livewire/admin/n2n/call-settings',
                    'livewire/admin/merchant-widget-controls', 'livewire/admin/gif-library',
                ],
            ],
            9 => [
                'label' => 'Business Suite (master only): landing, workspace, call-flow builder, admin switch, Coming-soon sheet',
                'views' => ['livewire/business', 'livewire/omni/respond-inbox', 'livewire/omni/respond-automation', 'livewire/omni/respond-templates', 'livewire/call-flow-builder', 'livewire/admin/business-suite-settings', 'livewire/admin/business-workspace-page', 'livewire/admin/business-brain-economics', 'livewire/admin/business-plan-capabilities', 'livewire/business-recordings', 'livewire/business-channels', 'livewire/business-guides', 'livewire/business-assistant', 'livewire/business-setup-wizard', 'business/nav-item', 'business/plan-required', 'components/business/channel-logo', 'components/omni/webchat-plugin-card', 'coming-soon'],
            ],
        ],
    ],
];
