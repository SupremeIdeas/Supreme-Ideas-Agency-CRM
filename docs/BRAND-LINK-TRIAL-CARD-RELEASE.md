# Brand-Growth release notes (master only)

What shipped, how to switch it on, how to check it, how to roll back. Everything here lives only in the master repo.

## Migrations (forward-only, idempotent)
`2026_12_19` guided tours (earlier) / `2026_12_20_create_brand_links_table` / `2026_12_21_add_brand_visibility_to_brand_partners` / `2026_12_22_add_brand_trial_to_brand_subscriptions` / `2026_12_23_create_connection_intents_table` / `2026_12_24_create_follow_reward_starts_table`. Run `php artisan migrate --force` (cPanel: cron-run migrate or the Updater step).

## Scheduler (cron: `* * * * * php artisan schedule:run`)
| Command | When | Purpose |
|---|---|---|
| `n2n:backfill-identity` (alias `naara-id:backfill`) | daily 03:40 | every account has its Naara ID |
| `brand-links:recheck` | weekly Mon 04:20 | live Business links re-checked; a failing link is paused |
| `brand-trials:convert` | every 10 minutes | due trials convert, short wallets retry, 48 h / 24 h / 6 h reminders |
| `brand-subscriptions:bill` | daily 05:45 | monthly billing (never touches a trial) |

Each shows in Admin > System Health > Scheduled jobs once it has run.

## Switches (all default to the safe side)
- Free trial: Admin > Naara Pro trials. **Off** until you enable it.
- Follow-reward gate: Admin > Follow rewards. **On** (press Follow, 8 s return delay, verified email, hourly ceilings).
- Business links: Admin > Business links (resemblance threshold, lists, weekly re-check).
- Router rules: Admin > Who pays? (country allow/deny).
Suggested rollout order: Business links, Business Card (no switch, public once a brand is listed), Connect journey, trial (switch on last), follow rewards (already on).

## Check buttons
`php artisan naara:verify brand` (Naara IDs, links, visibility reasons, trials) and `php artisan naara:verify messaging` (charge matrix). Admin > System Verify runs the same checks.

## What is NOT in this release (honest list, refreshed 2026-10-19)
Built since the first notes: the Conversation Router drives the New chat sheet (route card with who pays and the blocker), the unified `conversation_events` timeline, SMS and call lines inside a Naara thread, "On Naara / Not on Naara" contact labels, `messaging:reconcile` (including the stale-queued alert), the human slug and short code, the unavailable page, share-to-chat card, PDF card, brand analytics, the dashboard connect popup and the welcome message with accept/decline.
Still not built, on purpose or waiting on live accounts: composing an SMS inside the Naara thread (the route card hands over to the Naara Line page so there is one sending pipeline), a PSTN call started from a chat thread with settlement and receipt, and GATE 9 (SMS to a real test number). Legal sign-off is needed for international SMS/calls and for follow rewards under each platform's terms.

## Rollback
Switch the trial off (no new trials; running ones still convert or can be ended in Admin). Follow gate off restores the old behaviour. The tables are additive: leaving them in place is safe. A white-label install never contains any of this.
