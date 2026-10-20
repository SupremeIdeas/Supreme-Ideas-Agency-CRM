<?php

use App\Http\Controllers\AccountExportController;
use App\Http\Controllers\Admin\EmailInlineImageController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BusinessEmailVerificationController;
use App\Http\Controllers\WorkspaceSwitchController;
use App\Http\Controllers\CustomPageController;
use App\Http\Controllers\DeveloperDocsController;
use App\Http\Controllers\DownloadAppController;
use App\Http\Controllers\EsimQrController;
use App\Http\Controllers\GiftCardOrderController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PublicInvoiceController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\SupportAttachmentController;
use App\Http\Controllers\SupportVoiceController;
use App\Http\Controllers\VoiceTokenController;
use App\Http\Controllers\Webhooks\AppBuildWebhookController;
use App\Http\Controllers\Webhooks\GetatextWebhookController;
use App\Http\Controllers\Webhooks\GiftCardWebhookController;
use App\Http\Controllers\Webhooks\KycWebhookController;
use App\Http\Controllers\Webhooks\OfferwallPostbackController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use App\Http\Controllers\Webhooks\PayoutWebhookController;
use App\Http\Controllers\Webhooks\SmsDeliveryWebhookController;
use App\Http\Controllers\Webhooks\SmsInboundWebhookController;
use App\Http\Controllers\Webhooks\StripeConnectWebhookController;
use App\Http\Controllers\Webhooks\TwilioDialerWebhookController;
use App\Http\Controllers\Webhooks\TwilioDialStatusWebhookController;
use App\Http\Controllers\Webhooks\WorkspaceMetaWebhookController;
use App\Http\Controllers\Webhooks\TelegramWebhookController;
use App\Http\Controllers\Webhooks\TwilioCallRecordingController;
use App\Http\Controllers\Webhooks\TwilioRecordingWebhookController;
use App\Http\Controllers\Webhooks\TwilioCallFlowController;
use App\Http\Controllers\Webhooks\TwilioVoiceWebhookController;
use App\Http\Controllers\VoicemailAudioController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use App\Livewire\Account;
use App\Livewire\Admin\AccountDeletions;
use App\Livewire\Admin\LegalHoldRecords;
use App\Livewire\Admin\Alerts;
use App\Livewire\Admin\Analytics;
use App\Livewire\Admin\Announcements;
use App\Livewire\Admin\AppBuilder;
use App\Livewire\Admin\Backups;
use App\Livewire\Admin\BannerPlacements;
use App\Livewire\Admin\Banners;
use App\Livewire\Admin\BentoIcons;
use App\Livewire\Admin\BrandDirectory;
use App\Livewire\Admin\Branding;
use App\Livewire\Admin\LinkPreviews;
use App\Livewire\Admin\Coupons;
use App\Livewire\Admin\Credits;
use App\Livewire\Admin\CustomPages;
use App\Livewire\Admin\DeveloperApi;
use App\Livewire\Admin\EmailBroadcast;
use App\Livewire\Admin\EmailSettings;
use App\Livewire\Admin\EmailStudio;
use App\Livewire\Admin\ErrorLogViewer;
use App\Livewire\Admin\EsimControlCenter;
use App\Livewire\Admin\EsimHero;
use App\Livewire\Admin\ExchangeRates;
use App\Livewire\Admin\Features;
use App\Livewire\Admin\Gateways;
use App\Livewire\Admin\GiftHero;
use App\Livewire\Admin\HuntHero;
use App\Livewire\Admin\Guides;
use App\Livewire\Admin\HomeMedia;
use App\Livewire\Admin\Incidents;
use App\Livewire\Admin\Integrations;
use App\Livewire\Admin\JourneyGoals;
use App\Livewire\Admin\KycReview;
use App\Livewire\Admin\LegalEditor;
use App\Livewire\Admin\Maintenance;
use App\Livewire\Admin\Updater;
use App\Livewire\Admin\MarketingCopyStudio;
use App\Livewire\Admin\Merchants;
use App\Livewire\Admin\MoreApps;
use App\Livewire\Admin\NavSettings;
use App\Livewire\Admin\NavSlots;
use App\Livewire\Admin\Nci\HealthMonitor;
use App\Livewire\Admin\Nci\ProviderDetail;
use App\Livewire\Admin\Nci\ProviderRegistry;
use App\Livewire\Admin\Nci\RoutingConsole;
use App\Livewire\Admin\Nci\Wallets;
use App\Livewire\Admin\NumbersBento;
use App\Livewire\Admin\NumbersHero;
use App\Livewire\Admin\PageBuilder;
use App\Livewire\Admin\Partners;
use App\Livewire\Admin\GlobalPayoutRail;
use App\Livewire\Admin\Payouts;
use App\Livewire\Admin\PlatformThemePage;
use App\Livewire\Admin\Posts;
use App\Livewire\Admin\PreloaderStudio;
use App\Livewire\Admin\ThemeToggleStudio;
use App\Livewire\Admin\Pricing;
use App\Livewire\Admin\PricingArchitect;
use App\Livewire\Admin\ProductLines;
use App\Livewire\Admin\ProviderKeys;
use App\Livewire\Admin\Reconciliation;
use App\Livewire\Admin\RecoverPassword;
use App\Livewire\Admin\Refunds;
use App\Livewire\Admin\Security;
use App\Livewire\Admin\BrandIcons;
use App\Livewire\Admin\ServiceIconsPage;
use App\Livewire\Admin\SidebarMenu;
use App\Livewire\Admin\SiteChromePage;
use App\Livewire\Admin\SiteEditor;
use App\Livewire\Admin\SocialHunt;
use App\Livewire\Admin\Splash;
use App\Livewire\Admin\Staff;
use App\Livewire\Admin\SupportAgent;
use App\Livewire\Admin\SupportQueue;
use App\Livewire\Admin\SystemHealth;
use App\Livewire\Admin\TaxRates;
use App\Livewire\Admin\ThemePicker;
use App\Livewire\Admin\UiKit;
use App\Livewire\Admin\Users;
use App\Livewire\Admin\WelcomeSettings;
use App\Livewire\BecomeMerchant;
use App\Livewire\Blog;
use App\Livewire\BrandHunt;
use App\Livewire\BrandProfile;
use App\Livewire\BrandManage;
use App\Livewire\Business;
use App\Livewire\CallFlowBuilder;
use App\Livewire\CallForwarding;
use App\Livewire\Catalogue;
use App\Livewire\Checkout;
use App\Livewire\Contacts;
use App\Livewire\Dashboard;
use App\Livewire\DataEstimator;
use App\Livewire\DeveloperPortal;
use App\Livewire\Dialer;
use App\Livewire\GetListed;
use App\Livewire\GetNumber;
use App\Livewire\GiftCards;
use App\Livewire\Guide;
use App\Livewire\IdentityVerification;
use App\Livewire\Journey;
use App\Livewire\MerchantClients;
use App\Livewire\MerchantDashboard;
use App\Livewire\MerchantEarnings;
use App\Livewire\MerchantInvoices;
use App\Livewire\MerchantJoin;
use App\Livewire\MerchantWhiteLabel;
use App\Livewire\Messages;
use App\Livewire\MyLines;
use App\Livewire\PortIn;
use App\Livewire\Admin\PortInRequests;
use App\Livewire\Notifications;
use App\Livewire\PartnerEarnings;
use App\Livewire\PricingPage;
use App\Livewire\Profile;
use App\Livewire\Receipts;
use App\Livewire\Referrals;
use App\Livewire\Rewards;
use App\Livewire\SecurityCenter;
use App\Livewire\StatusPage;
use App\Livewire\SupportChat;
use App\Livewire\Wallet;
use App\Livewire\WelcomeAurora;
use App\Livewire\PayoutGuide;
use App\Livewire\Withdraw;
use App\Support\AdminPath;
use App\Support\LegalContent;
use App\Support\SiteContent;
use Illuminate\Support\Facades\Route;

// Public marketing site (Module 27) — CMS-driven pages (SiteContent), edited
// from Admin → Pages with no redeploy.
// Language switcher (header chip + marketing nav): guests and members.
Route::post('/locale', LocaleController::class)->middleware('throttle:30,1')->name('locale.switch');

Route::get('/', fn () => view('marketing.home', ['sections' => SiteContent::page('home')]))->name('home');
Route::get('/about', fn () => view('marketing.about', ['sections' => SiteContent::page('about')]))->name('about');
Route::get('/how-it-works', fn () => view('marketing.how-it-works', ['sections' => SiteContent::page('how-it-works')]))->name('how-it-works');
Route::get('/products', fn () => view('marketing.products', ['extra' => SiteContent::page('products')]))->name('products');
// Icon packs: one canonical SVG set recoloured per pack (see App\Support\IconPacks\IconPacks).
Route::get('/icon-pack/{pack}/{group}/{file}.svg', \App\Http\Controllers\IconPackController::class)->where(['pack' => '[a-z0-9-]+', 'group' => '[a-z]+', 'file' => '[a-z0-9-]+'])->name('icon-pack.svg');

