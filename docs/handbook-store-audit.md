# Naara Travel Handbook Store: Phase 0 audit (master only)

Read-only inventory of what exists and where, written before any handbook code (blueprint section 0.3). Checked against the committed `main` of the master repo on 2026-10-12.

## Reused as is

| Need | What exists | Where | Notes |
|---|---|---|---|
| Atomic, locked, idempotent wallet debit and refund | `WalletService::debit()` / `refund()`, idempotent by `meta['reference']` | `app/Services/Wallet/WalletService.php` | The wallet is USD, so list prices are USD. A repeated reference returns the first row and never moves money twice. |
| Admin revenue | `PlatformEarningsService::accrue($amount, $sourceType, $reference, $description)` | `app/Services/Platform/PlatformEarningsService.php` | One global bucket, whole amount is admin revenue, idempotent on reference. This is the same single routing point White Label sales use, so handbook revenue is accrued here with source type `handbook`. |
| "Naara Pro" entitlement | `NaaraPro::active($user)` (brand owner with an active or trialing subscription, or a lifetime Pro grant) | `app/Services/N2N/Media/NaaraPro.php` | Decision D8: reuse. A Pro "term" is the brand subscription row (`sub:{id}`) or the lifetime grant (`grant:{brand id}`). |
| Private file storage | `MediaStorage::privateDisk()` (Wasabi, R2 or local private) | `app/Support/MediaStorage.php` | Handbook files live only on the private disk and are served through entitlement-checked, signed, short-lived routes. Nothing handbook-related is ever on the public disk. |
| Audit trail | `Auditor::log()` | `app/Support/Auditor.php` | Every admin change. |
| Admin alerts | `AlertAdminJob` | `app/Jobs/AlertAdminJob.php` | Failed unlock hook, failed upload validation, audio storage errors. |
| Notifications | `Notify::event()` with rows in `config/notification_events.php` (master-only rows in `config/n2n_notification_events.php`) | `app/Support/Notifications/Notify.php` | Receipt, unlock, new edition, continue-reading nudge. |
| Scheduler health | `SchedulerHealth::TASKS` | `app/Support/SchedulerHealth.php` | The new daily job registers a heartbeat entry. |
| Master-only guard | `MasterOnlyModules` path tokens + each fork's `WhiteLabelBoundaryFitnessTest` | `app/Support/MasterOnlyModules.php` | `handbook` is added as a token so no handbook file can reach a fork. |

## Hook points

- **eSIM order success:** there is no domain event. `EsimOrder` rows are created directly in four places (`Livewire/Checkout`, `MerchantClientService`, `MerchantWidgetFulfilmentService`, `Api/V1/OrderController`). The unlock hook therefore listens to the `EsimOrder` model's `created` event from a master-only service provider, queues the work (a failure never fails the order) and resolves the country from `EsimPlan::countries` / `coverage_type`. Merchant and API orders are not the buyer's own, so only orders whose `user_id` is a member who bought for themselves unlock (Checkout path).
- **Country pages:** the eSIM catalogue pages are Livewire (`catalogue`); the "Travel handbook for {country}" card is a partial included from them.
- **Admin menu:** `components/layouts/admin.blade.php` builds the menu in groups; Handbooks goes under "Store & pricing". `AdminNavReachabilityTest` forces every admin page to be reachable from the menu or explicitly exempt.

## Not present (built here)

- No EPUB reader. The GodsWill sibling platform was not available to inspect from this environment, so the reader is built on `epubjs` (added as an npm dependency, bundled by Vite).
- No service worker or PWA layer in the web app (the Capacitor wrapper has none). The minimum needed for offline reading is built (a handbook-only service worker plus encrypted IndexedDB cache).
- No EPUB validator on the server. `epubcheck` needs Java, which shared cPanel hosting does not have, so validation is done in PHP (zip structure, mimetype, container, package document, spine, navigation, encryption and size checks). `epubcheck` can be added later as an optional extra.
- No PDF stamping library. Watermarking the EPUB is done in PHP (a licence page and metadata are added to the package). PDFs are stamped with `setasign/fpdi` when the file can be parsed by its free reader; when it cannot, the download is served unstamped, the event is logged and the admin is alerted (see the report).

## Decisions taken (defaults from the blueprint)

D1 price bands are admin-entered per title. D2 regional or global eSIM: the buyer picks one handbook from the covered countries. D3 three Pro slots per term, kept while Pro is active, locked (not deleted) on lapse and restored on renewal. D4 no extra Pro discount. D5 offline re-validation every 30 days. D6 tax and app-store rules are not guessed in code (web store first). D7 no automatic refunds; refund requests go to a human queue. D8 Pro equals the existing brand-listing entitlement.
