# Grounding report (Engineering Blueprint v3, section 0A), 2026-10-11

Master repo, `main` at the merge of #109 plus branch `claude/graphify-codebase-mapping-715iyi`. Method: file existence by suffix match against `git ls-files`, code read, and one reproduction run of the full suite. Anything not proven is marked **UNVERIFIED**.

## 1. Environment (recorded)
- PHP 8.3.6, Node 22.22.0, Laravel 12, **Livewire v3.8.7**, Alpine 3.15, GSAP 3.15, Capacitor 8.5 (cli, core, android, ios, **splash-screen only**: no Haptics, Share or Push plugin is installed, so "haptics via Capacitor" must fall back to `navigator.vibrate`).
- Baseline: `vendor/bin/phpunit` = **4069 tests, 69600 assertions, green** (run with `ADMIN_PATH=adminmaster`; the `.env.example` default `adminheavens` makes 22 admin-route tests 404, which is configuration, not a bug).
- Forks (same day): 2771 and 2774 tests, one environmental failure each (`SupportVoiceMimeTest`, because ffmpeg is installed on this machine).

## 2. Files the four blueprints name
84 distinct paths checked. **78 exist.** The 6 that do not are things the blueprints ask us to create, not errors:
`resources/js/nx-upload.js`, `resources/js/n2n/gestures.js`, `config/blog_share.php`, `config/tours.php`, `scripts/responsive/audit.mjs` (now created), and `routes/admin.php` (admin routes live in `routes/web.php`; the blueprint's path is wrong).

## 3. Register items: leads confirmed in code
| Issue | Lead | Result |
|---|---|---|
| 31 / 1F | Saved secret reveals its tail | **Confirmed and fixed.** `ProviderKeys::preview` showed the last 4 characters of every key, `MailSettings::passwordPreview` the last 2. Secrets now show a fixed 8-dot mask (no characters, no length). Plain settings keep a short tail. Test `SecretMaskTest`. |
| 1 / 2.0b-1 | `AutomationStatus` iterates a literal provider list | **Confirmed** (`AutomationStatus.php:34`). Other literal lists: `Admin\Payouts::PROVIDERS`, `PayoutRailGuide`, `StatusPage`, `PayoutStatusText`, `NameMatchGate`, `KycAndLimitsGate`, `PayoutRailExtensions::CORE_PROVIDERS`. The verify check `payouts / Payout health lists come from the gateway registry` currently warns and goes green when Phase 2 fixes it. |
| 33 / 2.0b-3 | `RailAdvisor` collapses reasons into `temporarily_unavailable` | **Confirmed** (3 call sites, lines 80, 93, 101). |
| 5 | Which environment produced the "no provider configured" screenshots | **UNVERIFIED** (needs the live site: tinker `gatewayFor`, `PayoutEnvGuard::allows`). |
| 4 (gestures) | Swipe-to-reply and friends absent | **Confirmed absent.** The only swipe code is `swipeBack()` in `resources/js/n2n/chat.js:94`, which honours `[data-no-swipe]` (that is where the marker comes from). |
| Brand blueprint | "No trial concept exists" | **Confirmed**: no trial field or logic in `Services/Brands`, `BrandSubscription`, `BrandPartner`. |
| Brand blueprint | `setupComplete()` = name + category + image + >=1 handle | **Confirmed** (`BrandSubscriptionService.php:166`). |
| Brand blueprint | `brand-subscriptions:bill` daily 05:45 | **Confirmed** (`routes/console.php:157`). |
| Brand blueprint 2.2 | Public Suffix List | **Not installed.** No PSL package in `vendor/`. The blueprint's own instruction applies: bundle a pinned copy of the ICANN section with its licence (MPL 2.0) and `verified_on`. |
| Brand blueprint 5 | QR library | **Present**: `bacon/bacon-qr-code` 3.1.1 and `endroid/qr-code` 6.0.9. No new dependency needed. |
| Brand blueprint 9 | Phone normalisation | **Present**: `giggsey/libphonenumber-for-php-lite`. |
| Provider blueprint | Voice is Twilio-only | **Confirmed and extended** (Telnyx TeXML voice now exists; the in-browser dialer is still Twilio-only). See `docs/WL-SAFE-UPDATE-AND-LIVE-PROVIDER-CHECK.md`. |
| Provider blueprint 3.1 / 3.2 | Telnyx messaging profile and webhook token missing | **UNVERIFIED in this pass**, scheduled with the provider work. |

## 4. Not done in this pass (so nothing is claimed)
`php artisan route:list --json` duplicate-name audit, `migrate:status` against production, Playwright reproduction of screenshots 01 to 10 (they need the live data and a signed-in session), `phpstan`, and per-gateway probes. Each is listed in the phase it belongs to.

## 5. Phase 0 delivered
Browser error beacon (`/_diag/client-error`, `client_errors`, scrubbed, no IP, 5 per fingerprint per hour), `php artisan naara:verify {area?} {--json}` with stored results and a daily schedule, 7 real checks plus honest "not built yet" rows for the 7 areas whose phase has not run, Setup Health panels (System checks, Browser errors), the `fix.*` flags (`FixFlags`, all OFF), and `scripts/responsive/audit.mjs`. Tests: `Phase0DiagnosticsTest` (7), `SecretMaskTest` (2).