// MASTER-ONLY omni webhook (Naara Respond): guarded so a stray copy can never half-register it on a fork.
if (class_exists(\App\Http\Controllers\Omni\WebhookController::class)) {
    Route::match(['get', 'post'], '/webhooks/omni/{channel}/{publicId}', \App\Http\Controllers\Omni\WebhookController::class)->where(['channel' => '[a-z]+', 'publicId' => '[0-9a-f-]{36}'])->middleware('throttle:600,1')->name('omni.webhook');
}
if (class_exists(\App\Http\Controllers\Omni\WebChatController::class)) {
    Route::prefix('omni/webchat/{publicId}')->where(['publicId' => '[0-9a-f-]{36}'])->group(function () {
        Route::options('/{any?}', [\App\Http\Controllers\Omni\WebChatController::class, 'preflight'])->where('any', '.*');
        Route::post('/session', [\App\Http\Controllers\Omni\WebChatController::class, 'session'])->middleware('throttle:30,1')->name('omni.webchat.session');
        Route::post('/messages', [\App\Http\Controllers\Omni\WebChatController::class, 'send'])->middleware('throttle:30,1')->name('omni.webchat.send');
        Route::get('/messages', [\App\Http\Controllers\Omni\WebChatController::class, 'poll'])->middleware('throttle:90,1')->name('omni.webchat.poll');
    });
}

// MASTER-ONLY sales pages (NaaraChat, White Label). Guarded with class_exists so a stray copy can never half-register them on a fork.
if (class_exists(\App\Http\Controllers\MasterMarketing\LandingController::class)) {
    Route::get('/naara-chat', [\App\Http\Controllers\MasterMarketing\LandingController::class, 'chat'])->name('n2n.landing');
    Route::get('/naara-brain', [\App\Http\Controllers\MasterMarketing\LandingController::class, 'brain'])->name('brain.landing');
    Route::get('/naara-gift', [\App\Http\Controllers\MasterMarketing\LandingController::class, 'gift'])->name('gift.landing');
    Route::get('/white-label', [\App\Http\Controllers\MasterMarketing\LandingController::class, 'whiteLabel'])->name('white-label.landing');
// White Label purchase funnel (MASTER-ONLY, class_exists-guarded): the landing plan buttons start an instance, the purchase page is the conversational wizard.
if (class_exists(\App\Http\Controllers\WlFunnelStartController::class)) {
    Route::get('/inv/{token}', [\App\Http\Controllers\WlInvoiceController::class, 'show'])->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:30,1')->name('wl-invoice.show');
    Route::get('/inv/{token}/pdf', [\App\Http\Controllers\WlInvoiceController::class, 'pdfByToken'])->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:20,1')->name('wl-invoice.pdf');
    Route::get('/white-label/start', [\App\Http\Controllers\WlFunnelStartController::class, 'start'])->middleware('throttle:30,1')->name('wl-funnel.start');
}
}
Route::get('/contact', fn () => view('marketing.contact', ['sections' => SiteContent::page('contact')]))->name('contact');

// Help Center hub (Naara Care overhaul) — one route reachable both signed in
// and signed out; only the live chat thread below (/support) requires auth.
Route::get('/help', \App\Livewire\HelpCenter::class)->name('help.center');

// Public Developer API documentation (ROADMAP §Layer 2) — renders the canonical
// docs/DEVELOPER-API.md reference as a browsable, branded page.
if (class_exists(\App\Http\Controllers\MasterMarketing\DeveloperController::class)) {
    // Master only: a marketing landing plus grouped docs. The single-page reference stays reachable at /developers/reference.
    Route::get('/developers', [\App\Http\Controllers\MasterMarketing\DeveloperController::class, 'landing'])->name('developers');
    Route::get('/developers/docs/{slug?}', [\App\Http\Controllers\MasterMarketing\DeveloperController::class, 'docs'])->where('slug', '[a-z0-9-]+')->name('developers.docs');
    Route::get('/developers/reference', DeveloperDocsController::class)->name('developers.reference');
} else {
    Route::get('/developers', DeveloperDocsController::class)->name('developers');
}

// Admin-authored custom-HTML pages (CMS). Prefixed to /p/ so it can never shadow
// a built-in route; only published, non-reserved slugs resolve.
Route::get('/p/{slug}', CustomPageController::class)
    ->where('slug', '[a-z0-9-]+')->name('custom-page');

// Brand-new pages composed entirely from the Section Builder (owner request,
// 2026-09-21) — an admin types any slug into Admin → Page Builder, arranges
// typed sections, publishes, and it's reachable here immediately with no new
// route/view of its own. The 4 "starter" pages (home/about/how-it-works/
// contact) already render Section Builder output through their own existing
// views, so they're excluded here to avoid a confusing second URL for the
// same content.
Route::get('/page/{slug}', function (string $slug) {
    abort_if(in_array($slug, array_keys(\App\Services\Builder\PageBuilderService::STARTER_PAGES), true), 404);
    abort_unless(\App\Support\PageSections::hasLive($slug), 404);

    return view('marketing.built-page', [
        'title' => \App\Support\BrandSettings::name().' — '.ucwords(str_replace('-', ' ', $slug)),
        'sections' => \App\Support\PageSections::live($slug),
    ]);
})->where('slug', '[a-z0-9-]+')->name('built-page');

// Web installer (blueprint Section 22.1). Active only until the lock file
// exists (EnsureNotInstalled).
Route::middleware('installer')->prefix('install')->group(function () {
    Route::get('/', [InstallController::class, 'welcome']);
    Route::get('/requirements', [InstallController::class, 'requirements']);
    Route::get('/setup', [InstallController::class, 'setup']);
    Route::post('/setup', [InstallController::class, 'install'])->name('install.run');
});

// Social login (Module 23). Guarded internally by SocialLogin::googleEnabled().
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->name('social.redirect');
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->name('social.callback');

// Public legal/help pages (blueprint Section 32; Module 30 legal CMS).
Route::get('/legal', fn () => view('legal.index', ['docs' => LegalContent::all()]))->name('legal');
Route::get('/legal/{slug}', function (string $slug) {
    abort_unless(LegalContent::exists($slug), 404);

    return view('legal.show', ['doc' => LegalContent::doc($slug)]);
})->name('legal.show');
// Cookie consent: the full preferences page and the proof-of-consent log endpoint (public, throttled, CSRF-exempt because it only records evidence).
Route::get('/cookie-preferences', [\App\Http\Controllers\CookieConsentController::class, 'preferences'])->name('cookie-preferences');
// Review moments: the "how did it go?" sheet after something really worked (off until an admin switches it on).
Route::middleware(['auth', 'active', 'throttle:30,1'])->prefix('reviews')->name('reviews.')->group(function () {
    Route::get('/next', [\App\Http\Controllers\ReviewMomentController::class, 'next'])->name('next');
    Route::post('/{id}/rate', [\App\Http\Controllers\ReviewMomentController::class, 'rate'])->whereNumber('id')->name('rate');
    Route::post('/{id}/dismiss', [\App\Http\Controllers\ReviewMomentController::class, 'dismiss'])->whereNumber('id')->name('dismiss');
    Route::post('/{id}/opened', [\App\Http\Controllers\ReviewMomentController::class, 'opened'])->whereNumber('id')->name('opened');
});

Route::post('/cookie-consent', [\App\Http\Controllers\CookieConsentController::class, 'store'])->middleware('throttle:30,1')->name('cookie-consent.store');
Route::get('/refund-policy', fn () => view('legal.show', ['doc' => LegalContent::doc('refund')]))->name('refund-policy');
// FAQ — wired the same way as About/How It Works/Contact: a SiteContent-driven
// page (admin-editable hero incl. background image via Admin → Pages → FAQ),
// with its own fuller flat Q&A list ('questions' key) — decoupled from the
// homepage's smaller, topic-grouped FAQ curation (owner request, 2026-09-21).
// Master-only (owner decision, 2026-09-21) — a white-label fork never gets
// this dedicated page, same 404-on-a-fork posture as every other
// master-only route in this codebase.
Route::get('/faq', function () {
    abort_unless(\App\Support\FeatureEntitlements::isMaster(), 404);

    return view('marketing.faq', [
        'sections' => SiteContent::page('faq'),
        'categories' => \App\Support\FaqContent::categories(),
    ]);
})->name('faq');

// Reviews page (Trustpilot display layer). A plain 404 until an admin turns the page on.
Route::get('/reviews', function () {
    abort_unless(\App\Support\Trustpilot\TrustpilotSettings::dedicatedPageOn(), 404);

    return view('marketing.reviews');
})->name('reviews');

// "What's New" — the platform's own public release history (content/billing/
// hot-menu blueprint Phase E). Public like the other content pages above.
Route::get('/whats-new', fn () => view('pages.whats-new', [
    'entries' => \App\Models\ChangelogEntry::latest('released_at')->latest('id')->paginate(15),
]))->name('whats-new');

