# Release checklist: Engineering Blueprint v3 (Phases 1-11)

Forward-only migrations. Take a database backup first (Admin > Backups, or your host's tool). There is no automatic down-migration path you should rely on in production.

## 1. Deploy
1. Put the site in maintenance mode if your host needs it; upload / pull the release (`public/build` is committed, no Node needed on the server).
2. Run `php artisan migrate --force`. On a cPanel host without a terminal use the cron-run migrate or the Updater's migrate step.
3. `php artisan optimize:clear`, then `php artisan storage:link` if the link is missing (Admin > System Verify > Media checks it).
4. Queue workers: restart Horizon (`php artisan horizon:terminate`) or, on cPanel, let the cron queue runner pick up the new code.

## 2. Migrations added by this release (all idempotent seeds, no destructive changes)
| Migration | What it adds |
|---|---|
| `create_master_marketing_video_sections` (master) | marketing video slots |
| `create_blog_reader_events` | privacy-safe share / keep-reading daily counters |
| `add_system_flags_to_more_apps` (master seeds 3 built-in rows) | built-in More apps |
| `create_notification_preferences_and_event_log` | per-person channel choices, quiet hours, once-only delivery log |
| `create_guided_tours` (seeds tours from config) | tours, steps, progress, counters |
| earlier in the series | `sms_opt_outs`, `business_brain_runs`, chat notices, N2N tables (master) |

Nothing here needs a seeder to be run by hand: the tour and built-in-app rows are created by their migrations and healed by `naara:verify`.

## 3. Verify (the owner's "run all" step)
Admin > System Verify > Run all, or `php artisan naara:verify --json`. Expect PASS everywhere except what your environment genuinely lacks:
- `payouts`: FAIL "corridor enabled for a gateway that cannot pay" means a gateway key is missing; add the key or disable the corridor. WARN "not probed in 7 days": press Run all probes.
- `calls`: WARN until a TURN relay is configured (`N2N_TURN_URLS`, `N2N_TURN_SECRET`).
- `email`: WARN until a real mail transport is configured.
- `notifications`: WARN lists events with no trigger in the product yet (reward expiring, claim rejected, line issue). Expected; they are defined and ready.
- `media`: WARN if `upload_max_filesize` is under 8 MB.
Anything else red is a defect: capture the check name and detail from the row.

## 4. Feature-flag rollout order (all default OFF where they spend money or expose a new surface)
1. Notification settings, Guided tours, Blog reader (no flag; safe).
2. NaaraBrain in the Business Suite, N2N calls, Dialer and gift cards: switch on one at a time, run the matching verify area after each.
3. White Label Funnel flags stay off until the owner has funded the Grey wallet and entered the business details.

## 5. Rollback
Redeploy the previous release and leave the new tables in place (they are additive and ignored by old code). Do not drop columns. If a migration failed half way, fix forward: migrations are written to be re-runnable.

## 6. What's new / how to verify (owner checklist)
- Phone numbers, Messages, catalogue: open on a phone-width window, no horizontal scroll.
- Admin > Marketing videos: add a video to a slot, hide it, see the page update at once.
- Blog: open an article, press Share (centred dialog), scroll to the end (Keep reading appears once).
- More menu: "From the blog" block; More Apps shows Business Suite, NaaraBrain and Naara Chat only to people who can open them.
- Sign up with a second email, open the confirmation link in another browser: confirmation page, toast at next sign-in.
- Account > Notification settings: quiet hours and channels save.
- Account > Guided tours: replay Dashboard and Wallet; Admin > Guided tours > Check all tours is green.
