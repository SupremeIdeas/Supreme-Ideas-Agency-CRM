<?php

namespace App\Support\MasterMarketing;

/**
 * MASTER-ONLY. The shipped copy of the White Label sales page (`/white-label`), as Site Editor page `white-label`. The page is switched on or off
 * by the admin (PageSwitch) after it is built. Plan names and prices are NOT in here: they come live from the license plan catalogue
 * (Admin > White Label), so a price change there shows here automatically. The agency quote range and every sentence below are editable in
 * Admin > Marketing site > White Label page; the structure lives in resources/views/marketing/master-marketing/white-label.blade.php.
 */
final class WhiteLabelContent
{
    /** Skins in the portfolio: key => [name, line, card tint]. Four screens each: images/marketing/skins/{key}/{home,esim,wallet,numbers}.webp. The tint is a design colour, not copy. */
    public const SKINS = [
        'surface' => ['Naara Surface', 'The default: layered depth and one bold action card.', '#0A9B94'],
        'noir' => ['Noir', 'True black for night owls, with the accent only where it counts.', '#2DD4BF'],
        'glass' => ['Glass', 'Frosted layers floating over a soft colour mesh.', '#38BDF8'],
        'paper' => ['Paper', 'Warm editorial paper with serif headings.', '#FB7185'],
        'neo' => ['Neo', 'Bold outlines and hard offset shadows, unmistakably confident.', '#A78BFA'],
        'aurora' => ['Aurora', 'Glow, orbit rings and a signature motif on every card.', '#8B5CF6'],
        'ankara' => ['Ankara', 'A bold woven-print band across every card.', '#34D399'],
        'vault' => ['Vault', 'Black and champagne foil with engraved lines.', '#D4A017'],
    ];

    /** The nine screens of the hero wall: [skin, screen]. Cell n shows images/marketing/skins/{skin}/{screen}.webp unless the admin replaces it. */
    public const WALL = [1 => ['surface', 'home'], 2 => ['noir', 'wallet'], 3 => ['glass', 'esim'], 4 => ['paper', 'numbers'], 5 => ['neo', 'home'], 6 => ['aurora', 'wallet'], 7 => ['ankara', 'esim'], 8 => ['vault', 'numbers'], 9 => ['surface', 'wallet']];

    /** The four screens captured per skin: key => label. */
    public const SCREENS = [
        'home' => 'Home',
        'esim' => 'eSIM catalogue',
        'wallet' => 'Wallet',
        'numbers' => 'Numbers',
    ];

    /** Plan columns of the comparison table, in order: license plan key => column key used in the row fields. */
    public const PLAN_KEYS = ['basic', 'medium', 'extended', 'extended_v2'];

    /** Icons are code, not copy (they must be names in the icon sprite). */
    public const KIT_ICONS = [1 => 'layers', 2 => 'sim', 3 => 'hash', 4 => 'wallet', 5 => 'palette', 6 => 'settings', 7 => 'edit', 8 => 'refresh', 9 => 'headset'];

