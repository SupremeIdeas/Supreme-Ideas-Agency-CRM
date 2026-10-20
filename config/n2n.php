<?php

/**
 * N2N (Naara-to-Naara) configuration (MASTER-ONLY, permanent). Every threshold here is only a DEFAULT: the admin-owned value lives in
 * Settings (see App\Support\N2N\N2NSettings), never as a constant in code.
 */
return [
    'username' => ['min' => 3, 'max' => 20],

    // Always reserved, on top of the admin list and every brand name (protects brand owners from squatting).
    'reserved_usernames' => ['admin', 'administrator', 'support', 'naara', 'naarasim', 'nia', 'staff', 'help', 'official', 'system', 'root', 'billing', 'security', 'moderator', 'team'],

    'otp' => ['driver' => env('N2N_OTP_DRIVER', 'twilio')],  // twilio | log

    // Realtime transport (Batch 4). The admin picks the driver in Settings; this is only the default and the Reverb connection details.
    'realtime' => [
        'driver' => env('N2N_REALTIME_DRIVER', 'poll'),   // reverb | poll | null
        'event_ttl_hours' => 48,
        'reverb' => [
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'host' => env('REVERB_HOST', '127.0.0.1'),            // where the PHP app reaches the Reverb server
            'port' => (int) env('REVERB_PORT', 8080),
            'scheme' => env('REVERB_SCHEME', 'http'),
            'public_host' => env('REVERB_PUBLIC_HOST'),           // what the browser connects to (wss behind the reverse proxy)
            'public_port' => (int) env('REVERB_PUBLIC_PORT', 443),
            'public_scheme' => env('REVERB_PUBLIC_SCHEME', 'https'),
        ],
    ],

    // Voice calls (Batch 10). TURN is self-hosted coturn with `use-auth-secret`; the secret lives only in the environment.
    'calls' => [
        'turn' => [
            'urls' => env('N2N_TURN_URLS', ''),        // e.g. turn:turn.example.com:3478?transport=udp,turn:turn.example.com:3478?transport=tcp,turns:turn.example.com:5349?transport=tcp
            'stun' => env('N2N_STUN_URLS', ''),        // e.g. stun:turn.example.com:3478 (coturn answers STUN too)
            'secret' => env('N2N_TURN_SECRET', ''),
            'ttl' => (int) env('N2N_TURN_TTL', 3600),
        ],
    ],

    // Group calls (Batch 11, VPS only): a self-hosted LiveKit SFU. The API secret lives only in the environment and is never sent to a browser;
    // the browser only ever receives a short-lived join token that the server signs per participant.
    'sfu' => [
        'url' => env('N2N_SFU_URL', ''),          // server-side address of the SFU HTTP API, e.g. http://127.0.0.1:7880
        'ws_url' => env('N2N_SFU_WS_URL', ''),    // the address browsers connect to, e.g. wss://sfu.example.com
        'key' => env('N2N_SFU_KEY', ''),
        'secret' => env('N2N_SFU_SECRET', ''),
    ],
];
