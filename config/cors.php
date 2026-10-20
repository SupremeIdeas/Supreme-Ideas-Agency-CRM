<?php

/*
 | CORS — identical to Laravel's built-in default (paths api/* and sanctum/csrf-cookie, any origin) EXCEPT that the Naara Widgets
 | surface (api/v1/merchant-widget/*, MASTER-ONLY) is deliberately NOT listed: it computes its own per-key, per-request
 | Access-Control-Allow-Origin from the key's exact domain allowlist (EnsureMerchantWidgetKeyUsable) and must never receive a
 | wildcard from this global layer. Add new non-widget API prefixes here explicitly.
 */
return [
    'paths' => [
        'api/user',
        'api/v1/catalogue',
        'api/v1/catalogue/*',
        'api/v1/quote',
        'api/v1/quote/*',
        'api/v1/orders',
        'api/v1/orders/*',
        'api/v1/white-label/*',
        'sanctum/csrf-cookie',
    ],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