    /** @return array<string, array<string, string>> */
    public static function defaults(): array
    {
        $kit = [
            'eyebrow' => 'What you get',
            'headline' => 'A complete connectivity business, ready on day one',
            'subtext' => 'You are not getting a template. You are getting a working platform with the parts that took us years to get right.',
        ];
        $cards = [
            1 => ['layers', 'Your own branded platform', 'Your name, your logo, your colours and your domain, on your own install.'],
            2 => ['sim', 'eSIM data for 190+ countries', 'Plans sold under your brand, with failover between providers built in.'],
            3 => ['hash', 'Virtual and verification numbers', 'One-time verification, rentals and permanent numbers, matched to the country.'],
            4 => ['wallet', 'Wallet, credits and payments', 'A single wallet, NaaraCredit rewards and the payment gateways you choose.'],
            5 => ['palette', '35 dashboard skins', 'Your members can restyle their whole dashboard, with accents, dials and dark mode.'],
            6 => ['settings', 'A full admin panel', 'Pricing, products, users, support and more, on an admin path only you know.'],
            7 => ['edit', 'A marketing site you can edit', 'Change the words and swap the images yourself, any time, with no developer.'],
            8 => ['refresh', 'Updates through the updater', 'New features and fixes arrive through the built-in updater, safely and with a rollback.'],
            9 => ['headset', 'Support from the people who built it', 'Standard support on every plan, and priority support on Extended V2.'],
        ];
        foreach ($cards as $n => [$icon, $title, $text]) {
            $kit["k{$n}_title"] = $title;
            $kit["k{$n}_text"] = $text;
        }

        $showcase = [
            'eyebrow' => 'The portfolio',
            'headline' => 'One platform. Eight identities, and 35 to choose from.',
            'subtext' => 'Every skin restyles the whole app, not only the colours. Open any of them to see the four screens your members use most, captured from the live platform.',
            'feat_eyebrow' => 'Featured',
            'feat_title' => 'Your admin and your members, side by side',
            'feat_text' => 'The same platform runs the storefront your members see and the admin panel you run it from. Here it is on a desktop, in the default Naara Surface skin.',
            'feat_b1' => 'Real screens, not mock-ups',
            'feat_b2' => 'Your name, logo and colours on every one',
            'feat_b3' => 'Members can restyle it themselves',
            'feat_image' => '/images/marketing/mockups/white-label/hero-desktop.webp',
            'feat_alt' => 'A white-label dashboard on a desktop screen',
            'view_label' => 'See the four screens',
            'close_label' => 'Close',
            'prev_label' => 'Previous skin',
            'next_label' => 'Next skin',
            'footnote' => 'These are real screens from the live platform, captured in each skin. Your members choose their own skin and accent from Appearance.',
        ];
        foreach (self::SKINS as $key => [$name, $line, $tint]) {
            $showcase["sk_{$key}_name"] = $name;
            $showcase["sk_{$key}_text"] = $line;
            foreach (self::SCREENS as $screen => $label) {
                $showcase["sk_{$key}_{$screen}_image"] = "/images/marketing/skins/{$key}/{$screen}.webp";
            }
        }
        foreach (self::SCREENS as $screen => $label) {
            $showcase["screen_{$screen}"] = $label;
        }

        $compare = [
            'eyebrow' => 'Licenses',
            'headline' => 'Pick the license that fits where you are',
            'subtext' => 'Every license gives you your own white-label platform. The difference is how much is unlocked on day one, and how much help you get.',
            'feature_label' => 'What is included',
            'price_label' => 'One-time price',
            'balance_label' => 'Balance to reach Extended',
            'balance_none' => 'Already Extended',
            'recommended_label' => 'Most popular',
            'cta_label' => 'Choose this license',
            'yes_label' => 'Included',
            'no_label' => 'Not included',
            'plan_basic_name' => 'Basic',
            'plan_medium_name' => 'Medium',
            'plan_extended_name' => 'Extended',
            'plan_extended_v2_name' => 'Extended V2',
            'no_plans' => 'License prices are shared on request. Talk to us and we will send the current list.',
            'note' => 'Basic and Medium upgrade to Extended by paying the remaining balance, and the locked features open on their own. Extended and Extended V2 have the same capability; V2 adds priority support from Supreme Ideas Agency.',
        ];
        // [label, basic, medium, extended, extended_v2]. Yes / No render as tick and dash; any other value renders as text.
        $rows = [
            1 => ['Your own branded platform, domain and admin panel', 'Yes', 'Yes', 'Yes', 'Yes'],
            2 => ['eSIM data plans for 190+ countries with provider failover', 'Yes', 'Yes', 'Yes', 'Yes'],
            3 => ['Virtual, rental and verification numbers', 'Yes', 'Yes', 'Yes', 'Yes'],
            4 => ['Wallet, NaaraCredits, payments and payouts', 'Yes', 'Yes', 'Yes', 'Yes'],
            5 => ['35 dashboard skins, accents and appearance dials', 'Yes', 'Yes', 'Yes', 'Yes'],
            6 => ['Marketing site you edit yourself, text and images', 'Yes', 'Yes', 'Yes', 'Yes'],
            7 => ['Updates through the built-in updater', 'Yes', 'Yes', 'Yes', 'Yes'],
            8 => ['Naara Gift: gift cards for the brands people love', 'No', 'No', 'Yes', 'Yes'],
            9 => ['Naara Connect: full voice eSIM with calls and SMS', 'No', 'No', 'Yes', 'Yes'],
            10 => ['Brand directory and Brand Hunt', 'No', 'No', 'Yes', 'Yes'],
            11 => ['Custom preloader', 'No', 'No', 'Yes', 'Yes'],
            12 => ['Support', 'Standard', 'Standard', 'Standard', 'Priority from Supreme Ideas Agency'],
            13 => ['Custom features and design work', 'Quoted', 'Quoted', 'Quoted', 'Quoted'],
        ];
        foreach ($rows as $n => [$label, $basic, $medium, $extended, $v2]) {
            $compare["r{$n}_label"] = $label;
            $compare["r{$n}_basic"] = $basic;
            $compare["r{$n}_medium"] = $medium;
            $compare["r{$n}_extended"] = $extended;
            $compare["r{$n}_extended_v2"] = $v2;
        }

        $wall = [];
        foreach (self::WALL as $n => [$skin, $screen]) {
            $wall["wall_{$n}_image"] = "/images/marketing/skins/{$skin}/{$screen}.webp";
        }

        $video = [
            'eyebrow' => 'See it in action',
            'headline' => 'Watch a white label go from install to first sale',
            'subtext' => 'A short walkthrough of the platform you would be running: the storefront your customers see, the admin panel you work from and how quickly it comes together.',
            'video_title' => 'The Naara white label walkthrough',
            'duration_label' => '',
            'play_label' => 'Play the video',
            'end_title' => 'Ready to launch your own Naara?',
            'end_cta' => 'See the licenses',
            'end_cta_href' => '#licenses',
            'replay_label' => 'Watch again',
            'transcript_label' => 'Read the transcript',
            'transcript' => '',
            'lbl_play' => 'Play',
            'lbl_pause' => 'Pause',
            'lbl_mute' => 'Mute',
            'lbl_unmute' => 'Unmute',
            'lbl_seek' => 'Seek',
            'lbl_speed' => 'Playback speed',
            'lbl_fullscreen' => 'Full screen',
            'lbl_exit' => 'Exit full screen',
            // Player controls (not copy). Source: `upload` plays video_file, `link` plays video_url (YouTube, Vimeo or a direct .mp4/.webm link).
            'video_source' => 'upload',
            'video_file' => '',
            'video_url' => '',
            'poster_image' => '',
            'player_color' => '',
            'opt_logo' => 'on',
            'opt_autoplay' => 'off',
            'opt_loop' => 'off',
        ];

        $engine = [
            'eyebrow' => 'How it fits together',
            'headline' => 'One powerful engine. Your brand on top.',
            'subtext' => 'The official Naara engine does the heavy lifting: providers, failover, wallet and payments. Your license carries it, with updates, into a platform that carries only your name.',
            'art_title' => 'The Naara engine, delivered under your own brand',
            'art_desc' => 'The official Naara engine, with eSIM data, numbers, payments and support, flows through your license and its safe updates into a platform with your name, your domain and your look, and you keep the margin.',
            'art_src' => 'Official Naara engine',
            'art_dst' => 'Your white label',
            'art_core_title' => 'Naara core',
            'art_core_sub' => 'Built from scratch',
            'art_n1' => 'eSIM data',
            'art_n2' => 'Numbers',
            'art_n3' => 'Payments',
            'art_n4' => 'Support',
            'art_flow' => 'Your license',
            'art_flow_sub' => 'Safe updates, with rollback',
            'art_d1' => 'Your name',
            'art_d2' => 'Your domain',
            'art_d3' => 'Your look',
            'art_out' => 'Your platform, your customers',
            'art_earn' => 'You keep the margin',
            'art_earn_sub' => 'Set your own prices',
            'p1_title' => 'Providers and failover',
            'p1_text' => 'eSIM and number providers are already wired in, with failover between them.',
            'p2_title' => 'Money, handled safely',
            'p2_text' => 'Wallet, credits and payment gateways come ready, built with money safety first.',
            'p3_title' => 'Updates that arrive for you',
            'p3_text' => 'New features reach your platform through the updater, with a rollback if you ever need it.',
        ];

        return [
            'hero' => [
                'eyebrow' => 'White Label',
                'headline' => 'Launch your own Naara.',
                'headline_accent' => 'Under your name, ready in days.',
                'subtext' => 'Get a connectivity platform that sells eSIM data and numbers across 190+ countries, with your brand on every screen. We set it up, hand you the keys, and you start selling.',
                'cta_primary' => 'See the licenses',
                'cta_primary_href' => '#licenses',
                'cta_secondary' => 'Talk to us',
                'cta_secondary_href' => '/contact',
                'chip_1' => 'Your brand, your domain',
                'chip_2' => 'eSIM and numbers',
                'chip_3' => '35 dashboard skins',
                'chip_4' => 'Edit it yourself',
                'stat_1_value' => '35',
                'stat_1_label' => 'dashboard skins',
                'stat_2_value' => '190+',
                'stat_2_label' => 'countries of eSIM data',
                'stat_3_value' => '4',
                'stat_3_label' => 'licenses to start from',
                'wall_alt' => 'Screens from a white-label platform in different skins',
            ] + $wall,
            'video' => $video,
            'reflection' => [
                'eyebrow' => 'How it is built',
                'headline' => 'The official Naara is custom built from scratch. Yours is a reflection of it.',
                'text_1' => 'Naara was designed and developed from the ground up, screen by screen, by Supreme Ideas Agency. Years of decisions about money safety, provider routing, privacy and design are in it.',
                'text_2' => 'Your white label is a faithful reflection of that platform, packaged so you can run your own business on it. It already carries everything it needs to start selling, and it keeps improving through updates.',
                'text_3' => 'If you want something beyond that reflection, such as features of the official platform that are not in your license or things nobody has built yet, that is real development work. We do it, and we quote it separately, below.',
                'pill_1' => 'Built from scratch',
                'pill_2' => 'Updated through the updater',
                'pill_3' => 'Yours to run',
                'shot_image' => '/images/marketing/mockups/white-label/reflection-trio.webp',
                'image_alt' => 'Three phones showing the same platform in three skins',
            ],
            'engine' => $engine,
            'kit' => $kit,
            'showcase' => $showcase,
            'compare' => $compare,
            'handover' => [
                'eyebrow' => 'Handing you the keys',
                'headline' => 'We set it up. You run it.',
                'text_1' => 'We install your white label, connect it to your domain and put your brand on it. Then we hand you the keys.',
                'text_2' => 'From there, the words and the images on your marketing site are yours to change. It is the easiest thing to do, and most owners are comfortable after a few minutes of playing around in the Marketing site editor.',
                'a_title' => 'You can change on your own',
                'a1' => 'Every headline and paragraph on your public pages',
                'a2' => 'Every image, from the hero to the footer',
                'a3' => 'Your blog posts, FAQ and legal pages',
                'a4' => 'Your colours, logo and skin defaults',
                'b_title' => 'Ask us for help with',
                'b1' => 'A custom preloader',
                'b2' => 'A custom-designed marketing site',
                'b3' => 'Marketing pages and landing pages written for you',
                'b4' => 'Anything beyond the license, as a quoted project',
                'help_note' => 'If you would like help with your marketing pages, we will quote it. As it stands, your white label already has all the content it needs for you to begin.',
                'shot_image' => '/images/marketing/mockups/white-label/handover-desktop.webp',
                'image_alt' => 'The Marketing site editor in the admin panel',
            ],
            'agency' => [
                'eyebrow' => 'Agency customisation',
                'headline' => 'Want more than the license? We build it.',
                'subtext' => 'Supreme Ideas Agency will design and develop custom features, flows and screens on top of your white label. Every request is scoped and quoted before we start, and nothing is built without your yes.',
                'from_label' => 'Custom work starts from',
                'from_value' => '$15,000',
                'to_label' => 'and goes up to',
                'to_value' => '$30,000',
                'range_note' => 'The final quote depends on what you ask for. Anything outside the current white label features is a separate project quote.',
                'a1_title' => 'Custom features',
                'a1_text' => 'A feature your market needs that the platform does not have yet.',
                'a2_title' => 'Custom flows and integrations',
                'a2_text' => 'Your own checkout journey, a provider you already use, or a system you need to connect.',
                'a3_title' => 'Custom design',
                'a3_text' => 'A bespoke look for your app and your marketing site, beyond the skins that ship.',
                'a4_title' => 'Brought closer to the official platform',
                'a4_text' => 'Features of the official Naara platform that are not part of your license.',
                'cta' => 'Request a quote',
                'cta_href' => '/contact',
            ],
            'disclaimer' => [
                'eyebrow' => 'Be clear-eyed',
                'headline' => 'Run it like a pro',
                'text_1' => 'At its current level, your white label is competitive in the market. It is a real platform with real products, and many businesses will be glad to run something this complete.',
                'text_2' => 'If you want to measure yourself against the official Naara platform, that is a different ambition. It becomes a custom project, and a custom project is quoted and charged on its own.',
                'text_3' => 'Things like a custom preloader or a custom marketing site are also extra. They are not part of the license, and we are happy to quote them when you want them.',
            ],
            'faq' => [
                'eyebrow' => 'Questions',
                'headline' => 'Before you decide',
                'q1' => 'Is this a template or a real platform?',
                'a1' => 'It is a real, working platform, a reflection of the official Naara. You get the products, the wallet, the admin panel and the dashboard, not a design file.',
                'q2' => 'Who sets it up?',
                'a2' => 'We do. We install it, connect your domain and apply your brand. Then you take over with the keys.',
                'q3' => 'Can I change the website text and images later?',
                'a3' => 'Yes, all of it, from the Marketing site editor in your admin panel. No developer is needed for that.',
                'q4' => 'What is the difference between Basic and Extended?',
                'a4' => 'Basic gives you the core platform and unlocks the richest features when you pay the balance to Extended. Extended has everything open from the first day. Extended V2 is the same, with priority support.',
                'q5' => 'Can I get features the official Naara has but my license does not?',
                'a5' => 'Yes, as custom work. That is a quoted project, because it goes beyond the license.',
                'q6' => 'What do custom projects cost?',
                'a6' => 'Custom work starts from the figure above and depends on the request. We send a clear quote before anything is built.',
            ],
            'cta' => [
                'headline' => 'Ready to run your own Naara?',
                'subtext' => 'Pick a license, or talk to us first. Either way, you will not be doing it alone.',
                'primary' => 'Get your white label',
                'secondary' => 'Talk to us',
                'secondary_href' => '/contact',
            ],
        ];
    }
}
