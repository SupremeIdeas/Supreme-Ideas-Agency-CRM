# Go-live runbook: deploy and live setup (MASTER, owner-run)

These steps change your live server, so they are yours to run (this development environment has no access to it). Each step names the exact command or screen and what "good" looks like. Do them in this order. Forks (white labels) are not touched by anything here.

## 0. Before you start
1. Take a database backup (Admin > Backups, or your host's tool). Migrations are forward-only.
2. Make sure `.env` has real values for what you intend to switch on (provider keys, mail transport, payment gateway keys). Secrets never go in chat or in the repo.
3. Rotate what was exposed earlier: regenerate the NOWPayments keys that were pasted in chat, regenerate the HeroSMS key, and change the seeded test members' passwords.

## 1. Deploy the release
1. Maintenance mode if your host needs it, then pull or upload the release (`public/build` is committed; no Node needed on the server).
2. `php artisan migrate --force`
3. `php artisan optimize:clear` and, if missing, `php artisan storage:link`.
4. Restart workers: `php artisan horizon:terminate` (VPS) or let the cron queue runner pick up the code (cPanel). The scheduler line must exist: `* * * * * php artisan schedule:run`.
5. Run Admin > System Verify > Run all (or `php artisan naara:verify --json`). Expect PASS except what your environment genuinely lacks (see `docs/RELEASE-CHECKLIST.md` section 3).

## 2. Seeds that a live install needs once (all idempotent)
| Command | What it gives you |
|---|---|
| `php artisan db:seed --class=WhiteLabelLicensePlanSeeder --force` | The White Label plans the funnel sells (Basic, Medium, Extended, Extended v2). Check the prices in Admin > White Label before switching the funnel on. |
| `php artisan db:seed --class=BrandPlanSeeder --force` | The Naara Pro plans (one catalogue shown in the brand wizard, dashboard and pricing). |
| `php artisan naara:seed-brands` (add `--dry` to preview) | The owner brands, including Whyte Nation's albums. |
| `php artisan db:seed --class=ProviderLoginSeeder --force` | The provider login manager rows (Admin > More > Provider logins). |
| `php artisan db:seed --class=EsimImageSeeder --force` | The 118 country and 9 region eSIM photos. |
| `php artisan handbooks:import database/seeders/handbooks --missing-only --publish` | The handbook catalogue (51 drafts plus the finished titles). The scheduler also runs it hourly, so this is only needed to land titles at once. |

## 3. Enable the White Label funnel (switches are OFF by default)
1. Load your receiving accounts from a seed file kept OUTSIDE the repository: `php artisan wl:seed-manual-accounts --file=/path/to/manual-payment-accounts.seed.json --dry-run`, then without `--dry-run`. Bank accounts load enabled, crypto loads OFF.
2. Admin > White Label invoices: set your legal seller details, number formats and terms, and preview a sample PDF.
3. Make sure a fresh rate exists for GBP, EUR, USDT and USDC (live Grey rate or a manual rate newer than the allowed age).
4. Send a small test transaction on each crypto network, and confirm it in Admin > White Label payments (a crypto account cannot be enabled before that).
5. Admin > Feature switches: turn on `wl_funnel` first, place a test purchase end to end on a phone (wallet payment), then turn on `wl_manual_payments` (USD and GBP first, crypto after its test, EUR after its own test). `wl_custom_orders` and `wl_preview_unlock` stay OFF until you want them.
6. `php artisan naara:verify wl` must be green. The funnel's scheduled tasks (`wl-funnel:expire`, `wl-invoices:retry`, `wl-secrets:purge`) run from the normal scheduler line.
Details: `docs/WL-FUNNEL-RELEASE.md`.

## 4. Handbooks
1. New and updated manuscripts ship in `database/seeders/handbooks/` as `{ID}.epub` and `{ID}.pdf`. The hourly job runs `handbooks:import ... --missing-only --publish --refresh-files`: a draft takes a changed file directly, and a PUBLISHED title gets a new unpublished edition that you review and publish in Admin > Handbooks (readers keep the current edition until you do).
2. Titles stay drafts until their checklist passes (EPUB, cover, back cover, price). Set each price in Admin > Handbooks.
3. To add a manuscript yourself (EPUB or Word with images, preview, batch image resize, PDF), use Admin > Handbooks > Manuscript studio.

## 5. Things only you can supply
- Monty Tenant ID, Sender ID and the server IP to whitelist.
- Funded provider balances for the real $5 order tests (eSIM Go, Quibity, Zendit, numbers providers).
- Any further provider credentials (put them in `.env` or the credential slots in Admin, never in chat).
- Upload Nia's real avatar in Admin > Support assistant (the setup wizards pick it up).
- A photo credits line in the footer or About page that links to `docs/ESIM-COUNTRY-PHOTO-CREDITS.md` (about 25 countries still lack eSIM photos).

## 6. Rollback
Redeploy the previous release and leave the new tables in place (additive, ignored by older code). Do not drop columns. If a migration stopped half way, fix forward; they are written to be re-runnable.
