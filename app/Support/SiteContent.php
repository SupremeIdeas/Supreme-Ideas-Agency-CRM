<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * CMS content for the public marketing pages (Module 27). The brand copy
 * (from the owner's "Complete Brand Copy Document") ships as code defaults;
 * the admin Pages editor stores per-field overrides, a visible flag, an
 * order, and an optional section image in ONE Setting row per page
 * (site.page.{page}), merged over the defaults at read time. Cached; busted
 * on any site.* save. So the marketing site works out of the box and is
 * fully editable with no redeploy.
 */
class SiteContent
{
    public const PAGES = ['home', 'about', 'how-it-works', 'contact', 'faq', 'pricing', 'download', 'navigation', 'products', 'naara-chat', 'naara-brain', 'naara-gift', 'white-label', 'business-suite'];

    /** Pages that exist on the master only (their copy classes carry `MasterMarketing` in the path, so a fork never receives them). */
    public const MASTER_ONLY_PAGES = ['faq', 'products', 'naara-chat', 'naara-brain', 'naara-gift', 'white-label', 'business-suite'];

    /**
     * PAGES minus any page that's master-only. Today that's just 'faq'
     * (owner decision, 2026-09-21 — the dedicated FAQ page's own route
     * 404s on a white-label fork, so its admin editor tab and page-select
     * shouldn't be reachable there either). Use this instead of the raw
     * PAGES const anywhere an admin picks a page to edit.
     *
     * @return list<string>
     */
    public static function editablePages(): array
    {
        return FeatureEntitlements::isMaster()
            ? self::PAGES
            : array_values(array_diff(self::PAGES, self::MASTER_ONLY_PAGES));
    }

    /**
     * Sections that are self-contained and safe to reuse on ANY marketing page
     * (BUILD: reusable sections). Each renders through a shared partial —
     * `resources/views/marketing/sections/{key}.blade.php` — rather than a
     * page-specific one, so a copy of it renders identically wherever it lands.
     * Page-bespoke sections (hero copy, the contact form, …) are NOT portable.
     *
     * @var list<string>
     */
    public const PORTABLE_SECTIONS = ['audiences'];

    /**
     * The canonical default fields for a portable section, wherever it is
     * defined in the shipped copy (portable sections live under one home page by
     * default). Used so a COPIED instance on another page still knows its field
     * shape — for editing, diffing and reset.
     *
     * @return array<string, string>
     */
    public static function portableDefaults(string $key): array
    {
        foreach (self::defaults() as $sections) {
            if (isset($sections[$key])) {
                return $sections[$key];
            }
        }

        return [];
    }

    /**
     * Seeded defaults for the homepage Diagnosis/Architecture/Promise block:
     * the card copy (resources/data/diagnosis-copy.json) plus every deep-dive
     * modal's eyebrow/headline/body/quote/bullets (resources/data/
     * bento-diagnosis-modals.json, flattened to `m_{cell}_{part}` fields, with
     * the cell id's dash as an underscore — `s2-a` -> `m_s2_a_headline`). Kept
     * in data files so ~100 fields don't bloat this class; edited from the
     * Site Editor like any other section.
     *
     * @return array<string, string>
     */
    public static function diagnosisDefaults(): array
    {
        static $memo = null;
        if ($memo !== null) {
            return $memo;
        }

        $fields = [];
        $copy = @file_get_contents(resource_path('data/diagnosis-copy.json'));
        foreach ((array) json_decode((string) $copy, true) as $k => $v) {
            $fields[(string) $k] = (string) $v;
        }

        $modals = @file_get_contents(resource_path('data/bento-diagnosis-modals.json'));
        foreach ((array) json_decode((string) $modals, true) as $cell => $m) {
            $id = 'm_'.str_replace('-', '_', (string) $cell);
            foreach (['eyebrow', 'headline', 'body', 'quote'] as $part) {
                $fields["{$id}_{$part}"] = (string) ($m[$part] ?? '');
            }
            $fields["{$id}_bullets"] = implode("\n", (array) ($m['bullets'] ?? []));
        }

        return $memo = $fields;
    }

    /**
     * The modal payload the diagnosis partial hands to its JS: the shipped
     * JSON overlaid with whatever the admin edited. Body HTML is reduced to a
     * small allow-list so a pasted <script> or inline handler can never ship.
     *
     * @param  array<string, mixed>  $s  the diagnosis section's merged fields
     * @return array<string, array<string, mixed>>
     */
    public static function diagnosisModals(array $s): array
    {
        $base = (array) json_decode((string) @file_get_contents(resource_path('data/bento-diagnosis-modals.json')), true);
        $out = [];

        foreach ($base as $cell => $m) {
            $id = 'm_'.str_replace('-', '_', (string) $cell);
            $bullets = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($s["{$id}_bullets"] ?? '')) ?: [])));

            $out[$cell] = array_merge($m, [
                'eyebrow' => (string) ($s["{$id}_eyebrow"] ?? $m['eyebrow'] ?? ''),
                'headline' => (string) ($s["{$id}_headline"] ?? $m['headline'] ?? ''),
                'body' => strip_tags((string) ($s["{$id}_body"] ?? $m['body'] ?? ''), '<p><strong><em><br><ul><ol><li>'),
                'quote' => (string) ($s["{$id}_quote"] ?? $m['quote'] ?? ''),
                'bullets' => $bullets !== [] ? $bullets : (array) ($m['bullets'] ?? []),
            ]);
        }

        return $out;
    }

    /**
     * The shipped copy. page => section => [fields]. Reserved keys the editor
     * adds per section: visible (bool), order (int), image (url).
     * List-ish content is kept as numbered fields so the editor stays a flat,
     * obvious form for a non-technical admin.
     *
     * @return array<string, array<string, array<string, string>>>
     */
    public static function defaults(): array
    {
        $pages = [
            'home' => [
                'hero' => [
                    'eyebrow' => 'eSIM for Global Africans',
                    'headline' => 'Land Anywhere. Connect Instantly.',
                    'subheadline' => 'NaaraSim gives you a local data plan in 190+ countries — activated on your phone before you leave home. No SIM cards. No airport counters. No roaming shocks.',
                    'cta_primary' => 'Get Your eSIM Now',
                    'cta_secondary' => 'See How It Works',
                    'social_proof' => 'Trusted by travelers from Lagos, Accra, Nairobi and beyond · 190+ countries covered · Activates in under 3 minutes',
                    // BUILD-13 (marketing): a real, visible product/device-mockup
                    // shown BELOW the description and ABOVE the CTA row — separate
                    // from the full-bleed backdrop `image` field. Empty by default.
                    'showcase_image' => '',
                    'stat_1' => '190+ Countries', 'stat_2' => '3 Min Setup', 'stat_3' => '0 Physical SIM', 'stat_4' => '24/7 Support',
                    // Hero customization foundation (owner request, 2026-09-21):
                    // admin-adjustable subheadline colour, a glass-card accent
                    // below the description, and a size dial for the hero's
                    // image elements (mockup/stat-card group) — built as plain
                    // CMS fields (not a separate settings class) so adding the
                    // NEXT knob (resize/reorder any other hero card) is just
                    // one more key here plus one more `$s['...']` read in
                    // hero.blade.php, no new plumbing required. Empty/zero/100
                    // defaults render byte-identical to the pre-existing hero.
                    'subheadline_color' => '', // '' = automatic (mix-blend two-tone on the WebGL branch, fixed slate on the image branch)
                    'glass_depth' => 0, // 0-100 — 0 renders no glass card at all
                    'image_scale' => 100, // 50-150% — scales the mockup/stat-card visual group

                    // Marketing CMS audit (2026-10-02): everything below used to be
                    // hard-coded in hero.blade / _who-for / _flags. Seeded here so the
                    // Site Editor shows (and can change) every word and photo.
                    'spot_status' => 'Connected',
                    'spot_title_1' => 'One App.',
                    'spot_title_2' => 'Every Connection.',
                    'spot_link_label' => 'Explore NaaraSim',
                    'spot_image' => '/images/home/hero-one-app-connect.webp',
                    'world_title_1' => 'Your World.',
                    'world_title_2' => 'One Connection.',
                    'world_stat' => '190+',
                    'world_body' => 'Stay connected wherever life takes you. Get eSIMs, local numbers, and a smarter mobile wallet — all from one simple platform built for global Africans.',
                    'world_avatars_image' => '/images/home/hero-avatar-cluster.webp',
                    'flags_label' => '190+ countries · one eSIM · no borders',
                    'who_eyebrow' => 'Who Naara Is For',
                    'who_heading' => 'Made for the way you move.',
                    'who_subtext' => 'One connectivity family, built for every kind of border-crosser. Find yourself below.',
                    'who_1_title' => 'Diaspora & Frequent Flyers',
                    'who_1_desc' => 'Home is wherever your people are. Keep one number reachable across every trip back, and land with data already active — no new SIM, no roaming shock, no explaining yourself at customs.',
                    'who_1_image' => '/images/home/who-for/diaspora-flyers.webp',
                    'who_2_title' => 'Digital Nomads & Remote Workers',
                    'who_2_desc' => 'Your standup doesn\'t care which country you woke up in. Get dependable data in 190+ countries so a deploy, a call or a deadline never waits on finding a SIM shop.',
                    'who_2_image' => '/images/home/who-for/digital-nomads.webp',
                    'who_3_title' => 'Students Studying Abroad',
                    'who_3_desc' => 'New campus, same number. Stay reachable for family, banking OTPs and visa calls back home, while your Naara data plan covers you from the moment you land.',
                    'who_3_image' => '/images/home/who-for/students-abroad.webp',
                    'who_4_title' => 'Cross-Border Traders & Entrepreneurs',
                    'who_4_desc' => 'Close a deal in Lagos, follow up from Accra, ship from Nairobi — same week. A local number builds trust on the ground; a Naara data plan keeps you online between every stop.',
                    'who_4_image' => '/images/home/who-for/cross-border-traders.webp',
                    'who_5_title' => 'First-Time & Backpacking Travelers',
                    'who_5_desc' => 'No airport SIM counters. No language barrier at the kiosk. Activate your eSIM before you even board, so the first thing you do on arrival is exactly what you came for.',
                    'who_5_image' => '/images/home/who-for/first-time-travelers.webp',
                    'who_6_title' => 'Remote Teams & Businesses',
                    'who_6_desc' => 'Equip a whole team with numbers and data from one dashboard. Provision, monitor and top up every teammate’s connectivity without juggling five carrier accounts.',
                    'who_6_image' => '/images/home/who-for/remote-teams.webp',
                ],
                // Phase C (Marketing/Chat blueprint): these two bands used to be
                // hard-coded partials injected at fixed anchors, so they never
                // appeared in the Site Editor. They are real sections now — show/
                // hide, reorder, edit copy, and set the top corner radius.
                'manifesto' => [
                    'tag' => 'Our Manifesto',
                    'title' => 'What we',
                    'title_accent' => 'stand for.',
                    'body' => 'Naara exists because staying connected should never depend on a border, a queue or a surprise bill. We built one wallet for data, numbers and gifts, priced honestly, delivered in minutes, and refunded automatically when we miss. No swapping SIMs. No hidden fees. No excuses. Just connection that works the way you always expected it to.',
                ],
                'diagnosis' => array_merge([
                    'eyebrow' => 'The Diagnosis',
                    'headline' => "The problem isn't signal.",
                    'headline_accent' => "It's what sits behind it.",
                    'promise_eyebrow' => 'The Naara Promise',
                ], self::diagnosisDefaults()),
                'how' => [
                    'eyebrow' => 'Simple by Design',
                    'headline' => 'Connected in Three Steps',
                    'subtext' => 'No store visits. No paperwork. No waiting. Just a phone and two minutes.',
                    'step_1_title' => 'Pick Your Destination',
                    'step_1_text' => "Choose the country or region you're traveling to and select a data plan that fits your trip — from a weekend pass to a 30-day plan.",
                    'step_1_image' => '/images/marketing/mockups/home/step-1-destination.webp',
                    'step_2_title' => 'Select Your Data Plan',
                    'step_2_text' => 'Compare local, regional and global plans by size and duration, then pay securely. Your eSIM QR code lands in your inbox the moment you check out.',
                    'step_2_image' => '/images/marketing/mockups/home/step-2-plan.webp',
                    'step_3_title' => 'Activate on Your Phone',
                    'step_3_text' => 'Scan the QR code or follow our guided setup — the eSIM installs in about two minutes. The moment you land, NaaraSim connects to local networks automatically.',
                    'step_3_image' => '/images/marketing/mockups/home/step-3-activate.webp',
                    'cta' => "Start Now — It's Instant",
                ],
                // Was a hard-coded decorative partial; a real section now (show/hide,
                // reorder, edit every line of the pitch and the preview card).
                'wizard' => [
                    'eyebrow' => 'On-demand, on your terms',
                    'headline' => 'Stop juggling numbers. Just ask the Wizard.',
                    'body' => 'No more hunting for the right number or the best plan. Tell our NaaraSim Intelligent Wizard what you need — a country, a service, a budget — and it outsources the search for you, matching your taste to the perfect number or eSIM for any credible purpose, in seconds.',
                    'bullet_1' => 'Tell it your preference — it does the matching',
                    'bullet_2' => 'OTP & SMS numbers for any verification',
                    'bullet_3' => 'Voice-ready virtual lines, on demand',
                    'cta_label' => 'Try the Wizard free',
                    'mock_title' => 'Ask NaaraSim',
                    'mock_subtitle' => 'Your intelligent connectivity wizard',
                    'mock_prompt' => 'What do you need today?',
                    'mock_request' => 'A US number for WhatsApp',
                    'mock_match' => 'Best match found',
                    'mock_detail' => 'SMS + voice · instant OTP · refunded if no code',
                    'mock_price' => '$1.20',
                    'mock_button' => 'Get it now',
                ],
                'features' => [
                    'eyebrow' => 'Built Different',
                    'headline' => 'Why Travelers Choose NaaraSim',
                    'f1_title' => 'Instant Digital Delivery',
                    'f1_text' => 'No waiting for shipping. Your eSIM arrives in your email the moment you pay. Activate it from anywhere in the world.',
                    'f2_title' => 'Keep Your Number',
                    'f2_text' => 'Your NaaraSim runs alongside your regular SIM. Calls and texts still come through your home number. NaaraSim handles your data.',
                    'f3_title' => '190+ Countries, One Platform',
                    'f3_text' => "Local, regional, and global plans available. Whether you're crossing one border or ten, there's a NaaraSim plan for your route.",
                    'f4_title' => 'No Roaming Bills',
                    'f4_text' => "You know exactly what you're paying before you travel. Fixed plan. No surprises. Your network back home stays off data abroad.",
                    'f5_title' => 'Top Up Anytime',
                    'f5_text' => 'Running low mid-trip? Add more data from the app without buying a new plan. Your eSIM stays active, your connection stays strong.',
                    'f6_title' => 'Built for African Travelers',
                    'f6_text' => 'NaaraSim was designed with the African traveler in mind. Support that understands your journey. Pricing that respects your reality.',
                ],
                'products' => [
                    'eyebrow' => 'One Family, Everything Connected',
                    'headline' => 'What Naara Gives You',
                    'subtext' => 'Naara is the family. NaaraSim keeps you connected — data and numbers across 190+ countries. Naara Gift lets you send the brands people love. One account, one wallet, one Naara.',
                    // (The four product cards come from Admin -> Product lines — ProductLineSettings — not
                    // from fields here; the old unused p1..p4 copy fields were removed so the editor
                    // doesn't offer text that changes nothing.)
                ],
                // "Who Naara is for" audience tabs (BUILD-12). Section header text
                // is admin-editable here; the six panels' approved copy lives in
                // the partial (finished copy). The six *_image fields are
                // admin-swappable via the SiteEditor inline-artwork uploader.
                'audiences' => [
                    'eyebrow' => 'Made for the way you move',
                    'headline' => 'Who Naara Is For',
                    'subtext' => 'One connectivity family, built for every kind of border-crosser. Find yourself below.',
                    'travelers_image' => '/images/audiences/naara-leisure-traveler.webp',
                    'business_image' => '/images/audiences/naara-business-professionals.webp',
                    'entrepreneurs_image' => '/images/audiences/naara-software-engineer.webp',
                    'creators_image' => '/images/audiences/naara-travel-creator.webp',
                    'privacy_image' => '/images/audiences/naara-secure-professional.webp',
                    'families_image' => '/images/audiences/naara-staying-connected.webp',
                ],
                'coverage' => [
                    'eyebrow' => "Wherever You're Going",
                    'headline' => '190+ Countries. One eSIM.',
                    'text' => 'From London to Lagos, Dubai to Dakar, Toronto to Tokyo — NaaraSim connects you to premium local networks the moment you land. We cover every continent, every major travel corridor, every destination on your bucket list.',
                    'highlights' => 'Europe · North America · Asia Pacific · Middle East · Latin America · Africa',
                    'callout' => 'Planning a multi-country trip? Our regional plans keep you connected across entire continents without switching plans.',
                    'cta' => 'Check Coverage for Your Destination',
                ],
                'pricing' => [
                    'eyebrow' => 'Transparent Pricing',
                    'headline' => 'Plans That Make Sense',
                    'text' => "No monthly fees. No contracts. Pay for exactly what you need — whether that's a 3-day pass or a 30-day global plan. Every plan includes instant activation and 24/7 support.",
                    'callout' => 'Plans start from $5 · No hidden fees · Cancel anytime',
                    'cta' => 'See All Plans',
                    'trust' => 'You only pay when you travel. No subscription required.',
                ],
                'testimonials' => [
                    'eyebrow' => 'Real Travelers, Real Connections',
                    'headline' => 'What Our Customers Say',
                    't1_name' => 'Chidinma O.', 't1_route' => 'Lagos → London',
                    't1_quote' => "I set up my NaaraSim the night before my flight and by the time I landed at Heathrow, my phone was already showing full bars. I didn't even notice the moment it connected. That's exactly how it should work.",
                    // No real photo on file for these named customers — the
                    // <x-avatar> component's own initials-circle fallback is
                    // the honest placeholder (Theme Visual Rebuild Rule 2's
                    // exception), not a stock photo of a stranger mislabelled
                    // with their name. Empty by default; admin-uploadable.
                    't1_image' => '',
                    't2_name' => 'Kwame A.', 't2_route' => 'Accra → Dubai',
                    't2_quote' => "I've used three different eSIM apps before NaaraSim. None of them felt like they were made for me. This one actually works in the countries I travel to, and the top-up process is so simple I did it in a taxi.",
                    't2_image' => '',
                    't3_name' => 'Amara T.', 't3_route' => 'Nairobi → Toronto',
                    't3_quote' => 'The setup took literally two minutes. My previous experience with roaming was painful — surprise bills, dead zones, hours on the phone with customer service. NaaraSim solved all of that in one purchase.',
                    't3_image' => '',
                ],
                // Topic-grouped curation (owner request, 2026-09-21): "take 40%
                // important content from our general FAQ grouped by the topic
                // without the hero and seed to replace the FAQ in our homepage
                // section so it still feels premium" — 5 topics, the questions
                // a first-time visitor is most likely to have before buying,
                // curated from the fuller FAQ (now on its own /faq page). Kept
                // flat (g{n}_label / g{n}_q{m} / g{n}_a{m}) so the generic
                // admin Site Editor field renderer (scalar text/textarea per
                // key) still works with no new plumbing.
                // Was a hard-coded partial; a real section now. The posts themselves
                // come from Admin -> Blog (self-hides until one is published).
                'blogs' => [
                    'eyebrow' => 'From the Blog',
                    'headline' => 'Guides & news for border-crossers',
                    'cta_label' => 'Read the blog',
                    'card_cta' => 'Read more',
                ],
                'faq' => [
                    'headline' => 'Questions We Hear a Lot',
                    'g1_label' => 'Getting Started',
                    'g1_q1' => 'What is Naara?',
                    'g1_a1' => 'Naara is a Pan-African connectivity platform. In one app and one wallet you can buy eSIM data plans for 190+ countries and phone numbers (verification numbers and a permanent line). Our promise: Stay Connected. No Borders. No Swaps.',
                    'g1_q2' => 'How fast can I get connected?',
                    'g1_a2' => 'Purchase to connected usually takes under three minutes: buy a plan, scan the QR code, and your data starts when you first connect in your destination.',
                    'g2_label' => 'eSIM Data',
                    'g2_q1' => 'Will my phone work with an eSIM?',
                    'g2_a1' => "Most phones released from around 2018 do, but it must also be carrier-unlocked. On many phones, dialing *#06# shows an EID number when eSIM is supported — or use our compatibility checker.",
                    'g2_q2' => "What does \"unlimited\" mean on unlimited plans?",
                    'g2_a2' => 'Unlimited plans are subject to a fair-usage policy: after a daily or total high-speed allowance, speeds may be reduced. The exact allowance is shown on each plan before you buy.',
                    'g3_label' => 'Verification Numbers',
                    'g3_q1' => 'What if my code never arrives?',
                    'g3_a1' => 'If no code arrives within 15 minutes of ordering, your wallet is automatically refunded. There is no ticket and no waiting for approval.',
                    'g3_q2' => 'Can a number receive voice calls?',
                    'g3_a2' => 'Verification numbers are built for SMS codes. For calls, use a Naara Line — a permanent number with calling.',
                    'g4_label' => 'Wallet & Pricing',
                    'g4_q1' => 'Are there hidden fees?',
                    'g4_a1' => 'No. The price you confirm is the price you pay, and the checkout shows taxes and fees as $0.00.',
                    'g4_q2' => 'How can I add money?',
                    'g4_a2' => 'Cards and bank transfer are available. In some countries, such as Ghana and Kenya, mobile money is also available. The options at checkout depend on where you are.',
                    'g5_label' => 'Refunds & Reliability',
                    'g5_q1' => 'What is your refund policy in short?',
                    'g5_a1' => 'We aim to be fair and honest. Unstarted eSIMs are refundable, numbers that get no code refund automatically, and approved refunds go back to your wallet.',
                    'g5_q2' => 'Is my money safe if a system goes down?',
                    'g5_a2' => 'Your wallet is recorded as a ledger, and balance changes are atomic, so a failed step does not leave you charged without an order or a refund.',
                    'cta' => 'Still have questions? Our support team responds within minutes — not hours.',
                    'cta_contact' => 'Contact us',
                    'cta_all' => 'See all questions',
                ],
                'cta' => [
                    'headline' => 'Your Next Trip Starts Here',
                    'subtext' => "Stop worrying about connectivity and start focusing on why you're traveling. Get your NaaraSim before you leave — and land ready.",
                    'cta_primary' => 'Get Connected Now',
                    'cta_secondary' => 'Browse Plans',
                    'trust' => 'Instant activation · No contracts · 190+ countries · 24/7 support',
                ],
            ],
            'faq' => [
                'hero' => [
                    'eyebrow' => 'Naara Help Centre',
                    'headline' => 'Stay connected. No borders. No swaps.',
                    'subtext' => 'Clear answers on eSIM data, verification numbers, your Naara Line, the wallet, refunds and rewards, all in one place.',
                    // Real shipped default (owner-supplied, 2026-09-21) — an
                    // iridescent light-refraction backdrop behind the gradient
                    // overlay, admin-swappable from the Site Editor.
                    'image' => '/images/faq/faq-hero-bg.webp',
                    // Dark-mode twin of the same photo (owner follow-up,
                    // 2026-09-21): not the light image dimmed with opacity —
                    // a genuinely re-graded export (navy-tinted, tamed
                    // highlights) so its bright/white areas melt into the
                    // dark page instead of glaring on it. Unlike 'image'
                    // above, page() has no special-case for this key, so the
                    // coded default surfaces on its own; no seeder needed.
                    'image_dark' => '/images/faq/faq-hero-bg-dark.webp',
                ],
                'ui' => [
                    'stat_1_value' => '190+', 'stat_1_label' => 'Countries',
                    'stat_2_value' => '1', 'stat_2_label' => 'Wallet',
                    'stat_3_label' => 'Answers',
                    'search_placeholder' => 'Search: try "eSIM", "refund", "wallet"...',
                    'search_button' => 'Search',
                    'trust_text' => 'Verification numbers are covered too: no code within {minutes} minutes means an automatic refund — no ticket required. See our',
                    'trust_link' => 'refund & reliability policy',
                    'topics_title' => 'Topics',
                    'links_title' => 'Quick Links',
                    'link_pricing' => 'Pricing', 'link_wallet' => 'My Wallet', 'link_refund' => 'Refund Policy', 'link_status' => 'System Status', 'link_support' => 'Contact Support',
                    'empty_title' => 'Nothing matched that search.',
                    'empty_text' => 'Try a different term, or message us and we will answer directly.',
                    'empty_button' => 'Talk to NaaraCare',
                ],
                'cta' => [
                    'eyebrow' => 'Still need a hand?',
                    'title_1' => 'Get connected in minutes,',
                    'title_2' => 'and we have your back.',
                    'text' => 'Buy an eSIM, grab a number, or ask NaaraCare anything. Unstarted eSIMs are refundable, and numbers that get no code refund automatically.',
                    'button_main' => 'Talk to NaaraCare',
                    'button_ghost' => 'See eSIM Plans',
                ],
                // Owner-supplied design handoff (2026-09-21): the dedicated
                // page's actual Q&A content moved off SiteContent entirely —
                // it's now DB-backed (FaqCategory/FaqItem, seeded by
                // FaqSeeder, edited at Admin -> FAQ) so 12 topics x ~9
                // questions each doesn't become ~200 flat SiteContent keys.
                // Only the hero above stays on the generic Site Editor.
            ],
            // Header menu labels (the floating nav + mega-menu on every marketing page).
            'navigation' => [
                'menu' => [
                    'family_label' => 'The Naara Family', 'family_desc' => 'Everything Naara gives you',
                    'esim_label' => 'eSIM Data Plans', 'esim_desc' => 'Local data, 190+ countries',
                    'how_label' => 'How It Works', 'how_desc' => 'From purchase to connected',
                    'gift_label' => 'Naara Gift', 'gift_desc' => 'Gift cards for 1,000+ brands', 'gift_badge' => 'Soon',
                    'dev_label' => 'Developers', 'dev_desc' => 'Resell via our API',
                    'pricing_label' => 'Pricing', 'pricing_desc' => 'Transparent, no surprises',
                    'faq_label' => 'FAQ', 'faq_desc' => 'Answers to common questions',
                    'about_label' => 'About', 'about_desc' => 'Our mission & story',
                    'blog_label' => 'Blog', 'blog_desc' => 'News & guides',
                    'contact_label' => 'Contact', 'contact_desc' => 'Talk to us',
                    'heading_products' => 'Products', 'heading_developers' => 'Developers', 'heading_company' => 'Company',
                    'menu_label' => 'Menu', 'tagline' => 'Stay Connected · No Borders',
                    'cta_dashboard' => 'Dashboard', 'cta_dashboard_full' => 'My Dashboard',
                    'cta_start' => 'Get Started', 'signin' => 'Sign in',
                ],
            ],
            // Marketing CMS audit (2026-10-02): the dedicated /pricing and /download
            // pages' own copy was hard-coded in their views — now editable.
            'pricing' => [
                'hero' => [
                    'eyebrow' => 'Transparent Pricing',
                    'headline' => 'Plans That Make Sense',
                    'subtext' => 'No monthly fees. No contracts. Pay for exactly what you need — a short pass or a full month. Every plan includes instant activation and 24/7 support.',
                    'estimate_note' => 'Indicative “from” pricing — live plans and exact prices appear here the moment our catalogue is connected.',
                ],
            ],
            'download' => [
                'hero' => [
                    'headline' => 'Get the {app} app',
                    'subtext' => 'Stay connected on the go — your data plans, numbers and wallet in one native app.',
                    'android_title' => 'Android',
                    'apk_button' => 'Download APK',
                    'apk_note' => 'Direct install · signed build',
                    'play_label' => 'Get it on Google Play',
                    'android_soon' => 'The Android app is on its way — check back soon.',
                    'ios_title' => 'iPhone & iPad',
                    'ios_label' => 'Download on the App Store',
                    'ios_soon' => 'The iOS app will be available on the App Store soon.',
                    'qr_caption' => 'Scan to open this page on your phone',
                ],
            ],
            'about' => [
                'hero' => [
                    'stat_card_label' => 'Countries covered',
                    'stat_card_value' => '190+',
                    'eyebrow' => 'Our Story',
                    'headline' => 'We Built the eSIM We Wished Existed',
                    'subtext' => "NaaraSim didn't start in a Silicon Valley boardroom. It started with a missed meeting, a dead phone, and a $40 airport SIM card that barely worked. There had to be a better way.",
                    // About-hero expansion (owner request, 2026-09-21) — the same
                    // AgriTech-reference split-card layout (light card, left copy
                    // + right image with floating badges) adapted with real Naara
                    // "Our Story" hero copy (from the owner-supplied About Us
                    // draft). Distinct from the existing WebGL-backdrop hero above
                    // (which still renders when `image` is set) — these fields
                    // only apply to the NEW card layout in about.blade.php.
                    'cta_primary' => 'Get Connected',
                    'cta_secondary' => 'Read the FAQ',
                    'stat_1' => '190+ Countries', 'stat_2' => '<3 Min to connect', 'stat_3' => '1 Wallet', 'stat_4' => '$0 Hidden fees',
                    'badge_label' => 'Join happy Naara customers',
                    'hero_image' => '/images/marketing/about/about-hero-traveller.webp',
                    // A real screen from the app floating over the hero photo (a skin screenshot; swap it from the Site Editor).
                    'phone_image' => '/images/marketing/skins/surface/esim.webp',
                    'phone_alt' => 'The Naara eSIM store on a phone',
                ],
                'story' => [
                    'headline' => 'The Full Story',
                    'p1' => 'Every African who travels internationally knows the ritual. You land in a new country. Before you can breathe, you\'re calculating — which SIM vendor is least likely to scam you, which network covers your hotel, how much data is enough for a week. Connectivity, which should be invisible, becomes the first problem you solve.',
                    'p2' => 'NaaraSim was founded to end that ritual. We\'re a product of Supreme Ideas Agency — a brand and digital technology company based in Onitsha, Nigeria. When we started looking for an eSIM product that genuinely served African travelers, we found plenty of options built for European backpackers and American business travelers. Nothing that started from our reality. So we built NaaraSim.',
                    'p3' => '"Naara" means dawn in several West African traditions — the moment a new signal rises, the moment clarity breaks through. That\'s what NaaraSim is: your first clear signal, wherever you land.',
                    'p4' => 'We\'re not trying to be the biggest eSIM company in the world. We\'re trying to be the best one for our people — Africans traveling for business, for family, for opportunity, for joy. NaaraSim runs on premium global infrastructure covering 190+ countries, with a setup experience obsessed-over so it takes under three minutes.',
                ],
                // The product behind the story: the real app on a desktop and three phones (owner request 2026-10-06, About page mockups).
                'tour' => [
                    'eyebrow' => 'The product behind the story',
                    'headline' => 'Built to be used, not just talked about',
                    'subtext' => 'Everything above is in the app you can open today. Here is what it looks like.',
                    't1_title' => 'Plans in minutes',
                    't1_text' => 'Pick a country, pick a plan, install the eSIM. The whole journey is a few taps.',
                    't2_title' => 'One wallet',
                    't2_text' => 'Top up once and use the same wallet for data, numbers and gift cards.',
                    't3_title' => 'Numbers when you need them',
                    't3_text' => 'Verification numbers, rentals and a permanent line, matched to the country.',
                    't4_title' => 'Your look, your way',
                    't4_text' => 'Choose a skin and an accent, in light or dark, and the whole app follows.',
                    'desk_image' => '/images/marketing/mockups/home/features-desktop.webp',
                    'desk_alt' => 'The Naara dashboard on a desktop',
                    'p1_image' => '/images/marketing/skins/surface/esim.webp',
                    'p2_image' => '/images/marketing/skins/surface/wallet.webp',
                    'p3_image' => '/images/marketing/skins/surface/numbers.webp',
                    'phones_alt' => 'The eSIM store, the wallet and the numbers screen on three phones',
                ],
                'mission' => [
                    'headline' => "What We're Here to Do",
                    'mission_title' => 'Our Mission',
                    'mission' => 'To make global connectivity instant, affordable, and effortless for every African crossing a border — so they can focus on the journey, not the logistics of staying online.',
                    'vision_title' => 'Our Vision',
                    'vision' => 'A continent of travelers who move through the world as fluidly as their ambitions carry them. No connectivity barriers. No roaming anxiety. Just Africans showing up fully, everywhere they go.',
                    'mission_slide_title' => 'Why we exist',
                    'vision_slide_title' => 'Where we are headed',
                    'essence_eyebrow' => 'Essence',
                    'essence_title' => 'Naara means dawn',
                    'belief' => "Connectivity is not a luxury. It's infrastructure. And every traveler — regardless of where they come from — deserves to land somewhere new and simply be online.",
                    // One real photo per slide (owner-supplied airport set, 2026-10-06); swap any of them from the Site Editor.
                    'mission_image' => '/images/marketing/about/mission-departures.webp',
                    'vision_image' => '/images/marketing/about/vision-unbound.webp',
                    'essence_image' => '/images/marketing/about/essence-roam-free.webp',
                ],
                'values' => [
                    'headline' => 'What We Stand For',
                    'v1_title' => 'Clarity',
                    'v1_text' => 'We write every plan description, every support message, and every piece of our interface so that a first-time eSIM user understands exactly what they\'re getting. No telecom jargon. No hidden fees.',
                    'v2_title' => 'Speed',
                    'v2_text' => 'Your time is your most valuable resource. NaaraSim is designed so that from the moment you decide to buy to the moment you\'re connected takes less time than it takes to order a coffee.',
                    'v3_title' => 'Belonging',
                    'v3_text' => 'We built this for African travelers first. Our support understands your travel patterns, our pricing respects your budget reality, and our coverage prioritizes the corridors African professionals actually use.',
                    'v4_title' => 'Reliability',
                    'v4_text' => "When you're in a foreign city trying to close a deal, a dropped connection isn't just inconvenient — it's costly. NaaraSim runs on infrastructure that prioritizes uptime above everything.",
                ],
                'team' => [
                    'headline' => 'The People Behind NaaraSim',
                    'founder_name' => 'Frank Charles Ebubedike',
                    'founder_title' => 'Founder & CEO, NaaraSim · CEO, Supreme Ideas Agency',
                    'founder_bio' => "Frank is a brand strategist and digital product builder who has spent years helping businesses across Africa build meaningful presences online. NaaraSim is his answer to a problem he's experienced personally: the unnecessary friction of staying connected while traveling as an African professional. He built the product he couldn't find — designed for the traveler he knows best.",
                    'company' => 'NaaraSim is a product of Supreme Ideas Agency — a brand and digital strategy company based in Onitsha, Nigeria, building purposeful digital products for businesses across Africa and globally.',
                ],
                // About-page expansion (owner request, 2026-09-21) — real copy
                // seeded verbatim from the owner-supplied About Us content
                // draft, extracted alongside the corrupted markup it originally
                // shipped in. Each `*_image` field follows the same
                // admin-image-upload convention as `how.step_1_image` above.
                'who' => [
                    'headline' => 'Built for people on the move. And the people who serve them.',
                    'subtext' => "We don't chase a demographic. We build for the people who need to stay reachable. Here is who Naara is made for.",
                    'w1_tag' => 'The traveller',
                    'w1_title' => 'Land Connected, Every Time',
                    'w1_text' => 'Business trips, holidays, family visits. Buy an eSIM before you fly, scan once, and switch on when you land. No airport SIM queues, no roaming bill shock, and your usual number stays active for messages.',
                    'w1_image' => '/images/about/who-traveller.webp',
                    'w2_tag' => 'The verified',
                    'w2_title' => 'Accounts Without Exposing Your Number',
                    'w2_text' => 'Sign up for services with a temporary number, or keep a permanent Naara Line for calls and texts. If a code never arrives, your wallet refunds itself automatically.',
                    'w2_image' => '/images/about/who-verified.webp',
                    'w3_tag' => 'The builders',
                    'w3_title' => 'Merchants, Partners & Developers',
                    'w3_text' => 'Resell Naara under your own brand, earn as a partner, or plug the Naara API into your own product. One platform, with dashboards, invoices and documentation ready.',
                    'w3_image' => '/images/about/who-builders.webp',
                ],
                'industries' => [
                    'headline' => 'Wherever a border shows up in your life',
                    'i1_name' => 'Travel & Tourism', 'i1_text' => 'Land with data already working, in 190+ countries, without hunting for a SIM.',
                    'i2_name' => 'Diaspora & Family', 'i2_text' => 'Keep a real number and stay reachable at home while living or visiting abroad.',
                    'i3_name' => 'Remote Work', 'i3_text' => 'Reliable data in every city, so the next meeting never depends on café Wi-Fi.',
                    'i4_name' => 'Online Accounts', 'i4_text' => 'Verification numbers for sign-ups, with automatic refunds when a code never comes.',
                    'i5_name' => 'Business Lines', 'i5_text' => 'A permanent Naara Line with forwarding and voicemail for your team or shop.',
                    'i6_name' => 'Gifting', 'i6_text' => 'Buy digital gift cards from your wallet and deliver them in minutes.',
                    'i7_name' => 'Merchants & Resellers', 'i7_text' => 'Sell Naara under your own brand, with clients, earnings and invoices in one dashboard.',
                    'i8_name' => 'Developers & API', 'i8_text' => 'Build eSIM and number ordering into your own product with the Naara developer API.',
                ],
                'numbers' => [
                    'tag_1' => 'Pan-African',
                    'tag_2' => 'No Borders',
                    'tag_3' => 'No Swaps',
                    'tag_4' => 'One Wallet',
                    'tag_5' => 'Instant eSIM',
                    'tag_6' => 'Auto-Refund',
                    'tag_7' => 'Fair Pricing',
                    'tag_8' => '190+ Countries',
                    'tag_9' => 'Verification Numbers',
                    'tag_10' => 'Naara Line',
                    'tag_11' => 'Secure by Design',
                    'tag_12' => 'Built in Africa',
                    'eyebrow' => 'The Naara Promise',
                    'headline' => 'What we stand for, in a word or two',
                ],
                'process' => [
                    'eyebrow' => 'How it works',
                    'headline' => 'Six steps. Zero surprises.',
                    // Optional wide trio above the cards. Off by default now that each step card carries its own phone screen.
                    'journey_image' => '',
                    'subtext' => 'Every order follows the same clear path, from sign-up to support. That is why Naara feels simple even when the network is not.',
                    'step_1_title' => 'Create Your Account', 'step_1_text' => 'Sign up free in a minute. One login covers eSIMs, numbers, gift cards, rewards and your wallet.',
                    'step_1_image' => '/images/marketing/mockups/about/step-1-account.webp', 'step_1_alt' => 'The Naara sign-up screen on a phone',
                    'step_2_title' => 'Fund Your Wallet', 'step_2_text' => 'Top up by card or bank transfer, or mobile money where available. One balance, shown in USD and NGN.',
                    'step_2_image' => '/images/marketing/mockups/how-it-works/step-7-top-up.webp', 'step_2_alt' => 'The Naara wallet top-up screen on a phone',
                    'step_3_title' => 'Choose Your Plan', 'step_3_text' => 'Pick a destination and a data plan, or order a number. The full price shows before you pay, with $0.00 taxes and fees.',
                    'step_3_image' => '/images/marketing/mockups/how-it-works/step-2-choose-plan.webp', 'step_3_alt' => 'A list of eSIM data plans for Türkiye on a phone',
                    'step_4_title' => 'Install or Receive', 'step_4_text' => 'Scan the QR code to install your eSIM, or receive your verification code in the app. It takes about a minute.',
                    'step_4_image' => '/images/marketing/mockups/how-it-works/step-4-install.webp', 'step_4_alt' => 'The eSIM install QR code on a phone',
                    'step_5_title' => 'Connect', 'step_5_text' => 'Switch on your eSIM when you land. Data starts when you first connect, and your usual number stays yours.',
                    'step_5_image' => '/images/marketing/mockups/how-it-works/step-6-connect.webp', 'step_5_alt' => 'The Naara dashboard showing an active eSIM on a phone',
                    'step_6_title' => 'Support & Refunds', 'step_6_text' => 'Unstarted eSIMs are refundable, and numbers with no code refund automatically. NaaraCare and Nia are one tap away.',
                    'step_6_image' => '/images/marketing/mockups/about/step-6-support.webp', 'step_6_alt' => 'The NaaraCare support chat with Nia on a phone',
                ],
                'culture' => [
                    'headline' => 'How we operate',
                    'c1_name' => 'Built in Africa, for the World', 'c1_text' => 'Naara started in Onitsha and is built for how Africa actually pays, travels and connects, then extended to everyone else.',
                    'c2_name' => 'Support Is a Feature', 'c2_text' => 'NaaraCare and our assistant Nia answer fast, inside the app. Voice notes and screenshots are welcome. You should never have to hunt for help.',
                    'c3_name' => 'We Ship, Listen, Repeat', 'c3_text' => 'Feedback shapes the roadmap. When customers tell us where the experience is rough, we fix it and say so.',
                ],
                'global' => [
                    'headline' => 'Not limited by a border.',
                    'body' => 'Naara eSIM plans cover 190+ countries, with regional bundles for multi-country trips. Our roots are in Nigeria, and our customers are wherever they need to be reachable.',
                    'cta' => 'Get connected',
                ],
                'closing_cta' => [
                    'label' => 'Ready to connect',
                    'headline' => 'Your next trip deserves better than roaming bills.',
                    'subtext' => "Set up in minutes, pay from one wallet, and get refunded automatically when we miss. Let's get you connected.",
                    'cta_primary' => 'Get Naara',
                    'cta_secondary' => 'Talk to NaaraCare',
                ],
            ],
            'how-it-works' => [
                'hero' => [
                    'headline' => 'From Purchase to Connected in Under 3 Minutes',
                    'subtext' => "NaaraSim is built to be simple. Here's exactly how it works — no technical knowledge required.",
                    'proof' => 'No store visits · No physical SIM · No roaming setup · Works on most modern smartphones',
                ],
                'steps' => [
                    'headline' => 'The Full Step-by-Step',
                    's1_title' => 'Check Your Device',
                    's1_text' => 'Before purchasing, confirm your phone supports eSIM and isn\'t carrier-locked. Most phones made after 2019 are eSIM-ready. Use our compatibility checker or look for "eSIM" in your phone\'s settings under Mobile Data.',
                    's1_image' => '/images/marketing/mockups/how-it-works/step-1-check-device.webp',
                    's2_title' => 'Choose Your Plan',
                    's2_text' => 'Browse plans by destination — local (one country), regional (a group of countries), or global. Pick the data size and duration that matches your trip. Plans start from 1GB.',
                    's2_image' => '/images/marketing/mockups/how-it-works/step-2-choose-plan.webp',
                    's3_title' => 'Complete Your Purchase',
                    's3_text' => 'Pay securely using your card or mobile payment method. Your eSIM QR code and activation instructions are sent to your email immediately after payment. No waiting.',
                    's3_image' => '/images/marketing/mockups/how-it-works/step-3-purchase.webp',
                    's4_title' => 'Install Before You Travel',
                    's4_text' => 'Scan the QR code from your email or go to Settings → Mobile Data → Add eSIM. The installation takes about 90 seconds. Do this while you still have Wi-Fi or home network access.',
                    's4_image' => '/images/marketing/mockups/how-it-works/step-4-install.webp',
                    's5_title' => 'Set NaaraSim as Your Data SIM',
                    's5_text' => 'After installation, set NaaraSim as your preferred data SIM. Keep your home SIM active for calls and texts.',
                    's5_image' => '/images/marketing/mockups/how-it-works/step-5-data-sim.webp',
                    's6_title' => 'Land and Connect',
                    's6_text' => 'When you arrive, your phone automatically connects to a local network. Turn off data roaming on your home SIM to avoid carrier charges. NaaraSim handles everything else.',
                    's6_image' => '/images/marketing/mockups/how-it-works/step-6-connect.webp',
                    's7_title' => 'Top Up If Needed',
                    's7_text' => 'Running low on data? Log in to your NaaraSim account and add more data to your existing plan without reinstalling or rescanning anything.',
                    's7_image' => '/images/marketing/mockups/how-it-works/step-7-top-up.webp',
                ],
                'compatibility' => [
                    'headline' => 'Does Your Phone Support NaaraSim?',
                    'intro' => "NaaraSim works on any eSIM-compatible device that isn't carrier-locked. Search the live checker below, or ask our support — we'll confirm in minutes.",
                    'note' => "Your device must not be carrier-locked, and some regional phone variants don't include eSIM hardware.",
                    'cta' => 'Check My Device',
                ],
                'support' => [
                    'cta' => 'Contact Support',
                    'headline' => "We're Here If You Need Us",
                    'text' => "Most NaaraSim setups go smoothly without any help. But travel is unpredictable, and we know that. If something doesn't work — a device issue, a network problem, or a simple question — our support team responds within minutes, not hours.",
                    'promise' => 'We don\'t close support tickets until your issue is actually resolved. Not just "responded to." Resolved.',
                ],
            ],
            'contact' => [
                'hero' => [
                    'headline' => 'We Actually Respond',
                    'subtext' => "Got a question before you buy? Need help with your setup? We're here. Real support from a real team — not a chatbot running in circles.",
                    'promise' => 'Average response time: under 2 hours',
                ],
                'form' => [
                    'headline' => 'Send Us a Message',
                    'success' => "We got your message. Someone from our team will respond within 2 hours. Check your spam folder if you don't hear from us — we'll be there.",
                    'disclaimer' => "We don't share your information. Ever.",
                    'sent_title' => 'Message sent',
                    'label_name' => 'Full Name', 'ph_name' => 'What should we call you?',
                    'label_email' => 'Email Address', 'ph_email' => 'Where should we reply?',
                    'label_subject' => "What's this about?",
                    'subjects' => "Before I buy\nSetup help\nBilling question\nTechnical issue\nPartnership\nSomething else",
                    'label_message' => 'Your Message', 'ph_message' => "Tell us what's going on. The more detail you give us, the faster we can help.",
                    'button' => 'Send Message',
                ],
                'channels' => [
                    'chat_cta' => 'Open the chat',
                    'wa_title' => 'WhatsApp',
                    'wa_text' => 'Message us directly for quick questions.',
                    'wa_cta' => 'Chat on WhatsApp',
                    'headline' => 'Other Ways to Reach Us',
                    'chat_title' => 'Chat With Us',
                    'chat_text' => 'The fastest way to get help. Signed-in customers get our assistant plus a real human hand-off, right from the Help & Support menu.',
                    'email_title' => 'Email Support',
                    'email_text' => 'For detailed questions, installation screenshots, or billing issues. We respond within 2 hours during business hours.',
                    'partners' => 'Interested in integrating NaaraSim into your platform? Email partners via the address below — NaaraSim is a product of Supreme Ideas Agency.',
                ],
            ],
        ];

        // Master-only sales pages (the extra "What Naara Gives You" sections, the Naara Chat page, the White Label page): present only where their
        // copy classes exist, so a white-label install never carries them and the Site Editor never lists them there.
        if (class_exists(\App\Support\MasterMarketing\ProductsContent::class)) {
            $pages['products'] = \App\Support\MasterMarketing\ProductsContent::defaults();
            $pages['naara-chat'] = \App\Support\MasterMarketing\ChatContent::defaults();
            $pages['naara-brain'] = \App\Support\MasterMarketing\BrainContent::defaults();
            $pages['naara-gift'] = \App\Support\MasterMarketing\GiftContent::defaults();
            $pages['white-label'] = \App\Support\MasterMarketing\WhiteLabelContent::defaults();
            // The infrastructure statement on About sits right after the story, before the product tour (master only).
            if (class_exists(\App\Support\MasterMarketing\AboutInfrastructureContent::class) && isset($pages['about']['story'])) {
                $about = [];
                foreach ($pages['about'] as $k => $v) {
                    $about[$k] = $v;
                    if ($k === 'story') {
                        $about['infrastructure'] = \App\Support\MasterMarketing\AboutInfrastructureContent::defaults();
                    }
                }
                $pages['about'] = $about;
            }
            // The header menu entries for those pages (labels are editable like every other menu label).
            $pages['navigation']['menu'] += [
                'chat_label' => 'Naara Chat', 'chat_desc' => 'Message and call on Naara',
                'brain_label' => 'NaaraBrain', 'brain_desc' => 'Ask once, move on',
                'wl_label' => 'White Label', 'wl_desc' => 'Launch your own Naara',
            ];
        }

        // NaaraBusiness Suite landing (master-only): present only where the Suite's own class exists, so a white-label install never carries its copy.
        if (class_exists(\App\Support\Business\BusinessLandingContent::class)) {
            $pages['business-suite'] = \App\Support\Business\BusinessLandingContent::defaults();
        }

        return $pages;
    }

    /**
     * Page content merged with admin overrides, ordered, and (for the public
     * site) filtered to visible sections.
     *
     * @return array<string, array<string, mixed>> section => fields (+ image/visible/order)
     */
    public static function page(string $page, bool $includeHidden = false, bool $translate = true): array
    {
        $defaults = self::defaults()[$page] ?? [];
        $overrides = self::overrides($page);
        // Language pack (2026-10-03): shipped defaults render in the visitor's language; any field the admin has customised stays exactly as written.
        $flat = $translate ? self::translations(app()->getLocale()) : [];
        // Copy the admin edited in the Site Editor is translated per language by TranslateSiteCopyJob (or typed in by hand later);
        // a stored translation is used only while it still matches the English text it was made from.
        $tr = ($translate && app()->getLocale() !== 'en') ? (self::adminTranslations($page)[app()->getLocale()] ?? []) : [];

        $sections = [];
        $position = 0;
        foreach ($defaults as $key => $fields) {
            $over = $overrides[$key] ?? [];
            if ($flat !== []) {
                // An override identical to the shipped English default (seeded content, or an admin who saved without editing) is not a
                // customisation: translate it. Anything the admin actually changed stays exactly as written.
                $over = array_filter($over, fn ($v, $f) => ! (is_string($v) && array_key_exists($f, $fields) && $fields[$f] === $v), ARRAY_FILTER_USE_BOTH);
                $fields = self::overlay($fields, $over, $flat, $page.'.'.$key.'.');
            }
            $fields['radius_top'] ??= '30'; // px; top-left/top-right corner radius — matches the global main>section default (Phase C)
            $merged = array_merge($fields, array_filter($over, fn ($v) => $v !== null && $v !== ''));
            if ($tr !== [] && isset($tr[$key])) {
                $merged = self::applyAdminTranslation($merged, $over, $tr[$key]);
            }
            $merged['visible'] = array_key_exists('visible', $over) ? (bool) $over['visible'] : true;
            $merged['order'] = array_key_exists('order', $over) ? (int) $over['order'] : $position;
            $merged['image'] = $over['image'] ?? '';
            $sections[$key] = $merged;
            $position++;
        }

        // Copied-in portable sections: a portable section reused on this page
        // lives only in this page's overrides (it has no default here). Rebuild
        // it from its canonical portable defaults + the stored override so it
        // renders and edits like a native section. (Reused sections feature.)
        foreach ($overrides as $key => $over) {
            if (isset($sections[$key]) || ! in_array($key, self::PORTABLE_SECTIONS, true)) {
                continue;
            }
            $base = self::portableDefaults($key);
            if ($base === []) {
                continue;
            }
            $merged = array_merge($base, array_filter($over, fn ($v) => $v !== null && $v !== ''));
            if ($tr !== [] && isset($tr[$key])) {
                $merged = self::applyAdminTranslation($merged, $over, $tr[$key]);
            }
            $merged['visible'] = array_key_exists('visible', $over) ? (bool) $over['visible'] : true;
            $merged['order'] = array_key_exists('order', $over) ? (int) $over['order'] : $position++;
            $merged['image'] = $over['image'] ?? '';
            $merged['_copied'] = true; // marks a reused section for the editor UI
            $sections[$key] = $merged;
        }

        uasort($sections, fn ($a, $b) => $a['order'] <=> $b['order']);

        if (! $includeHidden) {
            $sections = array_filter($sections, fn ($s) => $s['visible']);
        }

        return $sections;
    }

    /**
     * Flat "page.section.field[.n.field]" => translated string map for a locale
     * (lang/{locale}/site.php); empty for English or a locale without a file.
     *
     * @return array<string, string>
     */
    public static function translations(string $locale): array
    {
        static $cache = [];
        if ($locale === 'en') {
            return [];
        }
        if (! isset($cache[$locale])) {
            $file = lang_path($locale.'/site.php');
            $cache[$locale] = is_file($file) ? (array) require $file : [];
        }

        return $cache[$locale];
    }

    /** Replace default leaf strings with their translation, skipping any top-level field the admin overrode. */
    private static function overlay(array $fields, array $over, array $flat, string $prefix): array
    {
        foreach ($fields as $f => $v) {
            if ($prefix !== '' && substr_count($prefix, '.') <= 2 && isset($over[$f]) && $over[$f] !== '' && $over[$f] !== null) {
                continue;
            }
            $k = $prefix.$f;
            if (is_array($v)) {
                $fields[$f] = self::overlay($v, [], $flat, $k.'.');
            } elseif (is_string($v) && isset($flat[$k])) {
                $fields[$f] = $flat[$k];
            }
        }

        return $fields;
    }

    /** Fingerprint of an English admin edit; a stored translation is only valid while it matches. */
    public static function fingerprint(mixed $value): string
    {
        return md5((string) json_encode($value, JSON_UNESCAPED_UNICODE));
    }

    /** Swap in the stored translation for every field the admin edited whose English text has not changed since it was translated. */
    private static function applyAdminTranslation(array $merged, array $over, array $fieldTr): array
    {
        foreach ($fieldTr as $field => $entry) {
            if (! isset($over[$field]) || $over[$field] === '' || ! is_array($entry) || ! isset($entry['v'], $entry['h'])) {
                continue;
            }
            if (hash_equals((string) $entry['h'], self::fingerprint($over[$field])) && $entry['v'] !== '' && $entry['v'] !== []) {
                $merged[$field] = $entry['v'];
            }
        }

        return $merged;
    }

    /**
     * Admin-edited copy translated per language: locale => section => field => ['v' => translated value, 'h' => fingerprint of the English edit].
     *
     * @return array<string, array<string, array<string, array{v: mixed, h: string}>>>
     */
    public static function adminTranslations(string $page): array
    {
        try {
            return Cache::rememberForever('site.tr.'.$page, function () use ($page) {
                $stored = Setting::getValue('site.tr.'.$page, []);

                return is_array($stored) ? $stored : [];
            });
        } catch (\Throwable) {
            return [];
        }
    }

    public static function saveAdminTranslations(string $page, array $translations): void
    {
        Setting::setValue('site.tr.'.$page, $translations, 'site', 'Translations of admin-edited marketing copy.');
        Cache::forget('site.tr.'.$page);
    }

    /** @return array<string, array<string, mixed>> */
    public static function overrides(string $page): array
    {
        try {
            return Cache::rememberForever(self::cacheKey($page), function () use ($page) {
                $stored = Setting::getValue('site.page.'.$page, []);

                return is_array($stored) ? $stored : [];
            });
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param array<string, array<string, mixed>> $overrides */
    public static function saveOverrides(string $page, array $overrides): void
    {
        Setting::setValue('site.page.'.$page, $overrides, 'site', 'Marketing page overrides.');
        self::flush($page);
    }

    /**
     * Copy a portable section onto another page (reusable sections feature). The
     * section's current field values are snapshotted into the target page's
     * overrides under the same key, made visible, and appended to the end. Only
     * portable sections can be copied — a page-bespoke section has no shared
     * partial to render through elsewhere.
     *
     * @param  array<string, mixed>  $fields  the section's field values (no reserved keys)
     */
    public static function copySection(string $key, array $fields, string $toPage, string $image = ''): bool
    {
        if (! in_array($key, self::PORTABLE_SECTIONS, true) || ! in_array($toPage, self::PAGES, true)) {
            return false;
        }

        $overrides = self::overrides($toPage);

        // Append after everything currently on the target page.
        $maxOrder = -1;
        foreach ($overrides as $o) {
            $maxOrder = max($maxOrder, (int) ($o['order'] ?? 0));
        }
        foreach (self::page($toPage, includeHidden: true) as $s) {
            $maxOrder = max($maxOrder, (int) ($s['order'] ?? 0));
        }

        // Strip reserved/marker keys from the snapshot; store just the content.
        $clean = array_diff_key($fields, array_flip(['visible', 'order', 'image', '_copied']));

        $overrides[$key] = $clean + [
            'visible' => true,
            'order' => $maxOrder + 1,
            'image' => $image,
        ];

        self::saveOverrides($toPage, $overrides);

        return true;
    }

    public static function flush(?string $page = null): void
    {
        foreach ($page ? [$page] : self::PAGES as $p) {
            Cache::forget(self::cacheKey($p));
            Cache::forget('site.tr.'.$p);
        }
    }

    public static function isSiteKey(string $key): bool
    {
        return str_starts_with($key, 'site.');
    }

    /**
     * Resolve a section/step image value to a servable URL. Shipped defaults are
     * relative public paths (/images/...); admin overrides are absolute Wasabi
     * (or /storage) URLs. Empty stays empty so callers can hide the slot.
     */
    public static function imageUrl(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return preg_match('#^(https?:)?//#', $value) === 1 ? $value : asset($value);
    }

    private static function cacheKey(string $page): string
    {
        return 'site.page.'.$page;
    }
}