// Installable app: dynamic PWA manifest + public "Download the App" page.
Route::get('/manifest.webmanifest', ManifestController::class)->name('manifest');
Route::get('/download', DownloadAppController::class)->name('download');
// Verified app links: Android App Links + iOS Universal Links (404 until the fingerprint / Team ID are set in App Builder).
Route::get('/.well-known/assetlinks.json', [\App\Http\Controllers\AppLinksController::class, 'assetLinks'])->name('well-known.assetlinks');
Route::get('/.well-known/apple-app-site-association', [\App\Http\Controllers\AppLinksController::class, 'appleAppSiteAssociation'])->name('well-known.aasa');
if (class_exists(\App\Http\Controllers\MasterMarketing\InstallerController::class)) {
    Route::get('/download/get/{platform}', [\App\Http\Controllers\MasterMarketing\InstallerController::class, 'get'])->where('platform', 'windows|macos|macos_intel|linux|linux_deb|chrome')->middleware('throttle:60,1')->name('download.installer');
}
if (class_exists(\App\Http\Controllers\MasterMarketing\VideoStreamController::class)) {
    // Self-hosted marketing videos (MASTER ONLY): signed, expiring, never an attachment. See App\Support\MasterMarketing\VideoVault.
    Route::get('/mm-video', \App\Http\Controllers\MasterMarketing\VideoStreamController::class)->middleware(['signed', 'throttle:240,1'])->name('mm.video.stream');
}
Route::view('/offline', 'pages.offline')->name('offline');
// First-run onboarding carousel (installed app) → lands on login.
Route::get('/get-started', OnboardingController::class)->name('onboarding');

// Public, unauthenticated system status page (for users + Developer API integrators).
Route::get('/status', StatusPage::class)->name('status');
// Admin-uploaded Lottie preloader, served from our own origin (random-UUID filename, validated at upload).
Route::get('/preloader-asset/{id}.json', \App\Http\Controllers\PreloaderAssetController::class)->where('id', '[0-9a-f\-]{36}')->name('preloader.asset');

// Public blog (Module 30 · Blog overhaul). Index is a Livewire component so the
// SAME hero/carousel/infinite-feed serves marketing + the in-app floating nav.
Route::get('/blog', Blog::class)->name('blog');
Route::get('/blog/{post:slug}', [BlogController::class, 'show'])->name('blog.show');
// Email confirmation link (public: works from another device; the signature is the proof). See EmailConfirmationController.
Route::get('/email/confirm/{id}/{hash}', \App\Http\Controllers\EmailConfirmationController::class)->whereNumber('id')->middleware('throttle:20,1')->name('email.confirm');
Route::post('/blog-reader-event', [\App\Http\Controllers\BlogReaderEventController::class, 'store'])->middleware('throttle:60,1')->name('blog.reader-event');

// Public pricing page (Module 29) — real plans when live, estimate tiers before.
Route::get('/pricing', PricingPage::class)->name('pricing');

// Public, unauthenticated invoice view (Merchant V2 invoicing) — the link a
// merchant forwards to a client, who never has a NaaraSim login.
Route::get('/i/{token}', [PublicInvoiceController::class, 'show'])->name('invoice.public');

// Merchant invite landing (ROADMAP §Layer 3.3) — a reseller's co-branded
// storefront link. Captures the invite and sends the visitor to register.
Route::get('/merchant/{slug}/join', MerchantJoin::class)->name('merchant.join');

// Business Suite Prompt 13 §3.2 — public entry point. The CTA goes through
// the same Fortify register/login routes as any other visitor.
Route::get('/business', \App\Http\Controllers\BusinessSuiteLandingController::class)->middleware('business.suite')->name('business');

// Prompt 13 §3.3 — business_email verification click. `signed` rejects a
// tampered/expired link before the controller ever runs.
// Stored inbox files, fetched by a channel provider or a teammate through a short-lived signed link (never a permanent public URL).
Route::get('/business/inbox/media/{path}', \App\Http\Controllers\Business\InboxMediaController::class)
    ->middleware(['business.suite', 'signed'])->where('path', '[A-Za-z0-9_\-]+')->name('business.inbox.media');

Route::get('/business/verify/{walletGroup}', BusinessEmailVerificationController::class)
    ->middleware(['business.suite', 'signed'])
    ->name('business.email.verify');

