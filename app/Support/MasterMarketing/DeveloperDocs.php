<?php

namespace App\Support\MasterMarketing;

/**
 * MASTER-ONLY. The structured content of the public developer documentation: pages grouped the way a developer looks for them (Get started, Core concepts,
 * API reference, Widgets and plugin, Resources). Every endpoint, scope, field and status code here mirrors docs/DEVELOPER-API.md and the live controllers;
 * DeveloperDocsContentTest fails if the two drift. Page blocks (the reading column) and samples (the code column) are plain data.
 *
 * Block kinds: p, h, list, table, callout (tone: info|warn|ok), steps, diagram.
 * Sample: ['title' => ..., 'tabs' => [lang => code]] (a multi-language request) or ['title' => ..., 'lang' => ..., 'code' => ...] (one block).
 */
final class DeveloperDocs
{
    /** @return list<array{slug:string,title:string,icon:string,pages:list<array<string,mixed>>}> */
    public static function groups(): array
    {
        return self::$cache ??= [
            ['slug' => 'get-started', 'title' => 'Get started', 'icon' => 'zap', 'pages' => [self::introduction(), self::authentication(), self::quickStart()]],
            ['slug' => 'core-concepts', 'title' => 'Core concepts', 'icon' => 'layers', 'pages' => [self::moneyModel(), self::idempotency(), self::rateLimits(), self::errors(), self::lifecycle()]],
            ['slug' => 'api-reference', 'title' => 'API reference', 'icon' => 'code', 'pages' => [self::catalogue(), self::quote(), self::createOrder(), self::getOrder()]],
            ['slug' => 'widgets', 'title' => 'Widgets and plugin', 'icon' => 'smartphone', 'pages' => [self::widgetsOverview(), self::widgetsOptions(), self::wordpress(), self::widgetsSecurity()]],
            ['slug' => 'resources', 'title' => 'Resources', 'icon' => 'book-open', 'pages' => [self::roadmap()]],
        ];
    }

    private static ?array $cache = null;

    /** @return list<array<string,mixed>> every page in reading order, each tagged with its group */
    public static function pages(): array
    {
        $out = [];
        foreach (self::groups() as $g) {
            foreach ($g['pages'] as $p) {
                $out[] = $p + ['group' => $g['title'], 'group_slug' => $g['slug']];
            }
        }

        return $out;
    }

    public static function find(string $slug): ?array
    {
        foreach (self::pages() as $p) {
            if ($p['slug'] === $slug) {
                return $p;
            }
        }

        return null;
    }

    /** @return array{prev:?array,next:?array} */
    public static function neighbours(string $slug): array
    {
        $pages = self::pages();
        foreach ($pages as $i => $p) {
            if ($p['slug'] === $slug) {
                return ['prev' => $pages[$i - 1] ?? null, 'next' => $pages[$i + 1] ?? null];
            }
        }

        return ['prev' => null, 'next' => null];
    }

