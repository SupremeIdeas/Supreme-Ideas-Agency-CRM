<?php

/** Naara Respond (MASTER-ONLY) settings. Defaults are safe: nothing here switches a provider on. */
return [
    // Registers the proof channel outside tests (development only).
    'fake' => (bool) env('OMNI_FAKE', false),

    // Shown in the broadcast estimate. 0 until the owner decides how broadcasts are billed (blueprint decision 19).
    'broadcast_cost_per_message' => (float) env('OMNI_BROADCAST_COST', 0),

    // Words that opt a customer out of broadcasts at once (blueprint section 14).
    'stop_words' => ['stop', 'unsubscribe', 'opt out', 'optout', 'cancel messages'],
    'start_words' => ['start', 'subscribe'],

    // Model class the AI assistant uses ("fast" = the cheap model, plenty for answers grounded in your own passages; "standard" costs about 2x more).
    'agent_model_class' => env('OMNI_AGENT_MODEL_CLASS', 'fast'),

    // Pricing assumptions (the report `naara:respond:pricing` multiplies these by the model price table). Measure and replace once real traffic exists.
    'pricing' => [
        'tokens_in_per_reply' => (int) env('OMNI_PRICING_TOKENS_IN', 1800),   // system rules + 4 knowledge passages + recent turns + the question
        'tokens_out_per_reply' => (int) env('OMNI_PRICING_TOKENS_OUT', 150),
        'ai_budget_pct' => (float) env('OMNI_PRICING_AI_BUDGET_PCT', 8),     // the most of a plan's price the AI may cost at full use
        'payment_fee_pct' => (float) env('OMNI_PRICING_FEE_PCT', 3.0),       // card processing, taken off every plan payment
        'expected_use_pct' => (float) env('OMNI_PRICING_EXPECTED_USE', 40),  // typical share of the allowance actually used
    ],

    // Naara's own Meta app and Telegram bot. The IDs are NOT secrets (they appear in URLs and screenshots), so they ship as visible defaults you can override in .env.
    // Secrets (app secret, tokens) are never in code: put them in .env or Admin > API keys.
    'meta' => [
        'app_id' => env('OMNI_META_APP_ID', env('META_APP_ID', '2195469598059043')),               // DEFAULT: Naara app, mode "In development"
        'business_id' => env('OMNI_META_BUSINESS_ID', '4491570984493160'),     // DEFAULT: NAARA business portfolio
        'app_secret' => env('OMNI_META_APP_SECRET', env('META_APP_SECRET', env('WHATSAPP_APP_SECRET'))),
        'verify_token' => env('OMNI_META_VERIFY_TOKEN', env('WHATSAPP_VERIFY_TOKEN')),
        'config_id' => env('OMNI_META_CONFIG_ID'),                             // Embedded Signup configuration (App Dashboard > WhatsApp > Embedded Signup)
        'domain' => env('OMNI_META_DOMAIN', 'naara.app'),                       // DEFAULT: the domain being verified in Meta Business settings
    ],
    'telegram' => [
        'platform_bot_username' => env('OMNI_TELEGRAM_BOT', 'NaaraConnectBot'),  // DEFAULT: t.me/NaaraConnectBot
        'platform_bot_token' => env('OMNI_TELEGRAM_BOT_TOKEN'),                  // never default: rotate in BotFather, then put the new token in .env
    ],
];