// Authenticated customer app (blueprint Sections 4, 12, 14, 16). `active`
// confines a self-paused account to the account page until it reactivates
// (Section 26.1).
Route::middleware(['auth', 'active'])->group(function () {
    // Money + core app routes require a verified email — but only once outgoing
    // mail is configured (owner request), so users are never trapped behind a
    // verification link that can't be sent yet.
    Route::middleware('verified.mail')->group(function () {
        Route::get('/dashboard', Dashboard::class)->name('dashboard');
        Route::get('/catalogue', Catalogue::class)->name('catalogue');
        // Naara Gift storefront (feature-gated: 404 until naara_gift is live).
        Route::get('/gift-cards', GiftCards::class)->name('gift-cards');
        Route::get('/gift-cards/orders', [GiftCardOrderController::class, 'index'])->name('gift-cards.orders');
        Route::get('/gift-cards/orders/{order}', [GiftCardOrderController::class, 'show'])->name('gift-cards.order');
        Route::post('/gift-cards/orders/{order}/balance', [GiftCardOrderController::class, 'balance'])->name('gift-cards.order.balance');
        Route::get('/checkout/{plan}', Checkout::class)->name('checkout');
        Route::get('/wallet', Wallet::class)->name('wallet');
        // Business Suite Prompt 13 §3/§4/§5 — workspace management + the
        // session-scoped switcher. Never re-authenticates (§4.2).
        Route::get('/business/workspace', Business::class)->middleware(['business.suite', 'business.plan:workspace'])->name('business.workspace');
        // Prompt 14 §5 — IVR / ring-group / business-hours builder (org owner/admin).
        Route::get('/business/call-flows', CallFlowBuilder::class)->middleware(['business.suite', 'feature.live:business_call_flows', 'business.plan:call_flows'])->name('business.call-flows');
        // Prompt 15 — call recordings (owner/admin only); audio streams from the private disk, audited.
        Route::get('/business/recordings', \App\Livewire\BusinessRecordings::class)->middleware(['business.suite', 'feature.live:business_recording', 'business.plan:recording'])->name('business.recordings');
        Route::get('/business/recordings/{recording}/audio', \App\Http\Controllers\RecordingAudioController::class)->middleware('business.suite')->whereNumber('recording')->name('business.recordings.audio');
        // Prompt 16 — connect a workspace's own WhatsApp / Telegram / Instagram to the shared inbox (owner/admin only).
        // Guide directory for Naara Pro business workspaces (master only; behind the Business Suite switch like the rest).
        Route::get('/business/guides/{slug?}', \App\Livewire\BusinessGuides::class)->middleware('business.suite')->where('slug', '[a-z0-9\-]+')->name('business.guides');
        if (class_exists(\App\Livewire\Omni\RespondInbox::class)) {
            Route::get('/business/respond', \App\Livewire\Omni\RespondInbox::class)->middleware(['business.suite', 'feature.live:omni_respond', 'business.plan:inbox'])->name('business.respond');
        }
        if (class_exists(\App\Livewire\Omni\RespondAutomation::class)) {
            Route::get('/business/respond/automation', \App\Livewire\Omni\RespondAutomation::class)->middleware(['business.suite', 'feature.live:omni_respond', 'business.plan:inbox'])->name('business.respond.automation');
            Route::get('/business/respond/templates', \App\Livewire\Omni\RespondTemplates::class)->middleware(['business.suite', 'feature.live:omni_respond', 'business.plan:inbox'])->name('business.respond.templates');
        }
        if (class_exists(\App\Http\Controllers\Business\Softphone\NativeSoftphoneController::class)) {
            Route::post('/softphone/native/pair', [\App\Http\Controllers\Business\Softphone\NativeSoftphoneController::class, 'pair'])->middleware(['business.suite'])->name('softphone.pair');
        }
        if (class_exists(\App\Http\Controllers\Omni\WebChatPluginController::class)) {
            Route::get('/business/channels/{connection}/wordpress-plugin', \App\Http\Controllers\Omni\WebChatPluginController::class)->middleware(['business.suite', 'feature.live:omni_respond', 'throttle:10,1'])->whereNumber('connection')->name('omni.webchat.plugin');
        }
        Route::get('/business/channels', \App\Livewire\BusinessChannels::class)->middleware(['business.suite', 'feature.live:business_inbox', 'business.plan:inbox'])->name('business.channels');
        // Shared inbox composer: the Naara composer's HTTP path (files, voice, GIFs, location/contact/poll, scheduled sends). Guarded again inside by InboxAccess.
        Route::post('/business/inbox/send', \App\Http\Controllers\Business\InboxSendController::class)->middleware(['business.suite', 'feature.live:business_inbox', 'business.plan:inbox', 'throttle:120,1'])->name('business.inbox.send');
        // Prompt 17 — the AI voice assistant's settings and call history (owner/admin only).
        Route::get('/business/assistant', \App\Livewire\BusinessAssistant::class)->middleware(['business.suite', 'feature.live:business_ai_voice', 'business.plan:ai_voice'])->name('business.assistant');
        Route::post('/workspace/switch/{walletGroup?}', WorkspaceSwitchController::class)->middleware('business.suite')->name('workspace.switch');
        // The composer's GIF and sticker library, plus live search through the admin's own GIPHY / Tenor key.
        Route::get('/gifs/library', \App\Http\Controllers\GifLibraryController::class)->middleware('throttle:60,1')->name('gifs.library');
        Route::get('/gifs/search', \App\Http\Controllers\GifSearchController::class)->middleware('throttle:30,1')->name('gifs.search');
        Route::get('/numbers', GetNumber::class)->name('numbers');
        // My Lines — the dedicated management hub for owned eSIMs + numbers
        // (active, grouped by Model, with an Archive). Numbers section home.
        Route::get('/numbers/lines', MyLines::class)->name('numbers.lines');
        // Call forwarding for permanent numbers (Live Voice — Part A). The
        // component 404s unless Twilio is Active (voice rides the same keys).
        Route::get('/numbers/forwarding', CallForwarding::class)->name('numbers.forwarding');
        // In-browser international dialer (Live Voice — Part B). Also 404s until
        // Twilio is Active. The token endpoint mints the short-lived WebRTC token.
        Route::get('/numbers/dialer', Dialer::class)->name('numbers.dialer');
        Route::post('/voice/token', VoiceTokenController::class)->name('voice.token');
        // In-app contact book (Live Voice — Part C). Not provider-billed, so no
        // feature gate — standard auth-scoped CRUD that feeds the dialer.
        Route::get('/numbers/contacts', Contacts::class)->name('numbers.contacts');
        // Conversation inbox (Numbers overhaul §1) — inbound + outbound threads.
        Route::get('/numbers/messages', Messages::class)->name('numbers.messages');
        // Voicemail audio (Prompt 11) — streamed from the private disk, owner-only.
        Route::get('/numbers/voicemail/{message}', VoicemailAudioController::class)->name('numbers.voicemail-audio');
        // Port-in intake (Prompt 11) — bring an existing US/Canada number to Naara.
        // Honest multi-day carrier process, not instant provisioning.
        Route::get('/numbers/port-in', PortIn::class)->name('numbers.port-in');
        Route::get('/referrals', Referrals::class)->name('referrals');

        // NaaraCredits rewards area (loyalty module) — opt-in earning.
        Route::get('/receipts', Receipts::class)->name('receipts');
        Route::get('/rewards', Rewards::class)->name('rewards');
        // My Journey — loyalty milestones + travel/eSIM history, two tabs.
        Route::get('/journey', Journey::class)->name('journey');
        // Brand Partner Hunt (BUILD-6 §C) — follow-to-earn NaaraCredits.
        Route::get('/rewards/hunt', BrandHunt::class)->name('rewards.hunt');
        // Brand Profile (owner request, 2026-09-22) — the dedicated per-brand
        // page reached from a Hunt card: full gallery, every handle's own
        // "last post" teaser, same follow-to-earn claim.
        Route::get('/rewards/hunt/{brandPartner}', BrandProfile::class)->name('rewards.hunt.profile');
        // Brand Directory self-service (BUILD-9) — get listed + manage a listing.
        Route::get('/brand/get-listed', GetListed::class)->name('brand.get-listed');
        Route::get('/brand/manage', BrandManage::class)->name('brand.manage');

        // Partner profit-share earnings (dollars only; 404 for non-partners).
        Route::get('/partner', PartnerEarnings::class)->name('partner.earnings');

        // Cash out withdrawable (first-referral) credits (ROADMAP §Layer 1).
        // Browsing + payout-account setup are free (NAARA-BUILD-22 §3); KYC-L2
        // is only enforced once the unified free-payout threshold is spent
        // (WithdrawalService::request(), same as every other earner type).
        Route::get('/rewards/withdraw', Withdraw::class)->name('rewards.withdraw');
        // Rail Guide (Addendum B): which payout rail should I use? (Not the /faq page.)
        Route::get('/account/payout-guide', PayoutGuide::class)->name('payout-guide');
        // Notification sounds + ringtones: a member's own picks (Batch 2), and the same-origin tone stream the browser plays from.
        Route::get('/account/sounds', \App\Livewire\Account\SoundSettings::class)->name('account.sounds');
        Route::get('/account/header', \App\Livewire\Account\HeaderSettings::class)->name('account.header');
        Route::get('/sounds/tone/{tone}', \App\Http\Controllers\ToneController::class)->middleware('throttle:240,1')->name('sounds.tone');
        // Per-account appearance (skins x accent x mode x dials). Only YOUR dashboard changes (Prompt 20 §18).
        Route::get('/account/appearance', \App\Livewire\Account\Appearance::class)->name('account.appearance');
        // Widget Dock (MASTER-ONLY, class_exists-guarded): save or reset where the member parked their floating widgets.
        if (class_exists(\App\Http\Controllers\MasterMarketing\DockController::class)) {
            Route::post('/widget-dock', [\App\Http\Controllers\MasterMarketing\DockController::class, 'store'])->middleware('throttle:60,1')->name('widget-dock.store');
            Route::delete('/widget-dock', [\App\Http\Controllers\MasterMarketing\DockController::class, 'reset'])->middleware('throttle:30,1')->name('widget-dock.reset');
        }
        Route::post('/account/appearance/mode', \App\Http\Controllers\Account\AppearanceModeController::class)->middleware('throttle:60,1')->name('account.appearance.mode');
        // Member-to-member earnings transfer for people whose country has no payout rail yet.
        Route::get('/account/send-earnings', \App\Livewire\SendEarnings::class)->name('send-earnings');

        // Data estimator (blueprint Section 32).
        Route::get('/data-estimator', DataEstimator::class)->name('data-estimator');

        // Developer portal (ROADMAP §Layer 2) — only when the Developer API is
        // enabled (the api.enabled middleware 404s otherwise, hiding the program).
        Route::get('/developer', DeveloperPortal::class)
            ->middleware('api.enabled')->name('developer');

        // Become a merchant (ROADMAP §Layer 3.1) — KYB + application flow. The
        // page self-gates on the programme flag + KYC L3.
        Route::get('/merchant/apply', BecomeMerchant::class)->name('merchant.apply');
        // Merchant storefront dashboard (ROADMAP §Layer 3.5) — active merchants
        // only (404 otherwise): storefront, invite link, customers, earnings, payouts.
        Route::get('/merchant', MerchantDashboard::class)->name('merchant.dashboard');
        // Merchant earnings analytics (BUILD-7 §3) — reporting over MerchantEarning.
        Route::get('/merchant/earnings', MerchantEarnings::class)->name('merchant.earnings');
        // Naara Widgets (MASTER-ONLY): registered only where the module exists, so no fork can half-register it.
        if (class_exists(\App\Livewire\MerchantWidgets::class)) {
            Route::get('/merchant/widgets', \App\Livewire\MerchantWidgets::class)->name('merchant.widgets');
            Route::get('/merchant/widgets/plugin', \App\Http\Controllers\MerchantWidget\PluginDownloadController::class)->middleware('throttle:10,1')->name('merchant.widgets.plugin');
        }
        // Merchant V2 — client management (404s for a non-V2 merchant).
        Route::get('/merchant/clients', MerchantClients::class)->name('merchant.clients');
        // Merchant V2 — invoice dashboard (404s for a non-V2 merchant).
        Route::get('/merchant/invoices', MerchantInvoices::class)->name('merchant.invoices');
        // Prompt 21-EXT — self-service white-label license (visible-but-locked
        // for a non-V2 merchant, same pattern as the Merchant-V2 gate itself).
        Route::get('/merchant/white-label', MerchantWhiteLabel::class)->name('merchant.white-label');
        if (class_exists(\App\Livewire\WlFunnelPurchase::class)) {
            Route::get('/account/white-label-purchase', \App\Livewire\WlFunnelPurchase::class)->name('wl-funnel.purchase');
            Route::get('/account/white-label-setup', [\App\Http\Controllers\WlFunnelSetupController::class, 'show'])->name('wl-funnel.setup');
            Route::post('/account/white-label-setup', [\App\Http\Controllers\WlFunnelSetupController::class, 'store'])->middleware('throttle:10,1')->name('wl-funnel.setup.store');
            Route::get('/account/white-label-pay', \App\Livewire\WlFunnelPay::class)->name('wl-funnel.pay');
            Route::get('/account/white-label-pay/proforma', [\App\Http\Controllers\WlInvoiceController::class, 'proforma'])->middleware('throttle:20,1')->name('wl-funnel.pay.proforma');
            Route::get('/account/white-label-balance', \App\Livewire\WlBalancePay::class)->name('wl-funnel.balance');
            if (class_exists(\App\Livewire\WlCustomOrderRequest::class)) {   // master-only
                Route::get('/account/white-label-custom', \App\Livewire\WlCustomOrderRequest::class)->name('wl-custom-orders');
                Route::get('/account/white-label-custom/{reference}', \App\Livewire\WlCustomOrderView::class)->where('reference', 'WLC-[A-Z0-9]{6}')->name('wl-custom-orders.show');
            }
            Route::get('/account/white-label-document/{id}/pdf', [\App\Http\Controllers\WlInvoiceController::class, 'pdfOwn'])->whereNumber('id')->middleware('throttle:30,1')->name('wl-invoice.own');
        }

        // eSIM activation QR (SVG), generated from the LPA string. Owner- or
        // assigning-merchant-scoped inside the controller.
        Route::get('/esim/{order}/qr', EsimQrController::class)->name('esim.qr');
    });

    // Aurora Welcome entrance (first-login animation) — reachable while
    // unverified so it plays immediately after signup, then hands off to the
    // dashboard. Guards itself: no `just_registered` flag → straight to home.
    Route::get('/welcome', WelcomeAurora::class)->name('welcome');

    // In-app user guide + agreement (per-audience: user / merchant / V2 / developer).
    Route::get('/guide', Guide::class)->name('guide');

    // Account & data rights (blueprint Section 26) — reachable while unverified
    // so a user can still manage or delete their account and resend the email.
    Route::get('/account', Account::class)->name('account');
    // Extended self-service profile (owner request).
    Route::get('/account/profile', Profile::class)->name('profile');
    // Staff compensation earnings — the shared payout dashboard (BUILD-23 §4),
    // staff-only. Staff are KYC-exempt for their profit-share withdrawals.
    Route::get('/staff/earnings', fn () => view('staff.earnings'))
        ->middleware('role:staff|super_admin')->name('staff.earnings');
    // Security Center (Module 23) — also reachable unverified (to change email).
    Route::get('/account/security', SecurityCenter::class)->name('security');
    // Journey Engine J2: opt-in switches for off-app protective alerts.
    Route::get('/account/alerts', \App\Livewire\JourneyEngine\AlertSettings::class)->name('account.alerts');
    // Identity verification (ROADMAP §Layer 0.3) — KYC L2 gate for withdrawals.
    Route::get('/account/verify', IdentityVerification::class)->name('account.verify');
    // In-app notification centre (owner request) — the bell's "see all" page.
    Route::get('/notifications', Notifications::class)->name('notifications');
    // Guided tours: JSON for the browser engine + the replay hub (Account > Guided tours).
    Route::prefix('tours')->middleware('throttle:120,1')->group(function () {
        Route::get('auto', [\App\Http\Controllers\TourController::class, 'auto'])->name('tours.auto');
        Route::get('{key}', [\App\Http\Controllers\TourController::class, 'show'])->where('key', '[a-z0-9._-]+')->name('tours.show');
        Route::post('{key}/progress', [\App\Http\Controllers\TourController::class, 'progress'])->where('key', '[a-z0-9._-]+')->name('tours.progress');
    });
    Route::get('/account/tours', \App\Livewire\GuidedTours::class)->name('account.tours');
    Route::get('/notifications/settings', \App\Livewire\NotificationPreferences::class)->name('notifications.settings');
    // Self-hosted web-push subscribe/unsubscribe (owner request).
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
    // NaaraCare AI support chat (Module 24) — reachable unverified (they may need help).
    Route::get('/support', SupportChat::class)->name('support');
    // Private support voice clips (Module 25) — owner or ticket staff only.
    Route::get('/support/voice/{message}', SupportVoiceController::class)->name('support.voice');
    // Private support evidence attachments — owner or ticket staff only.
    Route::get('/support/attachment/{message}', SupportAttachmentController::class)->name('support.attachment');
    Route::get('/account/export', AccountExportController::class)
        ->name('account.export.download');
});