    /** Escape, then allow only `code` and **bold** inline. Output is safe HTML. */
    public static function inline(string $text): string
    {
        $t = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $t = preg_replace('/`([^`]+)`/', '<code>$1</code>', $t);

        return preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $t);
    }

    // ---------------------------------------------------------------- Get started

    private static function introduction(): array
    {
        return [
            'slug' => 'introduction', 'title' => 'Introduction',
            'lead' => 'Sell Naara eSIM data and verification numbers from your own app, with a plain REST API and prepaid wholesale pricing.',
            'body' => [
                ['p', 'You call the API, you pay a **prepaid wholesale price**, and you relay the delivery to your customer: a QR code or activation string for an eSIM, or a phone number plus the code that arrives for it.'],
                ['h', 'What you can build'],
                ['list', [
                    '**eSIM data plans** for 190+ countries, plus Naara Connect Full eSIMs (calls, SMS and data on one eSIM).',
                    '**Verification numbers** for one-time codes, quoted live for the country and service you ask for.',
                    '**A storefront without code**: paste one snippet, or install the WordPress plugin, and Naara hosts the checkout for you.',
                ]],
                ['h', 'How it fits together'],
                ['steps', [
                    ['Get a key', 'Create an API client in your Developer area and choose its scopes: `catalogue`, `quote`, `order`, `status`.'],
                    ['Top up', 'Orders draw down your prepaid API balance in USD. An empty balance returns `402` and provisions nothing.'],
                    ['Call the API', 'List plans, quote a price, place an order with a unique `reference`, then poll the order until it is `completed`.'],
                ]],
                ['callout', 'info', 'The Developer API is switched on by the platform operator', 'If every request returns `404`, the API has not been enabled on this install yet. Ask the operator to turn it on.'],
            ],
            'samples' => [
                ['title' => 'Your first request', 'tabs' => DeveloperSamples::request('GET', '/catalogue')],
                ['title' => 'Response', 'lang' => 'json', 'code' => self::json(['data' => [['id' => 12, 'name' => 'Nigeria 5GB / 30 days', 'type' => 'local', 'has_voice' => false, 'data_mb' => 5120, 'validity_days' => 30, 'countries' => ['NG'], 'price_usd' => 11.0, 'currency' => 'USD']]])],
            ],
        ];
    }

    private static function authentication(): array
    {
        return [
            'slug' => 'authentication', 'title' => 'Authentication',
            'lead' => 'Every request carries your API key as a bearer token.',
            'body' => [
                ['h', 'Base URL'],
                ['p', 'All endpoints live under `https://YOUR-DOMAIN/api/v1`. The API is disabled by default; if every request returns `404`, ask the operator to switch it on.'],
                ['h', 'Send your key'],
                ['p', 'Put the key in the `Authorization` header on every request. Keys look like `7|Xy8s...` and are issued from your Developer area or by the operator.'],
                ['list', [
                    'The full key is shown **once**, when you create it. Store it somewhere safe; afterwards only its last four characters are shown.',
                    'A key belongs to an **API client**. Suspending the client, or your account, disables the key immediately.',
                    'Never put a key in browser code or a mobile app. Call the API from your server.',
                ]],
                ['h', 'Scopes'],
                ['p', 'Each key is granted a subset of scopes. A call needs the scope shown on its endpoint, or it returns `403`.'],
                ['table', ['Scope', 'Grants'], [
                    ['`catalogue`', 'Browse available plans'],
                    ['`quote`', 'Get live prices'],
                    ['`order`', 'Place orders (spends your balance)'],
                    ['`status`', 'Read your order status'],
                ]],
                ['callout', 'warn', 'Missing or invalid key', 'A missing or invalid key returns `401`. A valid key without the needed scope returns `403`.'],
            ],
            'samples' => [
                ['title' => 'Authenticated request', 'tabs' => DeveloperSamples::request('GET', '/catalogue')],
                ['title' => '401 Unauthorized', 'lang' => 'json', 'code' => self::json(['message' => 'Unauthenticated.'])],
            ],
        ];
    }

    private static function quickStart(): array
    {
        return [
            'slug' => 'quick-start', 'title' => 'Quick start',
            'lead' => 'From an empty balance to a delivered eSIM in five calls.',
            'body' => [
                ['steps', [
                    ['Get a key and top up', 'Create a key with the scopes `catalogue`, `quote`, `order` and `status`, then top up your API balance in the Developer area.'],
                    ['Pick a plan', '`GET /catalogue` returns plans. Choose one and keep its `id`.'],
                    ['Confirm the price', '`POST /quote` with that `plan_id` returns the exact price you will pay.'],
                    ['Place the order', '`POST /orders` with a unique `reference`. Hand `result.qr_code` (or the `result.lpa` string for manual install) to your customer.'],
                    ['Confirm delivery', '`GET /orders/{reference}` until `status` is `completed`.'],
                ]],
                ['callout', 'ok', 'Safe to retry', 'Orders are idempotent on `reference`. If your network drops after you place an order, send the same request again: you get the same order back and are never charged twice.'],
            ],
            'samples' => [
                ['title' => 'Place the order', 'tabs' => DeveloperSamples::request('POST', '/orders', ['type' => 'esim', 'plan_id' => 12, 'reference' => 'your-order-abc123'])],
                ['title' => '201 Created', 'lang' => 'json', 'code' => self::json(self::orderExample())],
            ],
        ];
    }

    // ---------------------------------------------------------------- Core concepts

    private static function moneyModel(): array
    {
        return [
            'slug' => 'money-model', 'title' => 'Money model',
            'lead' => 'What you pay, what you never see, and what happens when something fails.',
            'body' => [
                ['list', [
                    'Every price is **your developer price**: wholesale plus a small operator markup. It is below the retail price your customers would pay directly.',
                    'The provider cost is **never exposed** in any response.',
                    'Orders are **prepaid**: they draw down your client\'s API balance in USD. Top up in the Developer area.',
                    'An order against an empty balance returns `402` and provisions nothing.',
                    'Prices are in USD, returned to four decimal places.',
                ]],
                ['callout', 'ok', 'Never charged without delivery', 'Any provider or save failure refunds your API balance automatically. You only keep a charge for an order that was delivered.'],
            ],
            'samples' => [
                ['title' => '402 Insufficient balance', 'lang' => 'json', 'code' => self::json(['error' => 'insufficient_balance', 'message' => 'Top up your API balance and retry.'])],
            ],
        ];
    }

    private static function idempotency(): array
    {
        return [
            'slug' => 'idempotency', 'title' => 'Idempotency',
            'lead' => 'Retry any order without ever paying or provisioning twice.',
            'body' => [
                ['p', 'Pass a `reference` on `POST /orders`: any string up to 64 characters, unique per key. If you retry with the same `reference`, you get back the **same order**. You are never double-charged and nothing is provisioned twice. Leave it out and the server generates one.'],
                ['callout', 'info', 'Two requests at once', 'If a second request with the same `reference` arrives while the first is still being processed, it returns `409 duplicate_in_progress`. Wait a moment and retry the **same** `reference`; it resolves to the order once it has been saved.'],
            ],
            'samples' => [
                ['title' => 'Retry with the same reference', 'tabs' => DeveloperSamples::request('POST', '/orders', ['type' => 'esim', 'plan_id' => 12, 'reference' => 'your-order-abc123'])],
                ['title' => '409 Conflict', 'lang' => 'json', 'code' => self::json(['error' => 'duplicate_in_progress'])],
            ],
        ];
    }

    private static function rateLimits(): array
    {
        return [
            'slug' => 'rate-limits', 'title' => 'Rate limits',
            'lead' => 'Requests are limited per key.',
            'body' => [
                ['p', 'The default limit is **300 requests per minute per key**. Going over returns `429 Too Many Requests`. Back off and retry after a short pause.'],
            ],
            'samples' => [
                ['title' => '429 Too Many Requests', 'lang' => 'json', 'code' => self::json(['message' => 'Too Many Attempts.'])],
            ],
        ];
    }

    private static function errors(): array
    {
        return [
            'slug' => 'errors', 'title' => 'Errors',
            'lead' => 'Errors are JSON, and the status code tells you what to do next.',
            'body' => [
                ['p', 'Business errors carry an `error` code and a `message`. Authentication and validation errors follow Laravel\'s shape: `{"message": "..."}`, and `{"message": "...", "errors": {...}}` for validation.'],
                ['table', ['Status', 'When'], [
                    ['`400` / `422`', 'Invalid input: bad or missing fields, or an unknown plan'],
                    ['`401`', 'Missing or invalid API key'],
                    ['`402`', 'Insufficient API balance'],
                    ['`403`', 'The key lacks the required scope, or the client is not permitted'],
                    ['`404`', 'The API is disabled, or the resource was not found'],
                    ['`409`', '`duplicate_in_progress`: an order with this `reference` is still being processed. Retry the same `reference` shortly'],
                    ['`429`', 'Rate limit exceeded'],
                    ['`500`', 'The order could not be finalised. Your balance is refunded automatically'],
                    ['`502`', 'No provider could fulfil it right now. Your balance is refunded automatically'],
                ]],
            ],
            'samples' => [
                ['title' => 'Business error', 'lang' => 'json', 'code' => self::json(['error' => 'insufficient_balance', 'message' => 'Top up your API balance and retry.'])],
                ['title' => 'Validation error', 'lang' => 'json', 'code' => self::json(['message' => 'The plan id field is required.', 'errors' => ['plan_id' => ['The plan id field is required.']]])],
            ],
        ];
    }

    private static function lifecycle(): array
    {
        return [
            'slug' => 'order-lifecycle', 'title' => 'Order lifecycle',
            'lead' => 'What happens between your request and a delivered eSIM or number.',
            'body' => [
                ['diagram', 'lifecycle'],
                ['list', [
                    'Your API balance is charged first. If it is too low you get `402` and nothing happens.',
                    'The order is fulfilled with a provider. If none can fulfil it you get `502` and your balance is refunded.',
                    'The order is saved. If saving fails you get `500` and your balance is refunded.',
                    'You then poll `GET /orders/{reference}`: `status` moves from `processing` to `completed` once the eSIM is active or the number has received its code.',
                ]],
                ['callout', 'ok', 'The guarantee', 'You are never charged without delivery, and retries with the same `reference` are safe.'],
            ],
            'samples' => [
                ['title' => 'Poll the order', 'tabs' => DeveloperSamples::request('GET', '/orders/your-order-abc123')],
            ],
        ];
    }

    // ---------------------------------------------------------------- API reference

    private static function catalogue(): array
    {
        return [
            'slug' => 'catalogue', 'title' => 'List plans', 'method' => 'GET', 'path' => '/catalogue', 'scope' => 'catalogue',
            'lead' => 'Browse the eSIM plans you can sell.',
            'body' => [
                ['p', 'Returns every available plan with its developer price. Add `?has_voice=true` to return only **Naara Connect** plans (Full eSIMs with calls, SMS and data), or `?has_voice=false` for data-only plans.'],
                ['h', 'Fields'],
                ['table', ['Field', 'Type', 'Notes'], [
                    ['`id`', 'int', 'Use this as `plan_id` when you quote or order'],
                    ['`name`', 'string', 'Display name'],
                    ['`type`', 'string', 'For example `local`'],
                    ['`has_voice`', 'bool', '`false` is data-only; `true` is a Naara Connect Full eSIM'],
                    ['`data_mb`', 'int', 'Data allowance in megabytes'],
                    ['`validity_days`', 'int', 'How long the plan lasts'],
                    ['`countries`', 'string[]', 'ISO country codes the plan covers'],
                    ['`price_usd`', 'number', 'Your developer price'],
                    ['`currency`', 'string', 'Always `USD`'],
                ]],
            ],
            'samples' => [
                ['title' => 'Request', 'tabs' => DeveloperSamples::request('GET', '/catalogue')],
                ['title' => '200 OK', 'lang' => 'json', 'code' => self::json(['data' => [['id' => 12, 'name' => 'Nigeria 5GB / 30 days', 'type' => 'local', 'has_voice' => false, 'data_mb' => 5120, 'validity_days' => 30, 'countries' => ['NG'], 'price_usd' => 11.0, 'currency' => 'USD']]])],
            ],
        ];
    }

    private static function quote(): array
    {
        return [
            'slug' => 'quote', 'title' => 'Get a quote', 'method' => 'POST', 'path' => '/quote', 'scope' => 'quote',
            'lead' => 'Find out the exact price before you order.',
            'body' => [
                ['p', 'A quote never spends anything. For an eSIM pass the `plan_id`; for a number pass the type, country and service. Number prices are fetched live.'],
                ['h', 'Body'],
                ['table', ['Field', 'Type', 'Notes'], [
                    ['`type`', 'string', '`esim` or `number`'],
                    ['`plan_id`', 'int', 'Required when `type` is `esim`'],
                    ['`number_type`', 'string', '`otp` or `rental`, required when `type` is `number`'],
                    ['`country`', 'string', 'Required when `type` is `number`, for example `nigeria` or `usa`'],
                    ['`service`', 'string', 'Required when `type` is `number`, for example `whatsapp`'],
                ]],
                ['callout', 'info', 'Nothing available', 'If no number is available for that country and service, you get `422` with `{"error": "unavailable"}`.'],
            ],
            'samples' => [
                ['title' => 'Quote an eSIM', 'tabs' => DeveloperSamples::request('POST', '/quote', ['type' => 'esim', 'plan_id' => 12])],
                ['title' => '200 OK', 'lang' => 'json', 'code' => self::json(['type' => 'esim', 'plan_id' => 12, 'price_usd' => 11.0, 'currency' => 'USD'])],
                ['title' => 'Quote a number', 'tabs' => DeveloperSamples::request('POST', '/quote', ['type' => 'number', 'number_type' => 'otp', 'country' => 'nigeria', 'service' => 'whatsapp'])],
                ['title' => '200 OK', 'lang' => 'json', 'code' => self::json(['type' => 'number', 'number_type' => 'otp', 'country' => 'nigeria', 'service' => 'whatsapp', 'price_usd' => 0.23, 'currency' => 'USD'])],
            ],
        ];
    }

    private static function createOrder(): array
    {
        return [
            'slug' => 'create-order', 'title' => 'Place an order', 'method' => 'POST', 'path' => '/orders', 'scope' => 'order',
            'lead' => 'Buy an eSIM or a number. This spends your API balance.',
            'body' => [
                ['p', 'Send a unique `reference` so a retry can never double-charge. For an eSIM, relay `result.qr_code` (or the `result.lpa` string for manual install) to your customer, then poll the order until it is `completed`.'],
                ['p', 'For a number, the verification code arrives asynchronously. Poll the order: once the code is received, `status` becomes `completed` and `result.code` holds it. A number that never receives a code times out and its `status` becomes `failed`.'],
                ['h', 'Body'],
                ['table', ['Field', 'Type', 'Notes'], [
                    ['`type`', 'string', '`esim` or `number`'],
                    ['`plan_id`', 'int', 'Required when `type` is `esim` (an `id` from `/catalogue`)'],
                    ['`number_type`', 'string', '`otp` or `rental`, required when `type` is `number`'],
                    ['`country`', 'string', 'Required when `type` is `number`'],
                    ['`service`', 'string', 'Required when `type` is `number`'],
                    ['`reference`', 'string', 'Optional idempotency key, up to 64 characters'],
                ]],
                ['h', 'Responses'],
                ['table', ['Status', 'Meaning'], [
                    ['`201`', 'Order created'],
                    ['`200`', 'A repeated `reference`: the original order, once it has finished saving'],
                    ['`402`', 'Insufficient balance'],
                    ['`409`', '`duplicate_in_progress`: retry the same `reference` shortly'],
                    ['`422`', 'Invalid plan, or number unavailable'],
                    ['`500` / `502`', 'Not delivered, and your balance was refunded'],
                ]],
            ],
            'samples' => [
                ['title' => 'Order an eSIM', 'tabs' => DeveloperSamples::request('POST', '/orders', ['type' => 'esim', 'plan_id' => 12, 'reference' => 'your-order-abc123'])],
                ['title' => '201 Created', 'lang' => 'json', 'code' => self::json(self::orderExample())],
                ['title' => 'Order a number', 'tabs' => DeveloperSamples::request('POST', '/orders', ['type' => 'number', 'number_type' => 'otp', 'country' => 'nigeria', 'service' => 'whatsapp', 'reference' => 'your-otp-xyz'])],
                ['title' => '201 Created', 'lang' => 'json', 'code' => self::json(['reference' => 'your-otp-xyz', 'kind' => 'number', 'status' => 'processing', 'price_usd' => 0.23, 'currency' => 'USD', 'result' => ['number' => '+234...', 'code' => null], 'created_at' => '2026-07-20T12:00:00+00:00'])],
            ],
        ];
    }

    private static function getOrder(): array
    {
        return [
            'slug' => 'get-order', 'title' => 'Get an order', 'method' => 'GET', 'path' => '/orders/{reference}', 'scope' => 'status',
            'lead' => 'Check where an order is, and fetch its delivery.',
            'body' => [
                ['p', 'Returns the same shape as the order response. `status` advances from `processing` to `completed` once the eSIM is active or the number has received its code. If the reference is not one of your orders you get `404` with `{"error": "not_found"}`.'],
            ],
            'samples' => [
                ['title' => 'Request', 'tabs' => DeveloperSamples::request('GET', '/orders/your-order-abc123')],
                ['title' => '200 OK', 'lang' => 'json', 'code' => self::json(['reference' => 'your-order-abc123', 'kind' => 'esim', 'status' => 'completed', 'price_usd' => 11.0, 'currency' => 'USD', 'result' => ['iccid' => '8944...', 'qr_code' => 'https://.../qr.png', 'lpa' => 'LPA:1$rsp.example.com$ACTIVATION-CODE'], 'created_at' => '2026-07-20T12:00:00+00:00'])],
                ['title' => '404 Not found', 'lang' => 'json', 'code' => self::json(['error' => 'not_found'])],
            ],
        ];
    }

    // ---------------------------------------------------------------- Widgets and plugin

    private static function widgetsOverview(): array
    {
        return [
            'slug' => 'widgets-overview', 'title' => 'Embed the widget',
            'lead' => 'Sell Naara eSIMs, verification numbers and gift cards on your own site without writing API code.',
            'body' => [
                ['p', 'Paste one snippet (or install the WordPress plugin) and your site shows a shop. Your shoppers pick a product, pay on a secure Naara checkout page and get it by email. You earn your margin on every sale; Naara handles payment, delivery and support.'],
                ['steps', [
                    ['Create a key', 'In your merchant dashboard, open Widgets and create a key. List the exact website address it may run on.'],
                    ['Paste the snippet', 'Put the two lines below on your page, where you want the shop to appear.'],
                    ['Watch it sell', 'Your Widgets page shows views, checkouts, orders and the margin you earned.'],
                ]],
                ['callout', 'info', 'Access may be paid', 'The operator can charge a one-time access fee for Widgets, or make them free. Your Widgets page tells you which applies.'],
            ],
            'samples' => [
                ['title' => 'Embed snippet', 'lang' => 'html', 'code' => "<div id=\"naara-widget\"></div>\n<script src=\"https://YOUR-DOMAIN/widgets/merchant-widget.js\"\n        data-naara-key=\"wk_live_your_public_key\" defer></script>"],
            ],
        ];
    }

    private static function widgetsOptions(): array
    {
        return [
            'slug' => 'widgets-options', 'title' => 'Widget options',
            'lead' => 'Tune what the widget sells and how it looks with data attributes.',
            'body' => [
                ['table', ['Attribute', 'Values', 'What it does'], [
                    ['`data-naara-key`', '`wk_live_...`', 'Required. Your **public** key. It identifies you but never authorises a request on its own'],
                    ['`data-product-type`', '`esim`, `number`, `giftcard`', 'Show only one product. Leave it out to show everything the key allows, as tabs'],
                    ['`data-mode`', '`modal` (default), `redirect`', 'Open checkout in a pop-up, or send the shopper to a full page'],
                    ['`data-theme`', '`light`, `dark`, `auto`', 'Match your site. `auto` follows the visitor\'s setting'],
                    ['`data-theme-color`', '`#0A6E6E`', 'Your accent colour as a six-digit hex value'],
                    ['`data-target`', 'a CSS selector', 'Mount the widget somewhere other than `#naara-widget`'],
                    ['`data-account`', '`on` (default), `off`', 'Turn the shopper "My orders" area off'],
                    ['`data-start`', '`account`', 'Open straight on "My orders", for a dedicated page'],
                ]],
                ['p', 'Verification numbers must be switched on by the operator, and the key needs the `number` product. Shoppers sign in to "My orders" with an emailed code, so nobody needs a password.'],
            ],
            'samples' => [
                ['title' => 'eSIM-only shop in your colour', 'lang' => 'html', 'code' => "<div id=\"naara-widget\"></div>\n<script src=\"https://YOUR-DOMAIN/widgets/merchant-widget.js\"\n        data-naara-key=\"wk_live_your_public_key\"\n        data-product-type=\"esim\"\n        data-theme-color=\"#0A6E6E\"\n        data-theme=\"auto\" defer></script>"],
                ['title' => 'A dedicated My orders page', 'lang' => 'html', 'code' => "<div id=\"naara-widget\"></div>\n<script src=\"https://YOUR-DOMAIN/widgets/merchant-widget.js\"\n        data-naara-key=\"wk_live_your_public_key\"\n        data-start=\"account\" defer></script>"],
            ],
        ];
    }

    private static function wordpress(): array
    {
        return [
            'slug' => 'wordpress', 'title' => 'WordPress plugin',
            'lead' => 'Prefer WordPress? Upload the plugin and use a block or a shortcode.',
            'body' => [
                ['steps', [
                    ['Download', 'Your Widgets page offers the plugin pre-filled with your key.'],
                    ['Upload', 'In WordPress, open Plugins, Add New, Upload Plugin, then activate it.'],
                    ['Place it', 'Add the **Naara widget** block to a page, or use a shortcode.'],
                ]],
                ['table', ['Shortcode', 'Shows'], [
                    ['`[naara_widget]`', 'Everything your key allows, as tabs'],
                    ['`[naara_widget type="esim"]`', 'One product: `esim`, `number` or `giftcard`'],
                    ['`[naara_widget theme="dark"]`', 'Force `light`, `dark` or `auto`'],
                    ['`[naara_account]`', 'A "My orders" page for returning customers'],
                ]],
                ['p', 'The settings page includes a one-click connection test that runs in your browser exactly as a visitor\'s page would, so you can see at once if the site address needs adding to your key.'],
            ],
            'samples' => [
                ['title' => 'Shortcodes', 'lang' => 'html', 'code' => "[naara_widget]\n[naara_widget type=\"esim\" theme=\"auto\"]\n[naara_account]"],
            ],
        ];
    }

    private static function widgetsSecurity(): array
    {
        return [
            'slug' => 'widgets-security', 'title' => 'Widget security',
            'lead' => 'Why it is safe to publish your key, and what protects your shoppers.',
            'body' => [
                ['list', [
                    'The key in your page is **public**. It identifies you; it can never move money on its own.',
                    'Every call is checked live: the key is active, your account is active, the page\'s address is on the key\'s allowed list, the product is allowed, and the rate limit holds.',
                    'Keys are locked to **exact domains**. There are no wildcards, and `www` is added separately.',
                    'Revoke or rotate a key at any time. A revoked key stops working on its very next request.',
                    'Checkout happens on a Naara-hosted page, so card and wallet details never touch your site.',
                    'Shoppers see only their own orders, placed through your site, after signing in with an emailed code.',
                ]],
                ['callout', 'info', 'Optional integrity pin', 'You can pin the script with a Subresource Integrity hash so a tampered copy is refused by the browser. Your Widgets page can show the hash for the current file.'],
            ],
            'samples' => [
                ['title' => 'Pinned script (optional)', 'lang' => 'html', 'code' => "<script src=\"https://YOUR-DOMAIN/widgets/merchant-widget.js\"\n        data-naara-key=\"wk_live_your_public_key\"\n        integrity=\"sha384-...\" crossorigin=\"anonymous\" defer></script>"],
            ],
        ];
    }

    // ---------------------------------------------------------------- Resources

    private static function roadmap(): array
    {
        return [
            'slug' => 'roadmap', 'title' => 'What is coming',
            'lead' => 'What is live today, and what we are still building.',
            'body' => [
                ['h', 'Live now'],
                ['list', [
                    'eSIM and number catalogue, quotes, orders and order status.',
                    'Prepaid balance with automatic refunds, and idempotent orders.',
                    'Naara Widgets and the WordPress plugin.',
                ]],
                ['h', 'On the roadmap'],
                ['list', [
                    'Permanent numbers through the API.',
                    'Webhooks for delivery and verification-code callbacks.',
                    'An interactive sandbox inside the developer area.',
                ]],
                ['callout', 'info', 'Until webhooks arrive', 'Poll `GET /orders/{reference}`. It is cheap, idempotent and always returns the latest state.'],
            ],
            'samples' => [],
        ];
    }

    // ---------------------------------------------------------------- shared

    /** @return array<string,mixed> */
    private static function orderExample(): array
    {
        return [
            'reference' => 'your-order-abc123', 'kind' => 'esim', 'status' => 'processing', 'price_usd' => 11.0, 'currency' => 'USD',
            'result' => ['iccid' => '8944...', 'qr_code' => 'https://.../qr.png', 'lpa' => 'LPA:1$rsp.example.com$ACTIVATION-CODE'],
            'created_at' => '2026-07-20T12:00:00+00:00',
        ];
    }

    private static function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
}
