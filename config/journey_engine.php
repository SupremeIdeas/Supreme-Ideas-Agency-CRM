<?php

/**
 * Journey Engine (J0/J1): intents and the starter rules. Every threshold is a Setting (journey.*), so nothing here is a hard-coded number; the defaults below are
 * HYPOTHESES to be proven with the metrics in the blueprint section 12, not market facts.
 *
 * Intent: a user-chosen reason for being here. The list is FIXED (a free-text answer can only be mapped onto it, never extend it).
 * Rule: signal (a Signals method that returns evidence or null) + priority + eligibility. An `opportunity` rule without `evidence_fields` is refused by the validator,
 * so a suggestion can never appear without saying WHY. `intents` = ['*'] means every user (Guardian-style protective alerts).
 */
return [
    'intents' => [
        'travel' => ['label' => 'Stay connected abroad', 'hint' => 'eSIM data plans for your trips', 'icon' => 'plane', 'route' => 'catalogue'],
        'verify' => ['label' => 'Get a number to verify accounts', 'hint' => 'One-time codes and rentals', 'icon' => 'hash', 'route' => 'numbers'],
        'line' => ['label' => 'A real phone line', 'hint' => 'Calls, texts and forwarding', 'icon' => 'phone', 'route' => 'numbers.lines'],
        'business' => ['label' => 'Run my business', 'hint' => 'Team, inbox and call flows', 'icon' => 'layers', 'route' => 'numbers'],
        'brand' => ['label' => 'Grow my brand or music', 'hint' => 'Get listed and be found', 'icon' => 'star', 'route' => 'brand.get-listed'],
        'chat' => ['label' => 'Message and call people on Naara', 'hint' => 'Chat inside the app', 'icon' => 'message-circle', 'route' => 'n2n.chat'],
        'earn' => ['label' => 'Earn or resell', 'hint' => 'Referrals and rewards', 'icon' => 'gift', 'route' => 'referrals'],
        'unsure' => ['label' => 'Not sure, help me choose', 'hint' => 'We will guide you', 'icon' => 'help-circle', 'route' => 'guide'],
    ],

    // Words in a free-text answer that point at an intent. Deterministic on purpose (no AI needed); an AI mapper may only choose among the same keys.
    'keywords' => [
        'travel' => ['travel', 'trip', 'abroad', 'esim', 'roaming', 'holiday', 'visit', 'flight', 'moving to'],
        'verify' => ['verify', 'verification', 'otp', 'code', 'whatsapp', 'telegram', 'sign up', 'bank'],
        'line' => ['phone line', 'landline', 'call', 'calls', 'texts', 'sms', 'forward', 'voicemail', 'second number', 'keep my number'],
        'business' => ['business', 'team', 'staff', 'customers', 'shop', 'store', 'company', 'support line'],
        'brand' => ['brand', 'music', 'artist', 'creator', 'listing', 'followers', 'album'],
        'chat' => ['chat', 'message friends', 'naara id', 'username'],
        'earn' => ['earn', 'referral', 'resell', 'commission', 'affiliate', 'income'],
    ],

    // A landing page or link source that pre-selects an intent (never final: the user confirms).
    'entry_sources' => ['esim' => 'travel', 'travel' => 'travel', 'verify' => 'verify', 'otp' => 'verify', 'line' => 'line', 'numbers' => 'line', 'business' => 'business', 'brand' => 'brand', 'earn' => 'earn', 'referral' => 'earn'],

    'priorities' => ['critical' => 1000, 'important' => 500, 'opportunity' => 100, 'fyi' => 10],

    'rules' => [
        'install_guide' => [
            'name' => 'Finish installing your eSIM', 'signal' => 'installGuide', 'priority' => 'important', 'intents' => ['travel'],
            'route' => 'numbers.lines', 'copy' => 'install_guide', 'evidence_fields' => ['hours_since_purchase', 'plan'],
        ],
        'low_data' => [
            'name' => 'Data running low', 'signal' => 'lowData', 'priority' => 'important', 'intents' => ['*'],
            'route' => 'catalogue', 'copy' => 'low_data', 'evidence_fields' => ['percent_used', 'mb_left'],
        ],
        'esim_expiring' => [
            'name' => 'eSIM about to expire', 'signal' => 'esimExpiring', 'priority' => 'important', 'intents' => ['*'],
            'route' => 'catalogue', 'copy' => 'esim_expiring', 'evidence_fields' => ['hours_left'],
        ],
        'number_renewal' => [
            'name' => 'Number renewal needs funds', 'signal' => 'numberRenewal', 'priority' => 'critical', 'intents' => ['*'],
            'route' => 'wallet', 'copy' => 'number_renewal', 'evidence_fields' => ['days_left', 'needed_usd', 'balance_usd'],
        ],
        'missed_calls' => [
            'name' => 'Missed calls this week', 'signal' => 'missedCalls', 'priority' => 'opportunity', 'intents' => ['line', 'business'],
            'owns' => [], 'route' => 'numbers.forwarding', 'copy' => 'missed_calls', 'evidence_fields' => ['missed_calls_7d'],
        ],
        // J4 brand flows. (Business flows are NOT here: they need the Business Suite, which is master-only and lives on another branch, so shared code must not reference it.)
        'brand_listing_paused' => [
            'name' => 'Brand listing paused (billing)', 'signal' => 'brandListingPaused', 'priority' => 'important', 'intents' => ['*'],
            'route' => 'brand.manage', 'copy' => 'brand_listing_paused', 'evidence_fields' => ['brand'],
        ],
        'brand_setup_incomplete' => [
            'name' => 'Finish your brand listing', 'signal' => 'brandSetupIncomplete', 'priority' => 'opportunity', 'intents' => ['brand'],
            'route' => 'brand.manage', 'copy' => 'brand_setup_incomplete', 'evidence_fields' => ['brand', 'hours_since_start'],
        ],
        'brand_get_listed' => [
            'name' => 'Get your brand listed', 'signal' => 'brandGetListed', 'priority' => 'opportunity', 'intents' => ['brand'],
            'owns' => ['brand_listing'], 'route' => 'brand.get-listed', 'copy' => 'brand_get_listed', 'evidence_fields' => ['intent'],
        ],
        'referral_after_success' => [
            'name' => 'Share Naara', 'signal' => 'referralAfterSuccess', 'priority' => 'opportunity', 'intents' => ['*'],
            'route' => 'referrals', 'copy' => 'referral_after_success', 'evidence_fields' => ['event'],
        ],
    ],
];
