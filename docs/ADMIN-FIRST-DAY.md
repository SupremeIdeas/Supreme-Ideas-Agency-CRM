# Admin first day (master platform)

After `php artisan naara:bootstrap` the platform runs with safe, documented defaults for every setting, a starter journey ladder, draft coupons and a complete notification catalog. You do **four things** that need a login somewhere else, then **review** (not build) the rest. Open **Admin > System > Setup health** at any time to see exactly what is done and what is waiting.

## The four manual tasks
1. **Cron.** Add one entry (cPanel Cron Jobs, or `crontab -e` on a VPS):
   `* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1`
   Setup health shows "Cron is running" once the first heartbeat arrives. Every scheduled job already writes a heartbeat.
2. **API keys.** Admin > API keys. Paste credentials for the providers you use, in this order of impact: a payment gateway, one eSIM provider, one number/SMS provider, then the rest. A feature that needs a key shows "Coming soon" until its key exists; nothing half-runs. `php artisan naara:credentials:status` lists every slot; add `--urls` for the webhook and OAuth callback addresses to paste into each provider's console (built from `APP_URL`, so set that to your https domain first).
3. **Email.** Admin > Email settings: choose any transport (SMTP, Postmark, SES, Resend...), paste its credentials, press **Send test**. Sender name, footer, templates and the notification matrix are already filled in; no template depends on the transport.
4. **Social login.** Admin > API keys > Social login: client id/secret per provider. Redirect address: `{APP_URL}/auth/{provider}/callback` (shown in Setup health). A provider's button should only appear once it is configured.

## Review these (they are defaults, not market facts)
| What | Where | Why look |
|---|---|---|
| Margins and floors | Admin > Pricing; `docs/PRICING-DEFAULTS-REPORT.md` | eSIM default margin is ~23% on retail; floors are cost + $0.50 (eSIM), +$0.01 (SMS), +$0.02/min (voice) |
| Coupons | Admin > Coupons | WELCOME10 and COMEBACK15 are live; FIRSTESIM and four seasonal coupons are **drafts** (inactive) |
| My Journey goals | Admin > Journey goals | 21 starter goals, **inactive**. Max a user can earn: 5,000 credits cap (`journey.reward_budget_credits`) |
| AI support autopilot | Admin > Support agent | Ships **off** (deliberate difference from the code default); turn on when ready |
| Notification defaults | `config/notification_catalog.php` | Transactional/security/billing on; marketing off and consent-gated |
| Real costs | Settings flagged "DEFAULT, tune" | N2N message/storage/call prices and any AI token prices must come from your real invoices |
| Legal text | Admin > Legal | Drafts, need counsel per market. Tax rates are **not** invented; add yours in Tax rates |

## Commands
| Command | What it does |
|---|---|
| `naara:bootstrap [--dry-run] [--only=step]` | Adds missing defaults. Never overwrites an edited value, never invents a credential |
| `naara:bootstrap:verify` | CI-safe. Fails on an unclassified setting, a notification missing from the catalog, an uncovered webhook route, an unsafe discount floor |
| `naara:bootstrap:audit` | Regenerates `docs/BOOTSTRAP-AUDIT.md` (census of settings, notifications, scheduler, features, credential blocks) |
| `naara:bootstrap:registry --merge` | Appends newly discovered Setting keys to `config/bootstrap/settings.php` for you to curate |
| `naara:bootstrap:pricing-report` | Regenerates `docs/PRICING-DEFAULTS-REPORT.md` |
| `naara:credentials:status [--urls]` | Credential slots and the generated webhook URLs |
| `naara:preflight` | Config Doctor; non-zero exit only on a critical failure |

## Seed the owner brands (after install, master only)
Run `php artisan naara:seed-brands` once on the live server (use `--dry` first to preview). It creates the Naara, Supreme Ideas Agency, Whyte Nation Music and Essential Farm listings with logos, covers, social handles, links and lifetime Naara Pro, adds Naara's official follow handles to the first spot of the rewards Hunt, fills any EMPTY footer social links, and removes the four old demo listings. Re-running never overwrites an edit, a changed password or a footer link you already set.

New member accounts it creates start with their own email address as the first password. Change each one after its first login (or reset it from Admin > Users). Edit any listing from the member's own Brand dashboard, and change a Pro term (lifetime or 1 year) under Admin > Brand Directory > Grant Pro.

## White-label note
Everything here is **master-only** (path-guarded by `MasterOnlyModules`). Worth proposing to white-label children later, once you decide: `naara:preflight`, the credential/webhook URL sheet and `naara:credentials:status` (all generic, no oversight content). Not proposed: the journey ladder or draft coupons (they encode master business choices).

## Not built yet (be aware)
- A real no-spend **Test** button per provider (slots show only "waiting" / "credentials saved", not "verified").
- Email Studio templates for all 50 notification classes (6 exist; the catalog records the rest), `naara:mail:preview-all`, and the user-facing notification preferences screen.
- Announcement/popup drafts, legal-page drafts and FAQ additions (Bootstrap phases 5, 9).
- Journey goals for features without metrics yet (username, contacts, N2N, Business, Brain).

## Provider logins

Admin > More > Provider Logins is your reminder of where and how you sign in to each supplier (login link, Google or email and password, which email). It never stores passwords. After install, open it once and edit each provider you have keys for: the ones marked "Default, please confirm" are guesses. On a server that was installed before this page existed, run `php artisan migrate` then `php artisan db:seed --class=ProviderLoginSeeder`.