// Provider webhooks (CSRF-exempt — see bootstrap/app.php). Getatext OTP
// delivery (blueprint Section 8.2).
// Inbound SMS conversations (Numbers overhaul §1) — verify-before-trust, queued.
Route::post('/webhooks/sms-inbound/{provider}', SmsInboundWebhookController::class)
    ->name('webhooks.sms-inbound');

// Delivery reports: Twilio (X-Twilio-Signature) and Telnyx (Ed25519) are signature-verified; Africa's Talking is token-guarded because it does not sign.
Route::post('/webhooks/sms-delivery/{provider}', SmsDeliveryWebhookController::class)
    ->name('webhooks.sms-delivery');

Route::post('/webhooks/getatext', GetatextWebhookController::class)
    ->name('webhooks.getatext');

// Twilio inbound-call webhook (Live Voice — Part A): signature-verified, returns
// TwiML that forwards the call to the user's configured target.
Route::post('/webhooks/twilio/voice', TwilioVoiceWebhookController::class)
    ->name('webhooks.twilio.voice');

// Business Phone Core (Prompt 14): signature-verified continuation callbacks
// for an org call flow — node entry/redirect, menu digits, queue dial result.
Route::post('/webhooks/twilio/call-flow/{callFlow}/{node}', [TwilioCallFlowController::class, 'node'])
    ->middleware('business.suite')->whereNumber(['callFlow', 'node'])->name('webhooks.twilio.call-flow');
Route::post('/webhooks/twilio/call-flow/{callFlow}/{node}/digits', [TwilioCallFlowController::class, 'digits'])
    ->middleware('business.suite')->whereNumber(['callFlow', 'node'])->name('webhooks.twilio.call-flow.digits');
Route::post('/webhooks/twilio/ai-agent/{callFlow}/{node}', [TwilioCallFlowController::class, 'aiAgent'])
    ->middleware('business.suite')->whereNumber(['callFlow', 'node'])->name('webhooks.twilio.ai-agent.continue');
Route::post('/webhooks/twilio/call-flow/{callFlow}/{node}/dial', [TwilioCallFlowController::class, 'dial'])
    ->middleware('business.suite')->whereNumber(['callFlow', 'node'])->name('webhooks.twilio.call-flow.dial');

// Shared inbox channels (Prompt 16, master only): a workspace's own Telegram bot, WhatsApp number and Instagram account (each with its own secret). All verify BEFORE reading the payload.
Route::post('/webhooks/telegram/{connection}', TelegramWebhookController::class)->whereNumber('connection')->name('webhooks.telegram');
Route::match(['get', 'post'], '/webhooks/meta/{connection}', WorkspaceMetaWebhookController::class)->whereNumber('connection')->name('webhooks.meta');

// Business call recording (Prompt 15, master only): the recording-ready callback and the notice played to whoever answers. Both signature-verified.
Route::post('/webhooks/twilio/call-recording/{callFlow}/{node}', [TwilioCallRecordingController::class, 'status'])
    ->middleware('business.suite')->whereNumber(['callFlow', 'node'])->name('webhooks.twilio.call-recording.status');
Route::post('/webhooks/twilio/call-flow/whisper', [TwilioCallRecordingController::class, 'whisper'])
    ->middleware('business.suite')->name('webhooks.twilio.call-flow.whisper');

// Voicemail recording webhook (Prompt 11): signature-verified, fires when a
// <Record> started by the voice webhook above finishes.
Route::post('/webhooks/twilio/recording', TwilioRecordingWebhookController::class)
    ->name('webhooks.twilio.recording');

// Telnyx (TeXML) voice: the same controllers, told which driver to use by the route default. Ed25519-verified.
Route::post('/webhooks/telnyx/voice', TwilioVoiceWebhookController::class)
    ->defaults('voice_provider', 'telnyx')
    ->name('webhooks.telnyx.voice');
Route::post('/webhooks/telnyx/recording', TwilioRecordingWebhookController::class)
    ->defaults('voice_provider', 'telnyx')
    ->name('webhooks.telnyx.recording');

// Twilio in-browser dialer webhooks (Live Voice — Part B): signature-verified.
// `dial` returns the outbound <Dial> TwiML (with a funded timeLimit) for a
// pre-authorised call; `dial-status` settles the wallet on hang-up.
Route::post('/webhooks/twilio/dial', TwilioDialerWebhookController::class)
    ->name('webhooks.twilio.dial');
Route::post('/webhooks/twilio/dial-status', TwilioDialStatusWebhookController::class)
    ->name('webhooks.twilio.dial-status');

// Payment gateway webhooks (blueprint Section 19.3): signature-verified,
// idempotent wallet credit.
Route::post('/webhooks/payments/{gateway}', PaymentWebhookController::class)
    ->name('webhooks.payments');

// Payout (transfer) webhooks (ROADMAP §Layer 0.2): signature-verified,
// idempotent payout-request settlement.
Route::post('/webhooks/payouts/{provider}', PayoutWebhookController::class)
    ->name('webhooks.payouts');

// "This wasn't me" (Addendum D-3.5): signed link in the payout notice emails. Freezes payouts, no login needed.
Route::get('/payouts/not-me/{user}', \App\Http\Controllers\Payouts\NotMeController::class)
    ->middleware(['signed', 'throttle:10,1'])
    ->name('payouts.not-me');

// Stripe Connect account webhooks (ROADMAP §Layer 0.2 — Stripe payout rail):
// a separate endpoint/secret from the above, since it carries account.updated
// onboarding-status events rather than payout-request events.
Route::post('/webhooks/stripe-connect/account', StripeConnectWebhookController::class)
    ->name('webhooks.stripe-connect.account');

// KYC result callbacks (ROADMAP §Layer 0.3): signature-verified, idempotent
// verification decisions.
Route::post('/webhooks/kyc/{provider}', KycWebhookController::class)
    ->name('webhooks.kyc');

// Rewarded-ad / offerwall postback (loyalty module): HMAC-verified,
// idempotent credit grant. Both verbs — networks vary.
Route::match(['get', 'post'], '/webhooks/offerwall', OfferwallPostbackController::class)
    ->name('webhooks.offerwall');

// Native app build status callback (App Export §1): HMAC-verified, flips a
// build queued→building→ready/failed and attaches the artifact + logs.
Route::post('/webhooks/appbuild/{provider}', AppBuildWebhookController::class)
    ->name('webhooks.appbuild');

// Naara Gift async delivery callback (Reloadly/Zendit): HMAC-verified,
// idempotent, fills the three-state receipt so the redemption screen updates.
Route::post('/webhooks/giftcards/{provider}', GiftCardWebhookController::class)
    ->name('webhooks.giftcards');

// WhatsApp Cloud API webhook (WhatsApp Autopilot §7): GET verify handshake +
// X-Hub-Signature-256-verified POST for delivery status and STOP opt-outs.
Route::match(['get', 'post'], '/webhooks/whatsapp', WhatsAppWebhookController::class)
    ->name('webhooks.whatsapp');

