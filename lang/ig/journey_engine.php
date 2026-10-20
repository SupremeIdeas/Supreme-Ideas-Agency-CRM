<?php

/**
 * Journey Engine copy. Plain, specific, evidence-led: no false urgency, no invented scarcity (blueprint principle 7). `why` is the sentence behind "Why am I seeing this?"
 * and may use the evidence fields as :placeholders. Only English is shipped; other locales fall back to it.
 */
return [
    'intent' => [
        'title' => 'What brings you to Naara?',
        'sub' => 'Pick everything that fits. You can change this any time.',
        'save' => 'Continue',
        'skip' => 'Skip for now',
        'saved' => 'Thanks. We will tailor what you see.',
        'text_label' => 'Or tell us in your own words',
        'text_placeholder' => 'For example: I am travelling to Kenya next month',
        'suggested' => 'We think this fits you. Tap to confirm.',
        'understand' => 'Suggest',
        'selected' => 'Nothing selected yet|:count selected|:count selected',
    ],
    'card' => [
        'why' => 'Why am I seeing this?',
        'dismiss' => 'Not now',
        'snooze' => 'Remind me tomorrow',
        'mute' => 'Hide suggestions like this',
        'today' => 'Today',
        'all_good' => 'All good. Nothing needs your attention.',
        'guardian_attention' => ':count thing needs your attention.|:count things need your attention.',
    ],
    'cards' => [
        'install_guide' => [
            'title' => 'Finish installing your eSIM',
            'body' => 'Your eSIM is bought but not active on a phone yet. The install steps take about two minutes.',
            'cta' => 'Open my lines',
            'why' => 'You bought :plan :hours_since_purchase hours ago and it has not been activated.',
        ],
        'low_data' => [
            'title' => 'Your data is running low',
            'body' => 'You have used :percent_used% of this plan. Top up before it runs out.',
            'cta' => 'Top up or choose a plan',
            'why' => 'Your latest usage reading shows :percent_used% used, with :mb_left MB left.',
        ],
        'brand_listing_paused' => [
            'title' => 'Your brand listing is paused',
            'body' => ':brand is hidden from the directory because the wallet could not cover the renewal. Top up and it resumes by itself.',
            'cta' => 'Open my brand',
            'why' => 'The listing for :brand is paused for billing. Your followers and credits are untouched.',
        ],
        'brand_setup_incomplete' => [
            'title' => 'Finish your brand listing',
            'body' => ':brand is not live yet. A few more details and people can find and follow it.',
            'cta' => 'Finish setup',
            'why' => 'You started :brand about :hours_since_start hours ago and the listing is not live yet.',
        ],
        'brand_get_listed' => [
            'title' => 'Get your brand in front of people',
            'body' => 'List your brand or music on Naara so members can find and follow it.',
            'cta' => 'See how it works',
            'why' => 'Because :intent, and you do not have a listing yet.',
        ],
        'esim_expiring' => [
            'title' => 'Your eSIM is about to expire',
            'body' => 'It ends in about :hours_left hours. Renew to stay connected.',
            'cta' => 'Renew or pick a new plan',
            'why' => 'This eSIM expires in about :hours_left hours.',
        ],
        'number_renewal' => [
            'title' => 'Your number needs funds to renew',
            'body' => 'It renews in :days_left days for $:needed_usd and your wallet holds $:balance_usd. Add funds so you do not lose it.',
            'cta' => 'Add funds',
            'why' => 'A number renews in :days_left days and your wallet balance ($:balance_usd) is below the renewal price ($:needed_usd).',
        ],
        'missed_calls' => [
            'title' => 'You missed some calls',
            'body' => ':missed_calls_7d calls went unanswered this week. Set where unanswered calls go, so no one is left hanging.',
            'cta' => 'Set up forwarding',
            'why' => ':missed_calls_7d calls on your lines rang out unanswered in the last 7 days.',
        ],
        'referral_after_success' => [
            'title' => 'Know someone who needs this?',
            'body' => 'Share your link and you both benefit when they buy. The exact reward is shown on the Referrals page.',
            'cta' => 'See my referral link',
            'why' => 'You just had a success (:event) and have not shared Naara yet.',
        ],
    ],
    'delivery' => [
        'title' => 'Naara alerts',
        'sub' => 'Choose how we tell you when something needs you. Everything here is off until you turn it on.',
        'channel_push' => 'Push notifications',
        'channel_push_hint' => 'On this device, even when the app is closed.',
        'channel_email' => 'Email',
        'channel_email_hint' => 'To the address on your account.',
        'on' => ':channel alerts are on.',
        'off' => ':channel alerts are off.',
        'what_title' => 'What we send, and what we never send',
        'what_1' => 'Only alerts about your own services: data running low, an eSIM about to expire, a number renewal your wallet cannot cover.',
        'what_2' => 'No offers or suggestions are ever pushed or emailed. Those stay in the app.',
        'what_3' => 'At most two a day, never at night (your time), and the same alert is not repeated within a day.',
        'what_4' => 'We never read your messages, voicemail or contacts to decide any of this. Turn a switch off and it stops at the next check.',
        'manage' => 'Manage these alerts: :url',
    ],
];
