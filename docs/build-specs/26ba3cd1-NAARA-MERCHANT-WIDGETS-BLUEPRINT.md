# Naara Widgets — Merchant Embed & WordPress Plugin Distribution Layer
### Master Engineering Blueprint for Claude Code

> **Grounded against:** `NaaraSim-Master_Platform_Sept_22.zip` (live codebase, read in full
> before a single line of this blueprint was written — no invented file names, no guessed
> classes). Every class, table, and pattern cited below exists today unless explicitly marked
> **NEW**. Where a decision had to be made rather than found in the code, it is called out in
> **§13 Flagged Decisions** exactly the way `docs/architecture/WHITE-LABEL-LICENSE-BOUNDARY.md`
> flags its own — stated, not silently assumed.
>
> **Answer to the direct question: yes, this is buildable, and it is buildable *safely*,
> because ~80% of the hard part already exists in your repo.** You already have a
> KYC-gated Merchant model with an admin-owned reseller margin, an atomic
> `MerchantEarningsService` ledger, a masked Developer API layer with scoped Sanctum
> tokens, and a `PricingEngine` with a dedicated merchant price lane. What's missing is
> a **distribution channel** that lets a merchant's *own* website — not a Naara-branded
> storefront URL — pull the catalogue and complete a purchase without ever touching the
| merchant's payment gateway. That channel is what this document builds.

---

## 0. The one-sentence architecture