// NAARA-PROMPT-22 — the admin route group is registered LAST in this
// file, not near the top where it originally lived. {adminGateway} is a
// bare wildcard segment for the dashboard route (no fixed suffix), which
// would otherwise shadow ANY single-segment route registered after it —
// Laravel matches routes in registration order, and a wildcard matches
// just as readily as a literal segment. Registering it last guarantees
// every other route in this file — present and future — is tried first.
// Dedicated admin sign-in page (blueprint Section 25). Lives at
// {ADMIN_PATH}/login OUTSIDE the `admin` gate so guests can reach it; EnsureAdmin
// redirects unauthenticated panel visitors here (a branded admin login) instead
// of the customer login. Posts to Fortify /login. Throttled like the panel.
// NAARA-PROMPT-22 §2.3 — a stable wildcard segment, resolved request-time by
// the admin.gateway middleware against the database-backed AdminPath::current()
// (env fallback). This is what makes the path dashboard-editable without a
// route:cache clear: nothing here bakes in a literal value at boot time.
Route::get('{adminGateway}/login', LoginController::class)
    ->where('adminGateway', AdminPath::PATTERN)
    ->middleware(['admin.gateway', 'throttle:admin'])->name('admin.login');

// Admin password recovery via security questions (guest, hard-throttled). A
// self-service path when email reset isn't available on a self-hosted install.
Route::get('{adminGateway}/recover', RecoverPassword::class)
    ->where('adminGateway', AdminPath::PATTERN)
    ->middleware(['admin.gateway', 'throttle:admin'])->name('admin.recover');

// Admin panel (blueprint Sections 13, 15, 17, 25). admin.gateway resolves the
// wildcard prefix FIRST — before the IP allow-list, auth or 2FA — so a wrong
// path 404s exactly like the IP allow-list: nobody probing it learns anything.
// `admin` then enforces the IP allow-list, a redirect to the dedicated admin
// login for guests, a plain 404 for signed-in non-admins, and TOTP 2FA
// enrolment. `auth` is intentionally omitted so EnsureAdmin owns the guest
// handling. Throttled to blunt path probing.
Route::middleware(['admin.gateway', 'admin', 'throttle:admin'])
    ->prefix('{adminGateway}')
    ->where(['adminGateway' => AdminPath::PATTERN])
    ->name('admin.')
    ->group(function () {
        // Panel entry + 2FA self-enrolment — any panel user (incl. staff).
        Route::get('/', App\Livewire\Admin\Dashboard::class)->name('dashboard');
        Route::get('/security', Security::class)->name('security');
        // Personal account (any panel user manages their own name/email/password).
        Route::get('/account', App\Livewire\Admin\Account::class)->name('account');
        // The admin's OWN appearance for the admin panel (same preference as any member; Prompt 20 §26).
        Route::get('/my-appearance', App\Livewire\Admin\MyAppearance::class)->name('my-appearance');
        // Naara chat reports (MASTER-ONLY): open to staff holding the `n2n.moderate` scope; the page itself enforces it (Moderation::canModerate).
        if (class_exists(\App\Livewire\Admin\N2N\Reports::class)) {
            Route::get('/n2n/reports', \App\Livewire\Admin\N2N\Reports::class)->name('n2n.reports');
        }

        // Admin configuration — super_admin & admin only (staff excluded).
        Route::middleware('role:super_admin|admin')->group(function () {
            Route::get('/pricing', Pricing::class)->name('pricing');
            Route::get('/exchange-rates', ExchangeRates::class)->name('exchange-rates');
            Route::get('/pricing/architect', PricingArchitect::class)->name('pricing-architect');
            Route::get('/errors', ErrorLogViewer::class)->name('errors');
            // Feature toggles (owner request) — switch features on/off + setup guides.
            Route::get('/features', Features::class)->name('features');
            // Port-in requests (Prompt 11) — ops workflow for customers bringing
            // a US/Canada number to Naara.
            Route::get('/port-in-requests', PortInRequests::class)->name('port-in-requests');
            // eSIM storefront hero (esim_upgrade Part 2) — title, description, images.
            Route::get('/esim-hero', EsimHero::class)->name('esim-hero');
            Route::get('/numbers-hero', NumbersHero::class)->name('numbers-hero');
            Route::get('/numbers-cards', NumbersBento::class)->name('numbers-cards');
            Route::get('/bento-icons', BentoIcons::class)->name('bento-icons');
            // Frontend-UX-fix blueprint Phase G — "More from Naara" banner placement system.
            Route::get('/banner-placements', BannerPlacements::class)->name('banner-placements');
            Route::get('/appearance', Splash::class)->name('appearance');
            Route::get('/preloader-studio', PreloaderStudio::class)->name('preloader-studio');
            Route::get('/notification-tones', \App\Livewire\Admin\NotificationTones::class)->name('notification-tones');
            Route::get('/theme-toggle-studio', ThemeToggleStudio::class)->name('theme-toggle-studio');
            // Marketing Copy Studio — Claude-assisted copy population for CMS pages.
            Route::get('/copy-studio', MarketingCopyStudio::class)->name('copy-studio');
            Route::get('/dashboard-theme', PlatformThemePage::class)->name('dashboard-theme');
            Route::get('/page-motion', \App\Livewire\Admin\PageMotionSettings::class)->name('page-motion');
            // What members may choose for THEIR dashboards: default skin/accent, enabled presets, lock (Prompt 20 §19).
            Route::get('/user-appearance', App\Livewire\Admin\UserAppearance::class)->name('user-appearance');
            // Theme picker — switch the platform-wide visual skin (Theme Batch 2 §4).
            Route::get('/theme', ThemePicker::class)->name('theme');
            Route::get('/branding', Branding::class)->name('branding');
            Route::get('/link-previews', LinkPreviews::class)->name('link-previews');
            Route::get('/site', SiteEditor::class)->name('site');
            Route::get('/product-lines', ProductLines::class)->name('product-lines');
            Route::get('/email-studio', EmailStudio::class)->name('email-studio');
            Route::get('/email-broadcast', EmailBroadcast::class)->name('email-broadcast');
            Route::post('/email-broadcast/inline-image', [EmailInlineImageController::class, 'store'])->name('email-broadcast.inline-image');
            Route::get('/home-media', HomeMedia::class)->name('home-media');
            Route::get('/sidebar-menu', SidebarMenu::class)->name('sidebar-menu');
            Route::get('/chrome', SiteChromePage::class)->name('chrome');
            Route::get('/legal', LegalEditor::class)->name('legal');
            Route::get('/cookies', \App\Livewire\Admin\CookieSettings::class)->name('cookies');
            Route::get('/cookies/export', [\App\Http\Controllers\Admin\CookieConsentExportController::class, 'export'])->name('cookies.export');
            Route::get('/blog', Posts::class)->name('blog');
            Route::get('/blog-reader', \App\Livewire\Admin\BlogReader::class)->name('blog-reader');
            Route::get('/tours', \App\Livewire\Admin\TourStudio::class)->name('tours');
            // Business links (MASTER-ONLY, class_exists-guarded).
            if (class_exists(\App\Livewire\Admin\BrandLinks::class)) {
                Route::get('/brand-links', \App\Livewire\Admin\BrandLinks::class)->name('brand-links');
            }
            if (class_exists(\App\Livewire\Admin\BrandTrials::class)) {
                Route::get('/brand-trials', \App\Livewire\Admin\BrandTrials::class)->name('brand-trials');
            }
            if (class_exists(\App\Livewire\Admin\FollowRewards::class)) {
                Route::get('/follow-rewards', \App\Livewire\Admin\FollowRewards::class)->name('follow-rewards');
            }
            if (class_exists(\App\Livewire\Admin\ConversationRouterSimulator::class)) {
                Route::get('/who-pays', \App\Livewire\Admin\ConversationRouterSimulator::class)->name('who-pays');
            }
            Route::get('/trustpilot', \App\Livewire\Admin\TrustpilotPanel::class)->name('trustpilot');
            Route::get('/review-moments', \App\Livewire\Admin\ReviewMomentsPanel::class)->name('review-moments');
            Route::get('/pages', CustomPages::class)->name('pages');
            // Dedicated /faq page's topics + questions (master-only, like the
            // page itself — this class ships only in master's codebase).
            Route::get('/faq', App\Livewire\Admin\FaqManager::class)->name('faq');
            Route::get('/builder', PageBuilder::class)->name('builder');
            Route::get('/app-builder', AppBuilder::class)->name('app-builder');
            // Installers (MASTER ONLY, class_exists-guarded): Windows/macOS/Linux/Chrome files + links, Android/iOS store settings.
            if (class_exists(\App\Livewire\Admin\MasterMarketing\Installers::class)) {
                Route::get('/installers', \App\Livewire\Admin\MasterMarketing\Installers::class)->name('installers');
                Route::get('/content-protection', \App\Livewire\Admin\MasterMarketing\ContentLock::class)->name('content-lock');
                Route::post('/installers/{platform}/upload', [\App\Http\Controllers\MasterMarketing\InstallerController::class, 'upload'])->where('platform', 'android|windows|macos|macos_intel|linux|linux_deb|chrome')->name('installers.upload');
                Route::post('/installers/{platform}/chunk', [\App\Http\Controllers\MasterMarketing\InstallerController::class, 'chunk'])->where('platform', 'android|windows|macos|macos_intel|linux|linux_deb|chrome')->name('installers.chunk');
                Route::post('/installers/{platform}/finalize', [\App\Http\Controllers\MasterMarketing\InstallerController::class, 'finalize'])->where('platform', 'android|windows|macos|macos_intel|linux|linux_deb|chrome')->name('installers.finalize');
                Route::post('/installers-plan', [\App\Http\Controllers\MasterMarketing\InstallerController::class, 'plan'])->name('installers.plan');
            }
            Route::get('/welcome-settings', WelcomeSettings::class)->name('welcome-settings');
            Route::get('/incidents', Incidents::class)->name('incidents');
            Route::get('/notices', Alerts::class)->name('notices');
            Route::get('/nav', NavSlots::class)->name('nav');
            Route::get('/bottom-nav', NavSettings::class)->name('bottom-nav');
            Route::get('/guides', Guides::class)->name('guides');
            Route::get('/service-icons', ServiceIconsPage::class)->name('service-icons');
            Route::get('/brand-icons', BrandIcons::class)->name('brand-icons');
            Route::get('/banners', Banners::class)->name('banners');
            Route::get('/more-apps', MoreApps::class)->name('more-apps');
            Route::get('/coupons', Coupons::class)->name('coupons');
            // Announcements & offers — push to every user's notification bell.
            Route::get('/announcements', Announcements::class)->name('announcements');
            Route::get('/credits', Credits::class)->name('credits');
            Route::get('/journey-goals', JourneyGoals::class)->name('journey-goals');
            Route::get('/journey-studio', \App\Livewire\JourneyEngine\Studio::class)->name('journey-studio');
            Route::get('/social-hunt', SocialHunt::class)->name('social-hunt');
            Route::get('/brand-directory', BrandDirectory::class)->name('brand-directory');
            Route::get('/gift-cards', App\Livewire\Admin\GiftCards::class)->name('gift-cards');
            // Naara Gift storefront hero — same system as the dashboard home hero.
            Route::get('/gift-hero', GiftHero::class)->name('gift-hero');
            Route::get('/hunt-hero', HuntHero::class)->name('hunt-hero');
            Route::get('/developer-api', DeveloperApi::class)->name('developer-api');
            // White Label page video: resumable chunked upload + poster (MASTER-ONLY, class_exists-guarded so a stray copy can never half-register it).
            if (class_exists(\App\Http\Controllers\MasterMarketing\VideoUploadController::class)) {
                Route::prefix('master-marketing/video')->name('mm-video.')->group(function () {
                    $c = \App\Http\Controllers\MasterMarketing\VideoUploadController::class;
                    Route::post('init', [$c, 'init'])->middleware('throttle:60,1')->name('init');
                    Route::put('{id}/chunk/{n}', [$c, 'chunk'])->whereNumber('n')->middleware('throttle:3000,1')->name('chunk');
                    Route::post('{id}/complete', [$c, 'complete'])->middleware('throttle:60,1')->name('complete');
                    Route::delete('{id}', [$c, 'abort'])->name('abort');
                    Route::post('poster', [$c, 'poster'])->middleware('throttle:60,1')->name('poster');
                    Route::post('forget', [$c, 'forget'])->middleware('throttle:60,1')->name('forget');
                });
            }
            // Marketing video sections (MASTER-ONLY, class_exists-guarded).
            if (class_exists(\App\Livewire\Admin\MasterMarketingVideoSections::class)) {
                Route::get('/marketing-videos', \App\Livewire\Admin\MasterMarketingVideoSections::class)->name('marketing-videos');
            }
            if (class_exists(\App\Livewire\Admin\MasterMarketingMenuIcons::class)) {
                Route::get('/menu-icons', \App\Livewire\Admin\MasterMarketingMenuIcons::class)->name('menu-icons');
            }
            Route::get('/global-payout-rail', GlobalPayoutRail::class)->name('global-payout-rail');
            Route::get('/refunds', Refunds::class)->name('refunds');
            Route::get('/reconciliation', Reconciliation::class)->name('reconciliation');
            Route::get('/analytics', Analytics::class)->name('analytics');
            Route::get('/tax-rates', TaxRates::class)->name('tax-rates');
            Route::get('/system-health', SystemHealth::class)->name('system-health');
            // Platform Bootstrap: Setup Health + credential slots (MASTER-ONLY, class_exists-guarded so a stray copy can never half-register it).
            if (class_exists(\App\Livewire\Admin\Bootstrap\SetupHealth::class)) {
                Route::get('/setup-health', \App\Livewire\Admin\Bootstrap\SetupHealth::class)->name('setup-health');
            }
            // White-label distribution oversight (Updater Batch 4).
            Route::get('/white-label', App\Livewire\Admin\WhiteLabelRegistry::class)->name('white-label');
            // White Label funnel payment review (MASTER only; admins only; class_exists-guarded).
            if (class_exists(App\Livewire\Admin\WlFunnelPaymentReview::class)) {
                Route::get('/white-label/funnel', App\Livewire\Admin\WlFunnelOverview::class)->middleware('role:super_admin|admin')->name('white-label.funnel');
                Route::get('/white-label/custom-orders', App\Livewire\Admin\WlCustomOrdersAdmin::class)->middleware('role:super_admin|admin')->name('white-label.custom-orders');
                Route::get('/white-label/funnel-check', App\Livewire\Admin\WlFunnelCheck::class)->middleware('role:super_admin|admin')->name('white-label.funnel.check');
                Route::get('/white-label/invoice-settings', App\Livewire\Admin\WlInvoiceSettingsPage::class)->middleware('role:super_admin|admin')->name('white-label.invoice-settings');
                Route::get('/white-label/invoice-preview', [\App\Http\Controllers\WlInvoiceController::class, 'preview'])->middleware(['role:super_admin|admin', 'throttle:20,1'])->name('white-label.invoice-preview');
                Route::get('/white-label/payment-review', App\Livewire\Admin\WlFunnelPaymentReview::class)->middleware('role:super_admin|admin')->name('white-label.payment-review');
                Route::get('/white-label/proof/{payment}/{i}', [\App\Http\Controllers\WlFunnelProofController::class, 'show'])->whereNumber(['payment', 'i'])->middleware(['role:super_admin|admin', 'throttle:120,1'])->name('wl-funnel-proof');
            }
            // Manual-payment receiving accounts + rates for White Label purchases (MASTER only; admins only; class_exists-guarded).
            if (class_exists(App\Livewire\Admin\WhiteLabelPayments::class)) {
                Route::get('/white-label/payments', App\Livewire\Admin\WhiteLabelPayments::class)->middleware('role:super_admin|admin')->name('white-label.payments');
            }
            // Project-intake PDF export (Prompt 21-EXT2 §6) — Livewire can't
            // stream a file download, so this is a real controller route.
            Route::get('/white-label/intake/{intake}/pdf', App\Http\Controllers\Admin\WhiteLabelIntakePdfController::class)->name('white-label.intake.pdf');
            Route::get('/gateways', Gateways::class)->name('gateways');
            Route::get('/kyc', KycReview::class)->name('kyc');
            Route::get('/merchants', Merchants::class)->name('merchants');
            Route::get('/gif-library', \App\Livewire\Admin\GifLibrary::class)->name('gif-library');
            // Business Suite master switch (MASTER-ONLY): on/off, status, and later the recording and AI knobs.
            if (class_exists(\App\Livewire\Admin\BusinessSuiteSettingsPage::class)) {
                Route::get('/business-suite', \App\Livewire\Admin\BusinessSuiteSettingsPage::class)->name('business-suite');
                Route::get('/business-suite/workspace-page', \App\Livewire\Admin\BusinessWorkspacePage::class)->name('business-suite.workspace-page');
                Route::get('/business-suite/brain-economics', \App\Livewire\Admin\BusinessBrainEconomicsPage::class)->name('business-suite.brain-economics');
            }
            // NaaraBrain (MASTER-ONLY): its own admin page (switch, limits, floating button) and measured AI cost per feature (Sprint 0).
            if (class_exists(\App\Livewire\Admin\Brain\Settings::class)) {
                Route::get('/brain', \App\Livewire\Admin\Brain\Settings::class)->name('brain');
                Route::get('/brain/ideas', \App\Livewire\Admin\Brain\Prompts::class)->name('brain.ideas');
            }
            if (class_exists(\App\Livewire\Admin\Brain\AiCosts::class)) {
                Route::get('/ai-costs', \App\Livewire\Admin\Brain\AiCosts::class)->name('ai-costs');
            }
            // N2N home (MASTER-ONLY): switches, limits and realtime health; registered only where the module exists.
            // Naara Travel Handbook Store (MASTER-ONLY, class_exists-guarded so a fork never registers it).
            if (class_exists(\App\Livewire\Admin\Handbooks\HandbookManager::class)) {
                Route::get('/handbooks', \App\Livewire\Admin\Handbooks\HandbookManager::class)->name('handbooks');
                Route::get('/handbooks/refunds', \App\Livewire\Admin\Handbooks\HandbookRefunds::class)->name('handbooks.refunds');
                Route::get('/handbooks/settings', \App\Livewire\Admin\Handbooks\HandbookSettingsPage::class)->name('handbooks.settings');
                Route::get('/handbooks/new', \App\Livewire\Admin\Handbooks\HandbookEditor::class)->name('handbooks.new');
                Route::get('/handbooks/{handbook}/edit', \App\Livewire\Admin\Handbooks\HandbookEditor::class)->whereNumber('handbook')->name('handbooks.edit');
                Route::get('/handbooks/{handbook}/manuscript', \App\Livewire\Admin\Handbooks\HandbookManuscript::class)->whereNumber('handbook')->name('handbooks.manuscript');
                Route::get('/handbooks/manuscript/{token}/chapter/{n}', [\App\Http\Controllers\Admin\HandbookManuscriptController::class, 'chapter'])->whereNumber('n')->name('handbooks.manuscript.chapter');
                Route::get('/handbooks/manuscript/{token}/asset', [\App\Http\Controllers\Admin\HandbookManuscriptController::class, 'asset'])->name('handbooks.manuscript.asset');
                Route::get('/handbooks/{id}/download/{kind}', [\App\Http\Controllers\Admin\HandbookAdminController::class, 'download'])->whereNumber('id')->whereIn('kind', ['epub', 'pdf', 'cover', 'back-cover'])->name('handbooks.download');
                Route::get('/handbooks/{id}/cover', [\App\Http\Controllers\Admin\HandbookAdminController::class, 'cover'])->whereNumber('id')->name('handbooks.cover');
                Route::get('/handbooks/{id}/back-cover', [\App\Http\Controllers\Admin\HandbookAdminController::class, 'backCover'])->whereNumber('id')->name('handbooks.backcover');
                Route::get('/handbooks/{id}/preview', [\App\Http\Controllers\Admin\HandbookAdminController::class, 'preview'])->whereNumber('id')->name('handbooks.preview');
                Route::get('/handbooks/{id}/preview/epub', [\App\Http\Controllers\Admin\HandbookAdminController::class, 'epub'])->whereNumber('id')->name('handbooks.preview.epub');
                Route::get('/handbooks/{id}/preview/key', [\App\Http\Controllers\Admin\HandbookAdminController::class, 'key'])->whereNumber('id')->name('handbooks.preview.key');
                Route::match(['get', 'post', 'delete'], '/handbooks/{id}/preview/noop', [\App\Http\Controllers\Admin\HandbookAdminController::class, 'noop'])->whereNumber('id')->name('handbooks.preview.noop');
            }
            if (class_exists(\App\Livewire\Admin\N2N\Settings::class)) {
                Route::get('/n2n', \App\Livewire\Admin\N2N\Settings::class)->name('n2n');
                Route::get('/n2n/brands', \App\Livewire\Admin\N2N\BrandProfiles::class)->name('n2n.brands');
                Route::get('/n2n/calls', \App\Livewire\Admin\N2N\CallSettings::class)->name('n2n.calls');
                Route::get('/n2n/group-calls', \App\Livewire\Admin\N2N\GroupCallSettings::class)->name('n2n.group-calls');
                Route::get('/n2n/notices', \App\Livewire\Admin\N2N\ChatNoticesAdmin::class)->name('n2n.notices');
                Route::get('/n2n/status', \App\Livewire\Admin\N2N\StatusSettings::class)->name('n2n.status');
                Route::get('/n2n/chat-controls', \App\Livewire\Admin\N2N\ChatControls::class)->name('n2n.chat-controls');
            }
            // Naara Widgets controls (MASTER-ONLY): registered only where the module exists.
            if (class_exists(\App\Livewire\Admin\MerchantWidgetControls::class)) {
                Route::get('/merchant-widgets', \App\Livewire\Admin\MerchantWidgetControls::class)->name('merchant-widgets');
            }
            Route::get('/partners', Partners::class)->name('partners');
            Route::get('/users', Users::class)->name('users');
            // Growth stack: social links, tracking pixels, social sign-in guides.
            Route::get('/integrations', Integrations::class)->name('integrations');
            Route::get('/deletions', AccountDeletions::class)->name('deletions');
            // Erasure fix Phase A: read-only, heavily-audited lookup of an
            // anonymized account's retained financial/order trail.
            Route::get('/legal-hold', LegalHoldRecords::class)->name('legal-hold');
            Route::get('/support-agent', SupportAgent::class)->name('support-agent');
        });

        // Support ticket queue (Module 25) — staff with the tickets.manage scope,
        // plus admin/super_admin (who hold every scope / bypass).
        // Payouts: admins/super admins (all scopes) and staff granted a payouts scope (Addendum D-3.19).
        Route::middleware('permission:payouts.review|payouts.finance')->group(function () {
            Route::get('/payouts', Payouts::class)->name('payouts');
            Route::get('/payouts/health', App\Livewire\Admin\PayoutHealth::class)->name('payout-health');
            // Crypto payout wallets must be on the provider's whitelist before they can be paid (master only; class_exists-guarded).
            if (class_exists(App\Livewire\Admin\CryptoWhitelist::class)) {
                Route::get('/payouts/crypto-whitelist', App\Livewire\Admin\CryptoWhitelist::class)->name('crypto-whitelist');
            }
        });

        Route::middleware('permission:tickets.manage')->group(function () {
            Route::get('/tickets', SupportQueue::class)->name('tickets');
        });

        // eSIM Control Center (BUILD-8 §4) — staff with the esim.manage scope,
        // plus admin/super_admin. One section for sync, margins, tooltips, images.
        Route::middleware('permission:esim.manage')->group(function () {
            Route::get('/esim', EsimControlCenter::class)->name('esim');
        });

        // NCI Operations Center (NAARA-BUILD-17) — staff with the nci.view scope,
        // plus admin/super_admin. Override actions inside each page additionally
        // gate on nci.override.
        Route::middleware('permission:nci.view')->prefix('nci')->name('nci.')->group(function () {
            Route::get('/registry', ProviderRegistry::class)->name('registry');
            Route::get('/provider/{provider}', ProviderDetail::class)->name('provider');
            Route::get('/health', HealthMonitor::class)->name('health');
            Route::get('/routing', RoutingConsole::class)->name('routing');
            Route::get('/wallets', Wallets::class)->name('wallets');
        });

        // Provider login manager (MASTER-ONLY, class_exists-guarded): where and how the owner signs in to each supplier dashboard. No passwords.
        if (class_exists(\App\Livewire\Admin\ProviderLogins::class)) {
            Route::get('/provider-logins', \App\Livewire\Admin\ProviderLogins::class)->middleware('role:super_admin|admin')->name('provider-logins');
        }

        // Naara Respond setup guide (MASTER-ONLY, class_exists-guarded): Meta, WhatsApp, Telegram, web chat, email, AI key and go-live, with real callback addresses.
        if (class_exists(\App\Livewire\Admin\OmniSetupGuide::class)) {
            Route::get('/respond-setup-guide', \App\Livewire\Admin\OmniSetupGuide::class)->middleware('role:super_admin|admin')->name('respond-setup-guide');
        }

        // Staff, backups + maintenance loop — super_admin only (Sections 27–29).
        Route::middleware('role:super_admin')->group(function () {
            // Every payout knob in one schema-driven screen (Addendum D, "easy and not hard-coded").
            Route::get('/payout-settings', App\Livewire\Admin\PayoutSettingsPage::class)->name('payout-settings');
            Route::get('/staff', Staff::class)->name('staff');
            Route::get('/api-keys', ProviderKeys::class)->name('api-keys');
            Route::get('/email', EmailSettings::class)->name('email');
            Route::get('/backups', Backups::class)->name('backups');
            Route::get('/maintenance', Maintenance::class)->name('maintenance');
            // Platform updater (Updater Batch 2) — apply signed .naaraupdate
            // packages with automatic rollback. Most sensitive screen: super_admin.
            Route::get('/updater', Updater::class)->name('updater');
            Route::get('/ui-kit', UiKit::class)->name('ui-kit');
        });
    });