**A merchant's website never touches money or the catalogue database.** It loads one
`<script>` tag (or the WordPress plugin, which just inserts that same script tag), the
script renders products fetched live from Naara over a scoped, domain-locked API key,
and when a shopper clicks "buy" the widget opens a **Naara-hosted checkout** (modal
iframe or full-page redirect — merchant's choice) where Naara's own Paystack /
Flutterwave / Stripe integration collects the money. Naara fulfils the eSIM / number /
gift card, credits the merchant's existing `MerchantEarningsService` ledger with the
reseller margin, and the merchant withdraws through the payout rails you already built
(`payout_accounts` / `payout_requests`). The merchant's WordPress site is a **shop
window**, never a **cash register**.

This is precisely the model on the Bitrefill page you linked
(`bitrefill.com/integrate`): a partner embeds a widget, Bitrefill's own checkout collects
payment, the partner earns "a passive income from each sale," and Bitrefill's team
"handle[s] customer support for any issues that may arise." Your version keeps that
shape and plugs it directly into infrastructure you've already built and tested.

---

## 1. What already exists (read this before building anything)

Confirmed in the live repo — **do not rebuild these**, extend them:

| Capability | Lives in | Notes |
|---|---|---|
| Reseller storefront model, admin-owned margin | `app/Models/Merchant.php`, `reseller_margin_pct` | Three tiers: pending/active/suspended/rejected, standard/v2 |
| Merchant lifecycle | `app/Services/Merchants/MerchantService.php` | apply/approve/suspend/reject/promote, KYB deferred to payout time (BUILD-4 §1) |
| Atomic merchant earnings ledger | `app/Services/Merchants/MerchantEarningsService.php` | `accrue()/hold()/release()/balance()`, `Cache::lock` + `DB::transaction`, idempotent on `reference` — **this is the exact primitive the widget will call on every sale** |
| Three-lane pricing | `app/Services/Pricing/PricingEngine.php` | `merchantEsimPrice()`, `merchantSmsPrice()`, `developerEsimPrice()` — cost → retail → merchant price, MarginGuard-floored at every step |
| Scoped external API layer | `app/Models/ApiClient.php`, `app/Http/Controllers/Api/V1/{Catalogue,Quote,Order}Controller.php`, `ApiScope` middleware | Sanctum-tokenable model (not a `User`), per-route scopes (`catalogue`/`quote`/`order`/`status`), masked `api_orders` table hiding cost + supplier — **this is the exact shape the Widget API will mirror** |
| Payout rails | `payout_accounts`, `payout_requests`, `App\Services\Payouts\*Gateway` | Paystack/Flutterwave/Stripe/PayPal/Cryptomus transfers, KYC-L2 gated |
| Wallet + top-up | `App\Services\Wallet\WalletService`, `CreditWalletJob`, `TopUpIntent` | Idempotent credit on `reference`, USD-locked amounts via `TopUpIntent` when the pay currency isn't USD/NGN |
| Payment gateways | `App\Services\Payments\{Paystack,Flutterwave,Stripe,...}Gateway` | All implement `PaymentGatewayInterface::initialize()/verifySignature()/parseWebhook()` — wallet-funding shaped today (see §5) |
| Gift cards | `GiftCardProduct`, `GiftCardOrder`, `GiftCardPricing`, `ReloadlyGiftCardService` | Retail lane exists; **merchant lane does not yet** (§4) |
| Supplier-name protection | `App\Support\SupplierScrub.php` | Reused unchanged — the widget must never leak a supplier name any more than the retail UI does |
| Security headers | `App\Http\Middleware\SecurityHeaders.php`, `config/security.php` | CSP is `frame-ancestors 'self'` **globally** today — this blocks embedding by design and must be relaxed *only* on the one hosted-checkout route (§5.4), never globally |
| A fully separate "White Label" system | `docs/architecture/WHITE-LABEL-LICENSE-BOUNDARY.md` | **Not this feature.** It deploys a whole second NaaraSim codebase on the merchant's own server, licensed against master. Widgets are the opposite: nothing is deployed anywhere, the merchant keeps their existing site untouched. See §2. |

---

## 2. Explicit boundary: Widgets ≠ White Label — do not merge these systems

Your White Label system (`WhiteLabelInstance`, `WhiteLabelLicenseController`, the whole
child-repo-pulls-updates-from-master model) is for a merchant who wants **their own
independent NaaraSim install** on their own domain and server. It requires a licence
key, a server, a deploy, and update-pulling. It is the heavyweight option.

**Naara Widgets is the lightweight option**, and it plugs into the **existing self-serve
`Merchant` model** (`app/Models/Merchant.php`), not the White Label model. A merchant
never needs a server. They need:

1. To already be an active `Merchant` (or become one — the existing eligibility flow in
   `MerchantService::eligibility()` is unchanged).
2. To generate a **Widget Key** from their existing Merchant Dashboard.
3. To paste one `<script>` tag, or install the Naara WordPress plugin and paste the key
   into a settings field.

If a future request asks to let a White Label *instance* also issue widget keys, that is
a distinct, later decision — flagged, not assumed, in §13.

---

## 3. The three integration methods (all backed by one API + one SDK)

1. **Embed snippet (any website, any CMS).** One `<script src="https://cdn.naara.../widget.js" data-naara-key="wk_live_...">` tag plus a `<div id="naara-widget"></div>` mount point. Works on Shopify, Wix, a hand-rolled HTML page, anything.
2. **WordPress plugin.** A thin wrapper: it enqueues the exact same `widget.js`, gives the merchant a settings screen to paste their key instead of editing template files, and ships a shortcode `[naara_widget type="esim"]` plus a Gutenberg block. No PHP business logic lives in the plugin — it is a *loader*, never a second implementation of pricing/catalogue/checkout. This is the single most important engineering discipline in this whole blueprint: **the WordPress plugin must not duplicate any Naara logic**, or every future pricing/security fix has to be shipped twice.
3. **Existing co-branded storefront link** (`/merchant/{slug}` / invite links) — already built, untouched, still the right answer for a merchant who wants a full page, not a widget.

All three ultimately call the **same** Widget API (§6) with the **same** Widget Key
auth (§5) and land in the **same** `MerchantEarningsService` ledger. There is exactly
one code path from "customer clicked buy" to "merchant got paid" — website vs
WordPress vs future mobile SDK are just different loaders in front of it.

---

## 4. Data model additions (migrations)

All new tables/columns follow the codebase's existing conventions: `foreignId()->constrained()->nullOnDelete()`/`cascadeOnDelete()` as appropriate, `decimal(18,4)` for USD amounts, and a docblock citing this document the way existing migrations cite `ROADMAP §Layer 3`.

### 4.1 `merchant_widget_keys` — **NEW**
The credential a widget/plugin authenticates with. One merchant can have many keys
(one per site is the recommended pattern — a key compromised on one site can be
revoked without breaking the merchant's other sites).

```
id
merchant_id            foreignId → merchants, cascadeOnDelete
label                   string            -- "My WooCommerce store", set by merchant
public_key              string, unique    -- wk_live_xxxxxxxx (safe to expose client-side)
secret_hash             string            -- sha256(secret), the secret is shown ONCE at creation, mirrors token_last_four pattern on ApiClient but a widget key is a CLIENT-SIDE id, not a bearer secret — see §5.1 for why public_key alone is the auth surface, secret_hash is for the WP-plugin's server-side activation ping only
allowed_domains         json              -- ["mystore.com", "www.mystore.com"], no wildcards by default (see §13)
product_scopes          json              -- subset of ['esim','number','giftcard']
theme                   json nullable     -- { "primary_color": "#0A6E6E", "mode": "light" } merchant-set widget theme, always rendered under a "Powered by Naara" mark (mirrors MerchantBranding's existing big-merchant/small-Naara rule)
status                  string            -- active | revoked
revoked_at              timestamp nullable
revoked_reason          string nullable
last_seen_at            timestamp nullable
created_at / updated_at
```

### 4.2 `widget_orders` — **NEW**
Deliberately mirrors `api_orders` (§1 table) exactly: a masked wrapper that links to
the real fulfilment record and is the only thing ever exposed back to the widget/plugin.
**Never expose `esim_orders`/`sms_orders`/`gift_card_orders` rows directly to a widget
key** — same discipline as the Developer API.

```
id
merchant_widget_key_id  foreignId → merchant_widget_keys, nullOnDelete
merchant_id             foreignId → merchants, cascadeOnDelete    -- denormalised for fast ledger joins even if the key is later deleted
buyer_user_id           foreignId → users, nullOnDelete            -- the auto-provisioned guest account, §7
kind                    string            -- esim | number | giftcard
reference               string, unique    -- widget:{public_key}:{uuid}, the idempotency boundary (mirrors api_orders' unique(client, reference))
status                  string            -- pending_payment | fulfilling | completed | failed | refunded
retail_price_usd        decimal(18,4)     -- R, what a normal Naara customer would pay
merchant_price_usd      decimal(18,4)     -- M, what THIS buyer actually paid
currency                string(3)
esim_order_id           foreignId → esim_orders, nullOnDelete
sms_order_id            foreignId → sms_orders, nullOnDelete
gift_card_order_id      foreignId → gift_card_orders, nullOnDelete
result                  json nullable     -- masked delivery payload, same shape api_orders returns
origin_domain           string nullable   -- the Origin header captured at session creation, for the fitness test in §12 and for fraud review
created_at / updated_at
```

### 4.3 `users` — add two columns
```
is_widget_guest   boolean default false   -- auto-provisioned via a widget checkout, never set any other way
signup_channel     string nullable         -- 'widget' | null (existing signup paths stay null — purely additive, nothing existing changes meaning)
```
(`users.merchant_id` already exists from the co-branded storefront work — reused
unchanged for attribution; see §7.2 for exactly how it's set for a widget guest.)

### 4.4 `widget_events` — **NEW**, optional but recommended for §9's analytics tab
Lightweight, high-volume, no foreign-key joins on the hot path (fire-and-forget from
the widget JS via `sendBeacon`) — do not put this on the same table as `widget_orders`.
```
id
merchant_widget_key_id  foreignId → merchant_widget_keys, cascadeOnDelete
event_type              string   -- 'impression' | 'catalogue_view' | 'checkout_opened' | 'checkout_abandoned'
metadata                json nullable
created_at
```
(No `updated_at` — this is an append-only log; keep it out of the money-safety
transaction boundary entirely.)

---

## 5. Security architecture (the part Frank asked to never be cut corners on)

Every item below is a **hard requirement**, not a suggestion. A widget key is, by
definition, a credential that will sit in publicly-viewable HTML/JS on someone else's
website — the whole design must assume the `public_key` **will** leak, and stay safe
anyway.

### 5.1 Why the public key can be public, and what actually gates access
`public_key` alone identifies *which merchant, which product scopes, which domains*.
It is **not** a bearer token — mirror how Stripe's `pk_live_...` is safe to expose
while `sk_live_...` never is. The things that actually stop abuse are:

- **Domain allowlist enforcement, server-side, on every request** — not just at plugin
  activation. Every Widget API call checks the request's `Origin` (primary) and
  `Referer` (fallback) header against `merchant_widget_keys.allowed_domains`. A request
  from an unlisted origin is `403`'d and logged (`Auditor::log('widget.origin_rejected', ...)`),
  never silently allowed even for "just reading the catalogue."
- **CORS is explicit, not `*`.** The Widget API responses set
  `Access-Control-Allow-Origin` to the *matched* allowed domain only (never a
  wildcard, never an unvalidated reflection of the incoming Origin), computed
  per-request after the allowlist check passes.
- **Product scopes are enforced server-side per key**, exactly like `ApiScope` gates
  Developer API routes today — a key scoped to `['giftcard']` gets a `403` on the
  eSIM catalogue endpoint, full stop, mirroring `ApiScope::handle()`'s `abort(403, ...)`
  pattern verbatim.
- **Rate limiting per key**, not just per IP — a compromised/scraped key gets throttled
  independent of how many origins are hitting it. Reuse Laravel's `throttle` middleware
  keyed on `public_key` rather than IP, at a tier stored on `merchant_widget_keys`
  (default e.g. 120 req/min catalogue, 10 req/min checkout-session-create).
- **Instant kill switch** — `revoke()` on `MerchantWidgetKeyService` flips `status` to
  `revoked`; every subsequent request 403s within the request cycle (no cache lag
  tolerated on this check — read-through, not cached, exactly like
  `EnsureApiClientUsable::handle()` checks `usable()` live on every request).
- **No secret ever required client-side.** The WP plugin's `secret_hash` is used
  exactly once, server-to-server, when the plugin "activates" (a WP-side cron/admin
  action pings Naara to confirm the key + domain pairing at install time) — never
  shipped into the page the visitor's browser loads.

### 5.2 New middleware — mirrors `EnsureApiClientUsable`/`ApiScope` exactly
- `EnsureWidgetKeyUsable` (alias `widget.key`) — resolves `public_key` from a header
  (`X-Naara-Widget-Key`, never a query string, to keep it out of server access logs and
  Referer leakage), loads the `MerchantWidgetKey`, confirms `status === 'active'` **and**
  its `Merchant` is `isActive()`, confirms the Origin/Referer against `allowed_domains`,
  stamps `last_seen_at`. Any failure is `403`, logged, never a silent pass-through.
- `WidgetScope` (alias `widget.scope:esim`) — mirrors `ApiScope::handle()` verbatim,
  checking `product_scopes` instead of Sanctum abilities.

### 5.3 Never expose cost or supplier
Reuse `App\Support\SupplierScrub` unchanged. The Widget Catalogue endpoint returns
exactly the same *shape* of masked data `CatalogueController` already returns today
(`id`, `name`, attributes, price, currency) — priced at the **merchant** lane
(`PricingEngine::merchantEsimPrice()`), never the developer or cost lane. `widget_orders`
never stores `cost_price_usd`; it only stores `retail_price_usd` and
`merchant_price_usd`, matching the masking discipline `api_orders` already enforces.

### 5.4 CSP / `frame-ancestors` — scoped, never global
`config/security.php`'s `frame-ancestors 'self'` stays **exactly as-is** for every
existing route. The **hosted checkout page** (§8) is the *only* route in the entire
app that ever needs to sit inside a third-party iframe, and it needs a *different*
`frame-ancestors` value **per request**, computed from the requesting
`merchant_widget_key`'s `allowed_domains` — never `*`. Implementation: the hosted
checkout controller sets its own `Content-Security-Policy` header directly on its
response (Laravel lets a later `header()` call on the response win, or explicitly
exclude this one route from the global `SecurityHeaders` middleware group and apply a
purpose-built `WidgetCheckoutSecurityHeaders` middleware instead) — e.g.
`frame-ancestors 'self' https://mystore.com` for that specific merchant's checkout
session, recomputed fresh every time, never cached across merchants. This is the
single most important "don't leave this behind" item in the whole build: get it wrong
and either (a) no merchant can ever embed a real checkout, or (b) *every* website on
earth can iframe your checkout page. Test this explicitly (§12).

### 5.5 Replay & idempotency
- `widget_orders.reference` is the idempotency key end-to-end, exactly like
  `api_orders`' `unique(client, reference)` — a retried checkout-session-create call
  with the same client-generated idempotency token returns the existing session, never
  a second charge.
- Payment webhook processing is **entirely unchanged** — it already dedupes on
  provider reference (`CreditWalletJob implements ShouldBeUnique`, `WalletService`
  credits idempotently). The widget flow adds a *new consumer* of that already-proven
  pipeline (§7), it does not touch its guarantees.

### 5.6 Fraud & velocity
Reuse the existing pattern from `App\Support\GiftCardFraud` as the template: add a
parallel, narrowly-scoped `WidgetFraud` check — velocity per widget key (checkout
sessions/hour), velocity per buyer email/device fingerprint across merchants, and a
hard per-order face-value cap for first-time guest buyers (admin-configurable) before
KYC ever enters the picture. Flag-and-hold (never silently block) above threshold,
routed to the same admin review surface gift-card fraud already uses if one exists —
Claude Code should confirm the exact review UI when this batch is built rather than
invent a second one.

### 5.7 Audit logging
Every state change — key created, key revoked, origin rejected, checkout session
created, order fulfilled, earnings accrued — goes through `Auditor::log()` exactly like
every other money-adjacent action in this codebase already does. No new logging
mechanism.

---

## 6. Widget API surface (`routes/api.php`, new `v1/widget` group)

Mirrors the existing Developer API group's shape (prefix, middleware stacking,
per-route scope) almost line for line:

```php
Route::prefix('v1/widget')
    ->middleware(['throttle:widget', 'widget.enabled', 'widget.key'])
    ->group(function () {
        Route::get('catalogue', [Widget\CatalogueController::class, 'index'])
            ->middleware('widget.scope:esim,number,giftcard'); // any-of, checked against the request's requested kind
        Route::post('giftcards/{product}/quote', [Widget\GiftCardQuoteController::class, 'store'])
            ->middleware('widget.scope:giftcard');
        Route::post('checkout/sessions', [Widget\CheckoutSessionController::class, 'store'])
            ->middleware(['throttle:widget-checkout']); // tighter tier, §5.1
        Route::get('orders/{reference}/status', [Widget\OrderStatusController::class, 'show']);
    });

// Unauthenticated by widget-key (it IS the customer-facing page) but its OWN
// dedicated CSP — see §5.4. Session token in the URL is short-lived + single-use.
Route::get('checkout/{session_token}', [Widget\HostedCheckoutController::class, 'show'])
    ->middleware(['widget-checkout-headers'])->name('widget.checkout');
```

- `GET /v1/widget/catalogue` — same response shape as `CatalogueController::index()`
  today, priced via `PricingEngine::merchantEsimPrice($plan, $merchant)` /
  `merchantSmsPrice()` / the new `GiftCardPricing::merchant()` (§4.5 below), filtered to
  `product_scopes`.
- `POST /v1/widget/checkout/sessions` — body: `{ kind, product_id, face_value?, idempotency_key }`.
  Creates the `widget_orders` row (`status = pending_payment`), returns
  `{ session_token, checkout_url, expires_at }`. `checkout_url` is the hosted checkout
  page's URL (§8) — the widget JS either redirects the top window to it or opens it in
  the iframe modal, merchant's choice via `theme.mode`/an explicit widget config flag.
- `GET /v1/widget/orders/{reference}/status` — polled by the widget JS after checkout
  (and also pushed via `postMessage` from the hosted checkout iframe on completion, so
  polling is a fallback, not the primary UX) — returns
  `{ status, result }` where `result` is the same masked delivery payload `api_orders`
  already returns (QR/LPA for eSIM, number/code for numbers, code/PIN for gift cards).

### 6.1 Gift card merchant pricing lane — **NEW method, small, additive**
`GiftCardPricing` currently has no merchant lane (confirmed — §1 table). Add, mirroring
`PricingEngine::merchantEsimPrice()`'s exact shape:
```php
public function merchant(GiftCardProduct $product, float $face, Merchant $merchant, bool $log = true): float
{
    return $this->pricing->giftCardMerchantPrice($this->cost($product, $face), $product->provider, $merchant, $log);
}
```
and the matching `PricingEngine::giftCardMerchantPrice()` counterpart to
`giftCardRetail()`, reusing the exact same `resellerMargin($merchant)` private helper
that already backs `merchantEsimPrice()`/`merchantSmsPrice()` — this is a ~15-line
addition to an existing pattern, not new architecture.

---

## 7. Guest checkout & fulfilment — the money-safety core

This is the one genuinely new mechanism in the whole feature, so it is designed to
**reuse your existing, already-proven wallet rails** rather than invent a parallel
payment pipeline. See §13.1 for why this was chosen over a standalone "direct charge"
gateway mode.

### 7.1 The flow, end to end
1. Shopper clicks "Buy" in the widget → `POST /v1/widget/checkout/sessions` creates a
   `pending_payment` `widget_orders` row and returns a `checkout_url`.
2. Shopper lands on the **hosted checkout page** (Naara's own domain, §8), enters an
   email (or logs into an existing Naara account if they have one — see §7.3), picks a
   payment method.
3. **Guest provisioning** (`WidgetCheckoutService::resolveBuyer()`): if the email
   matches no existing user, create one — `is_widget_guest = true`,
   `signup_channel = 'widget'`, `merchant_id = <the widget key's merchant's id>`
   (reusing `users.merchant_id` exactly as the co-branded storefront path already
   does), no password set (a magic-link/OTP login is issued if they ever want to log
   in directly to Naara later — reuse whatever passwordless mechanism already exists
   for the main app; do not invent a second one).
4. The hosted checkout page calls the **existing**
   `PaymentGatewayInterface::initialize($guestUser, $merchantPrice, $currency, meta)`
   — unchanged signature — with `meta = ['purpose' => 'widget_checkout', 'widget_order_reference' => $reference]`
   and a `TopUpIntent` locking the USD amount exactly like a normal top-up does today
   for non-USD/NGN currencies (reused unchanged).
5. Shopper pays. The **existing, unmodified** `PaymentWebhookController` verifies the
   signature and dispatches the **existing, unmodified** `CreditWalletJob`, which
   credits the guest's wallet idempotently on the provider reference — precisely the
   path a normal top-up takes today.
6. **One small additive hook** (§13.1): at the end of `CreditWalletJob::handle()`,
   after a successful credit, check whether `reference` corresponds to a
   `pending_payment` `widget_orders` row (via the `TopUpIntent`/meta linkage from step
   4). If so, dispatch a new `FulfillWidgetOrderJob` rather than leaving the credit
   sitting in the guest's wallet as spendable balance.
7. `FulfillWidgetOrderJob` — inside one `DB::transaction()`, mirroring the existing
   orphan-charge-guard discipline already in `Checkout.php`: debits the guest's wallet
   for exactly `merchant_price_usd` (idempotent on a `widget_debit:{reference}` key),
   calls the **same** `ProviderRouter`/`GiftCardOrderService` fulfilment code the retail
   checkout already calls, and on success:
   - marks `widget_orders.status = completed`, stores the masked `result`;
   - calls `MerchantEarningsService::accrue($merchant, $guestUser, $kind, $retailPriceUsd, $merchantPriceUsd, $reference)` —
     **the exact same call the co-branded storefront checkout already makes** (grep
     `MerchantEarningsService` in `Checkout.php` to confirm the call shape before
     wiring this — it already imports it, per §1 table);
   - on any fulfilment failure, reverses the wallet debit and marks
     `status = failed`, alerting admins exactly like `ProviderRouter`'s existing
     "refunds itself if no provider can fulfil profitably" guard already does — **do
     not write a new failure-handling philosophy, reuse the one that exists.**
8. The hosted checkout page polls `GET /v1/widget/orders/{reference}/status` (or
   receives a `postMessage` push if it's still the active tab) and shows
   success/delivery, then (for the iframe-modal integration method) posts a
   `naara:checkout:complete` message to the parent window so the widget JS can close
   the modal and show a receipt inline on the merchant's own site.

### 7.2 Why this reuses the wallet instead of a parallel "direct charge" path
Building a second charge-and-fulfil pipeline outside the wallet would mean
re-proving every money-safety guarantee (idempotency, webhook-before-trust, orphan-charge
reversal, currency-locking) that `WalletService`/`CreditWalletJob`/`TopUpIntent` already
have tests for. Routing the widget's money through the *same* wallet credit → debit
cycle means the widget inherits those guarantees for free, at the cost of one small
additive hook in one job. This is the trade-off explicitly recorded in §13.1.

### 7.3 A returning shopper isn't forced into a second guest account
If the email entered at hosted checkout matches an existing Naara user (guest or full
account), reuse it — never create duplicate accounts per merchant. `merchant_id`
attribution: a `users.merchant_id` is set **once**, at first-touch, and is never
silently overwritten by a later purchase through a *different* merchant's widget (flag
this to the shopper — "you're already linked to a different Naara partner" — rather
than quietly re-attributing revenue, which would be a merchant-trust problem, not just
a technical one). This mirrors the "permanently linked" language already in
`docs/ROADMAP-PAYOUTS-MERCHANTS-API.md` §3.4 for the invite-link path.

---

## 8. Hosted checkout page

A single Blade view + Livewire (or plain controller, Claude Code's call at build time
based on how much interactivity the payment step needs — `Checkout.php` is the
reference pattern either way), served at `naara.<tld>/widget/checkout/{session_token}`:

- **Co-branded** exactly like the existing storefront: merchant's `logo_url`/`brand_color`
  from `merchants` big and bold, "Powered by Naara" small beneath — reuse
  `App\Support\MerchantBranding` unchanged, don't reinvent the co-brand rule.
- Minimal — email field (or "log in" link for a returning shopper), the one product
  being bought (no browsing the full catalogue here — browsing happens in the widget
  itself), payment method selector driven by whichever gateways are enabled
  (`PaymentGatewayInterface` implementations already registered), a single "Pay" button.
- `session_token` is single-use and short-lived (e.g. 15 minutes), tied to exactly one
  `widget_orders` row — a stale/reused token 404s, never silently re-renders a new
  session.
- Sends `postMessage` events to its `window.opener`/parent frame at each milestone
  (`naara:checkout:ready`, `naara:checkout:complete`, `naara:checkout:error`,
  `naara:checkout:closed`) so the widget JS (§9) never has to poll aggressively.
- Runs under the scoped `frame-ancestors` header from §5.4 — nowhere else in the app.

---

## 9. Widget JS SDK (`widget.js`)

- **Zero dependencies, vanilla JS, one file**, versioned and served from Naara's own
  asset pipeline/CDN with Subresource Integrity so a merchant's `<script>` tag can
  optionally pin `integrity="sha384-..."`.
- Reads config from the tag's `data-*` attributes (`data-naara-key`, `data-product-type`,
  `data-theme-color`, `data-mode="modal"|"redirect"`) — no build step required on the
  merchant's side, works in raw HTML, WordPress, Shopify, anything that can paste a
  `<script>` tag.
- On load: fires an `impression` beacon to `widget_events` (fire-and-forget,
  `navigator.sendBeacon`, never blocks render), fetches `GET /v1/widget/catalogue`,
  renders a lightweight product grid into the mount element using the merchant's
  `theme` colors, always with a small "Powered by Naara" footer link (non-removable —
  matches the co-brand rule in §8, and is also the honest disclosure a shopper deserves
  about who they're actually buying from and who handles support).
- "Buy" click → `POST /v1/widget/checkout/sessions` → opens `checkout_url` either as a
  centred modal `<iframe>` (default) or a full-page redirect (merchant's
  `data-mode` choice) → listens for the `postMessage` events from §8 → on
  `naara:checkout:complete`, closes the modal and renders an inline success/receipt
  state without leaving the merchant's page.
- Content-Security-Policy friendly: no `eval`, no inline `<script>` injection into the
  host page beyond the one mount element, so it doesn't fight a merchant's *own* CSP if
  they have one.
- Fully responsive/mobile-first — most embeds will render inside a WordPress theme's
  sidebar or a narrow mobile viewport.

---

## 10. WordPress plugin (`naara-widgets`)

**Discipline, repeated from §3: this plugin is a loader, not a reimplementation.**

- Standard WP plugin scaffold (`naara-widgets.php` header, `readme.txt`, uninstall
  hook that cleans up its own options only).
- **Settings screen** (`Settings → Naara Widgets`): paste the widget key's
  `public_key` (and, for activation-ping purposes only, the one-time secret shown at
  key creation — sent once, server-to-server, over HTTPS, to confirm the domain
  pairing, then never stored by the plugin in a way JS on the page can read).
- **Gutenberg block** (`naara/widget`) with inspector controls for product type +
  theme, and a matching **shortcode** `[naara_widget type="esim" theme="dark"]` for
  classic-editor sites — both just render the mount `<div>` + enqueue `widget.js` with
  the right `data-*` attributes. No PHP calls anything except `wp_enqueue_script`.
- **Distribution**: given the license-gated, merchant-specific nature of this plugin
  (it's useless without an active Naara merchant account), self-host the `.zip` for
  manual install/update from the Naara admin/merchant dashboard rather than submitting
  to wordpress.org initially — flagged as a decision in §13.3, with wp.org submission
  as a clearly-later option once the plugin is stable and Naara wants broader organic
  discovery.
- **Update mechanism v1**: manual — a "new version available" notice in wp-admin
  linking back to the Naara dashboard download, exactly the pattern many premium WP
  plugins (WooCommerce extensions, etc.) use before standing up a private update
  server. A private update-server (mirroring the existing `WhiteLabelUpdateClient`
  pull pattern, but for the plugin `.zip` rather than a whole codebase) is a natural
  **Phase 2**, not required for launch — flagged in §13.3.
- **WooCommerce is explicitly out of scope for v1** — the plugin renders a *standalone*
  widget block/shortcode, it does not register Naara products as WooCommerce products
  or touch the WooCommerce cart/checkout. That's a materially bigger integration
  (WC product sync, WC order lifecycle hooks) and should be its own later blueprint if
  Frank wants it — noted in §13.3 so it isn't silently assumed in or out.

---

## 11. Merchant Dashboard additions (`app/Livewire/MerchantDashboard.php`)

A new **Widgets** tab, alongside the existing storefront/earnings/payout tabs:

- **Key management** — create (label + domains + product scopes + theme), rotate,
  revoke. The one-time secret is shown once, exactly like how API keys are
  conventionally revealed once elsewhere in the industry — copy it now, it's gone.
- **Per-key analytics** — impressions/catalogue views/checkout-opened/completed,
  rolled up from `widget_events` + `widget_orders`, so a merchant can see funnel
  drop-off per site (mirrors the kind of funnel visibility `PlatformAnalyticsService`
  already provides at the platform level — reuse its aggregation patterns rather than
  writing a new analytics engine).
- **Earnings breakdown by channel** — storefront vs widget vs (for V2 merchants)
  managed clients, using the `source_type` tag `MerchantEarningsService::accrue()`
  already writes on every ledger row (`'source_type' => $sourceType` — the widget flow
  passes `'widget:esim'`/`'widget:number'`/`'widget:giftcard'` so it's filterable
  without a schema change).
- **Copy-paste embed snippet + downloadable WP plugin link**, generated per key, so a
  non-technical merchant never has to hand-write the `<script>` tag.

---

## 12. Admin controls & feature flags

New `WidgetSettings` support class, mirroring `MerchantSettings` exactly:

```php
class WidgetSettings
{
    public const FLAG = 'widgets.enabled';                          // off by default
    public const MAX_KEYS_PER_MERCHANT = 'widgets.max_keys_per_merchant';
    public const CHECKOUT_RATE_LIMIT = 'widgets.checkout_rate_limit_per_min';
    public const CATALOGUE_RATE_LIMIT = 'widgets.catalogue_rate_limit_per_min';
    public const FIRST_TIME_GUEST_CAP_USD = 'widgets.first_time_guest_cap_usd'; // §5.6
    public const WORDPRESS_PLUGIN_DOWNLOAD_URL = 'widgets.wp_plugin_download_url';
    // ...static getValue()/Setting::getValue() accessors, identical shape to MerchantSettings
}
```

Admin screen additions (new `Admin → Widgets` page, alongside the existing
`Admin → Merchants`): global on/off, global rate-limit tiers, a live table of every
`merchant_widget_key` across all merchants with one-click revoke (the platform-wide
kill switch, separate from a merchant revoking their own key), and a fraud-review
queue fed by §5.6's velocity flags.

---

## 13. Flagged decisions & assumptions (stated, not guessed)

**13.1 — Widget purchases ride the existing wallet (credit-then-auto-debit), not a new
parallel "direct charge" gateway mode.** `PaymentGatewayInterface` today is
wallet-funding shaped (`initialize()` starts a top-up, the webhook always credits a
wallet). Two options existed: (a) extend the interface with a genuinely new
"charge-and-deliver" mode that never lands money in a spendable wallet balance, or
(b) reuse the wallet exactly as today and add one small hook that immediately
auto-debits and fulfils right after credit. **This blueprint picks (b)** because it
inherits every existing money-safety guarantee (idempotency, webhook-verify-before-trust,
`TopUpIntent` currency-locking) for the cost of one additive hook in
`CreditWalletJob`, rather than requiring a second, separately-proven payment pipeline.
The one accepted trade-off: for a few hundred milliseconds a guest's wallet technically
holds a spendable balance before the auto-debit job runs. This is judged acceptable
because (i) the job is queued immediately in the same request cycle that created the
credit, (ii) the guest account has no other purchasing surface exposed to it at that
point, and (iii) it mirrors how `TopUpIntent` already briefly separates "money
received" from "balance available" today. If the owner wants this narrowed to zero
window, a synchronous (non-queued) fulfil-on-webhook path is the follow-up — flagged
here, not silently built either way.

**13.2 — A widget guest gets a real, permanent Naara account, not a disposable one.**
This means they can be re-marketed to, they build order history, and if they later
become a direct Naara customer their identity is already known — consistent with how
the co-branded storefront's `users.merchant_id` model already treats merchant-acquired
customers as real platform users. The alternative (fully anonymous, no-account
checkout) was rejected because it would mean building a second identity-less order/
support model outside every existing pattern in this codebase (KYC, wallet, referrals
all key off `User`).

**13.3 — WordPress plugin distribution, update mechanism, and WooCommerce integration
are each independently scoped smaller than they could be, on purpose (see §10).**
Self-hosted `.zip` + manual update notice + no WooCommerce product sync is the v1
scope. Each of those three is a legitimate, larger follow-up project once the core
widget/checkout loop is proven in production — not something to silently fold into
this build and risk under-building all four at once.

**13.4 — Domain allowlist has no wildcard support in v1.** A merchant must list every
exact domain/subdomain a key is used on (`mystore.com` and `www.mystore.com` as two
entries, not `*.mystore.com`). This is deliberately conservative for launch security;
wildcard support (with its own SSRF/subdomain-takeover considerations) is a
follow-up, not a silent default.

**13.5 — Gift card widget checkout only supports providers with a `FIXED` or `RANGE`
denomination model already in `GiftCardPricing::denominations()`** — no new gift-card
provider behaviour is introduced by this feature; it consumes the existing
`GiftCardOrderService` fulfilment path unchanged.

**13.6 — Legal/compliance is unchanged from, and inherits, the existing merchant
reseller model's posture** stated in `docs/ROADMAP-PAYOUTS-MERCHANTS-API.md`'s
cross-cutting requirements: Naara is not a bank, cash-out rides licensed PSPs, and the
reseller economics should be reviewed with a Nigerian/pan-African fintech lawyer before
go-live — widgets don't change that exposure, they add a new front door to the same
already-reviewed-or-to-be-reviewed model.

---

## 14. Test / fitness-test plan (mirrors `WhiteLabelBoundaryFitnessTest`'s discipline)

New `tests/Feature/`:
- `WidgetKeyAuthTest` — wrong/missing key, revoked key, origin not in allowlist, all
  reject with 403 and are audit-logged.
- `WidgetScopeTest` — a `giftcard`-only key 403s on eSIM catalogue/checkout.
- `WidgetCatalogueNeverExposesCostTest` — asserts the response JSON never contains
  `cost_price_usd`, provider name, or any key not already present in
  `CatalogueController`'s existing masked shape (a fitness test, not a feature test —
  fails loudly if a future change accidentally widens the payload).
- `WidgetCheckoutIdempotencyTest` — replaying a checkout-session-create with the same
  idempotency key never creates a second `widget_orders` row or a second charge.
- `WidgetGuestProvisioningTest` — new email creates exactly one user; existing email
  reuses it; a second merchant's widget never silently reassigns `merchant_id`.
- `WidgetOrphanChargeReversalTest` — a fulfilment failure after a successful charge
  reverses the wallet debit and marks the order failed, mirroring the existing
  `ProviderRouter` refund-itself guarantee.
- `WidgetEarningsAccrualTest` — a completed widget order produces exactly one
  `MerchantEarning` ACCRUAL row for `merchant_price − retail_price`, matching
  `MerchantEarningsService::accrue()`'s existing contract, tagged `source_type =
  'widget:esim'` (etc.).
- `WidgetCheckoutFrameAncestorsTest` — **the §5.4 test that must never be skipped** —
  asserts the hosted checkout response's CSP `frame-ancestors` contains *only* the
  requesting session's merchant's allowed domain(s), never `*`, never another
  merchant's domain, and every other route in the app still gets the global
  `frame-ancestors 'self'` unchanged.
- `WidgetPlatformKillSwitchTest` — `widgets.enabled = false` 404s/403s the entire
  `v1/widget` group instantly, mirroring `EnsureDeveloperApiEnabled`'s existing pattern.

---

## 15. Build batches for Claude Code

Each batch is self-contained, ends in a green `vendor/bin/phpunit` run for its own new
tests, and should begin — per your own standing rule — with Claude Code **reading the
live current state of every file it's about to touch**, not assuming this document's
snippets are copy-paste-final. This document is the design of record; the code it
describes (three-lane pricing, `MerchantEarningsService`, `api_orders`'s masking
pattern, `CreditWalletJob`) is the ground truth to re-read at build time.

**BATCH 1 — Foundations.**
Migrations: `merchant_widget_keys`, `widget_orders`, `widget_events`, add
`is_widget_guest`/`signup_channel` to `users`. `WidgetSettings` support class (mirror
`MerchantSettings`). `MerchantWidgetKey` model (fillable, casts, `belongsTo(Merchant)`,
`allowedDomains(): array` helper, `usable(): bool` mirroring `ApiClient::usable()`).
Admin `Setting` seeds for the new flags, defaulted off. Tests: model factory +
`WidgetPlatformKillSwitchTest` scaffold (flag off → routes not yet built, so just
assert the flag resolves correctly).

**BATCH 2 — Security & auth layer.**
`MerchantWidgetKeyService` (create/rotate/revoke, secret generation +
`secret_hash`, unique-key generation mirroring `MerchantService::uniqueSlug()`'s
collision-retry style). `EnsureWidgetKeyUsable` + `WidgetScope` middleware, registered
as `widget.key`/`widget.scope` aliases in `bootstrap/app.php` next to the existing
`api.client`/`api.scope` aliases. Origin/Referer allowlist check + per-request CORS
header computation (§5.1). New `throttle:widget`/`throttle:widget-checkout` rate
limiter definitions. Tests: `WidgetKeyAuthTest`, `WidgetScopeTest`.

**BATCH 3 — Pricing lane completion.**
Add `PricingEngine::giftCardMerchantPrice()` mirroring `merchantEsimPrice()`, and
`GiftCardPricing::merchant()` calling it (§6.1). Confirm (via a fitness test, not
just a manual check) that `merchantEsimPrice()`/`merchantSmsPrice()`/the new gift card
method all route through the same `resellerMargin()`/MarginGuard floor — no new pricing
philosophy, just the missing third lane. Tests: unit tests for the new pricing method
mirroring existing `PricingEngine` test coverage.

**BATCH 4 — Widget Catalogue + Quote API.**
`app/Http/Controllers/Api/V1/Widget/{Catalogue,GiftCardQuote}Controller.php`, mirroring
`CatalogueController`/`QuoteController` almost line-for-line but priced at the merchant
lane and filtered by `product_scopes`. Route group in `routes/api.php` (§6). Tests:
`WidgetCatalogueNeverExposesCostTest`.

**BATCH 5 — Guest checkout & the fulfil-after-credit hook.**
`WidgetCheckoutService::resolveBuyer()`/`createSession()`. `CheckoutSessionController`.
The additive hook in `CreditWalletJob::handle()` (§7.1 step 6) — **read the live file
first, this is a surgical addition, not a rewrite**. New `FulfillWidgetOrderJob`
reusing `ProviderRouter`/`GiftCardOrderService` fulfilment calls exactly as
`Checkout.php` already does, plus the `MerchantEarningsService::accrue()` call.
`OrderStatusController`. Tests: `WidgetCheckoutIdempotencyTest`,
`WidgetGuestProvisioningTest`, `WidgetOrphanChargeReversalTest`,
`WidgetEarningsAccrualTest`.

**BATCH 6 — Hosted checkout page + scoped CSP.**
`Widget\HostedCheckoutController` + Blade view, co-branded via
`App\Support\MerchantBranding` (§8). The purpose-built
`WidgetCheckoutSecurityHeaders` middleware computing per-request `frame-ancestors`
(§5.4) — **the single highest-risk file in this whole blueprint, build and test it in
isolation before wiring anything else to it.** `postMessage` event contract. Tests:
`WidgetCheckoutFrameAncestorsTest` (must pass before this batch is considered done,
not deferred).

**BATCH 7 — Widget JS SDK (`widget.js`).**
Vanilla JS, no build step required to consume, asset-pipeline entry + SRI hash
generation. Catalogue render, checkout modal/redirect, `postMessage` listener,
`sendBeacon` impression tracking into `widget_events`. Manual QA checklist (no
automated browser tests required for v1 unless Claude Code judges Playwright coverage
already exists elsewhere in the repo worth extending — check before assuming).

**BATCH 8 — WordPress plugin scaffold.**
`naara-widgets` plugin: settings screen, Gutenberg block, shortcode, enqueue logic
only (§10 discipline — no PHP business logic). Self-hosted `.zip` packaging script.
Manual-update-notice mechanism. Explicitly **not** in this batch: WooCommerce
integration, self-hosted update server (§13.3).

**BATCH 9 — Merchant Dashboard "Widgets" tab.**
Key management UI in `MerchantDashboard.php`, per-key analytics (reusing
`PlatformAnalyticsService`'s aggregation patterns), earnings-by-channel breakdown
(`source_type` filter on the existing `MerchantEarning` ledger — no schema change),
copy-paste snippet + plugin download link generator.

**BATCH 10 — Admin controls + platform kill switch + fraud queue.**
`Admin → Widgets` page: global flags, per-key revoke table, rate-limit tier config,
`WidgetFraud` velocity checks (§5.6) mirroring `GiftCardFraud`'s shape, fraud-review
queue. Tests: `WidgetPlatformKillSwitchTest` (full, now that the routes exist),
`WidgetFraud` unit tests.

**BATCH 11 (optional, later) — WooCommerce native integration** and/or **private WP
plugin update server**, per §13.3, only if Frank confirms he wants either after the
core loop is live.

---

## 16. Suggested rollout order

1. Batches 1–3 (foundations, security, pricing) behind `widgets.enabled = false` — zero
   user-facing surface, fully internal.
2. Batches 4–6 (API + guest checkout + hosted checkout) — test end-to-end with the flag
   on for exactly one internal test merchant account, real small-value sandbox
   purchases through all three product types.
3. Batch 7 (JS SDK) — embed on one real, low-traffic Supreme Ideas Agency-adjacent site
   first (e.g. a page you control) before it ever reaches an external merchant.
4. Batch 8 (WP plugin) — same controlled rollout, one merchant, before wider release.
5. Batches 9–10 (dashboard polish + admin controls) — can build in parallel with 7–8
   since neither blocks the other.
6. Flip `widgets.enabled = true` platform-wide only after batch 10's fraud queue and
   kill switch are proven live.

---

**Bottom line for the direct question asked:** yes — a merchant's customer buying an
eSIM/number/gift card through a widget on their own WordPress site, with Naara's own
payment gateway collecting the money, the sale recorded straight to that merchant's
account, and the merchant tracking/withdrawing earnings in their existing Naara
dashboard, is fully buildable on top of what's already in this repo. Nothing here asks
you to trust an unproven payment path — every money-moving step either reuses a rail
you've already built and tested, or is a small, explicitly-flagged, narrowly-scoped
addition to one of them.