/*
 | Naara Widgets hosted checkout (MASTER-ONLY, docs/build-specs/NAARA-MERCHANT-WIDGETS-BLUEPRINT.md §8). The ONLY route that may be
 | framed by a third party, under its own per-session CSP (MerchantWidgetCheckoutSecurityHeaders). Same kill switch as the API.
 */
if (class_exists(\App\Http\Controllers\MerchantWidget\HostedCheckoutController::class)) {
Route::middleware(['merchant.widget.enabled', 'merchant.widget.checkout.headers', 'throttle:30,1'])->group(function () {
    Route::get('/widget/checkout/{token}', [\App\Http\Controllers\MerchantWidget\HostedCheckoutController::class, 'show'])->name('merchant-widget.checkout');
    Route::get('/widget/return/{order}', [\App\Http\Controllers\MerchantWidget\HostedCheckoutController::class, 'done'])->middleware('signed')->name('merchant-widget.return');
    Route::get('/widget/plugin/version', fn () => response()->json(['version' => \App\Services\MerchantWidgets\MerchantWidgetPluginPackager::version()])->header('Cache-Control', 'public, max-age=3600'))->name('merchant-widget.plugin.version');
    Route::post('/widget/checkout/{token}/pay', [\App\Http\Controllers\MerchantWidget\HostedCheckoutController::class, 'pay'])->name('merchant-widget.checkout.pay');
});
}


// Browser error beacon (engineering blueprint 0.3): open to guests, throttled, scrubbed in the controller.
Route::post('/_diag/client-error', [\App\Http\Controllers\ClientErrorController::class, 'store'])
    ->middleware('throttle:20,1')->name('diag.client-error');
