# NAARA PROMPT 21: Skin access, trials, unlocks, journeys, goals and mood presets

**For:** Claude Code, working in the NaaraSim repo.
**Depends on:** `NAARA-PROMPT-20-SURFACE-SYSTEM-BLUEPRINT-rev11.md` (appearance engine, 35 skins, accents, custom accent, dials, `user_appearance`, `AppearanceResolver`). Read Prompt 20 §13 to §22, §37, §38 first. This document adds the **access layer** on top of it.
**Visual references:** the wireframes from Prompt 20 show skins and the Appearance page. They do **not** yet show locks, trial banners, unlock sheets or the admin Access tab (see §17: wireframe follow-up).
**Status of facts:** everything in §2 was read from the repository. Everything marked **VERIFY** must be confirmed in code before you rely on it. Do not guess table columns.

---

## 0. How to work (repository rules that apply here)
Follow `CLAUDE.md` exactly: one batch at a time; update `PROGRESS.md` after each (DONE list plus exact next step); no emoji, SVG sprite icons only (add missing icons to `resources/views/partials/icon-sprite.blade.php`, ids prefixed `i-`); dark mode on every element; loading states and disabled buttons on every money or state-changing action; browser-verify at mobile and desktop widths after `npm run build`; admin pages live behind `{adminGateway}` and no admin URL appears in user markup (S25). White-label discipline: run `git show HEAD:<file>` on any file you port to a fork; do not assume parity. Money safety: every debit or credit goes through `WalletService` / `CreditService` with an idempotency reference.

## 1. Decisions already made by the owner (locked)
1. **Free skins (always available to everyone):** Naara Surface, Neo, Glass, Dot-grid, Clay.
2. **Every other skin is Pro and locked by default**, including all skins added after this list. Admin can flip any skin between free and pro in the admin panel (flag, not hard-coded).
3. **Unlock routes for a Pro skin:** (a) an active **Naara Pro** subscription whose plan tier covers the skin, (b) completing a **goal, journey or task** that grants that skin, (c) an admin grant, (d) an admin-scheduled free window (seasonal), (e) a one-time **trial**.
4. **Trial rule:** any Pro skin set by admin as trial-enabled can be tried **once per user per skin, for 1 hour**, in real time. After the hour the dashboard returns to the free skin the user was using before. There is **no cooldown and no second trial** of the same skin. It stays locked until the user unlocks it by subscription or by the goal. (This replaces the earlier "30 minute lock" idea.)
5. **Naara Pro = the user's active brand listing subscription** (the "List my brand" plan). The **higher the plan, the more skins** the user may switch between at any time.
6. **Accents:** Naara Teal is free. **All other preset accents and the custom colour are Pro features, only for users with an active Naara Pro plan.** **Goals, journeys and tasks never unlock accents.**
7. **Lapse:** subscribers keep their Pro skins and accents while the subscription is active. When the renewal debit fails because the wallet is insufficient, **the dashboard immediately returns to a free skin and the free accent.**
8. **Mood presets:** active Naara Pro users get a few one-tap mood presets that switch skin (and accent) according to mood.
9. **Goals pack:** Claude Code creates a large default set of journeys, goals and tasks (credits and skin rewards), builds any feature needed to validate them, wires the unlock logic, and keeps all of it **editable by admin after launch**.

## 2. Verified facts about the current codebase
| Area | What exists | Consequence |
|---|---|---|
| Journey goals | `App\Models\JourneyGoal` (fields: title, description, metric, target, period_type lifetime/monthly/quarterly/yearly/campaign, starts_at, ends_at, reward_credits, audience all/merchant/merchant_v2, icon, image_path, is_active, created_by) and `JourneyGoalClaim` (journey_goal_id, user_id, period_key, achieved_value, credits_granted, reference) with a unique index on (goal, user, period) | The engine already pays **credits only**. Extend it; do not build a parallel system. |
| Goal engine | `App\Services\Journey\JourneyGoalService`: `METRICS` constant (esim_purchases, number_purchases, countries_reached, total_spend_usd, referrals_made, credits_earned_lifetime, checkin_streak_days, merchant_clients_connected, merchant_invoices_paid); `evaluate($user)`, `progress()`, private `claim()` (creates the claim, calls `CreditService::earn()` with reference `journey_goal:{id}:{user}:{period}`, sends `JourneyGoalUnlockedNotification`); a new metric = one `case` in `metricValue()` plus one label | Add metrics and the skin grant inside this class. The class comment states the contract; keep it. |
| When goals are evaluated | `EvaluateJourneyGoalsJob::dispatch($user->id)` is called only from `GetNumber` (number purchase), `Checkout` (eSIM purchase), `MerchantClients`, and `Rewards` | New metrics need new dispatch points (§9) plus a nightly sweep. |
| Journey page | `App\Livewire\Journey` with tabs `milestones` (the user's NaaraCredits earning history, **not** achievements), `travel`, `goals`; goals are computed only when the Goals tab is open | Goals tab is where skin-reward chips appear. |
| Default goals | `journey_goals` table migration exists (`2026_09_04_115048`); **no default goals are seeded anywhere** | The default pack is net new. |
| Admin goals page | `App\Livewire\Admin\JourneyGoals`, plus `Admin\Credits` | Extend these pages; do not create a second goals admin. |
| Credits | `App\Services\Credits\CreditService`: `earn`, `spend`, `spendWithdrawable`, `rewardReferral`, `checkIn`, `canCheckIn`, `grantOnce`, `balance`; ledger `CreditLedger`; idempotency by reference; 100 credits = $1; `CreditSettings::enabled()` master switch; `DailyCreditCap` caps Hunt claims only | Skin-only rewards need no credits. Credit rewards must keep references unique. |
| Existing earn actions | Daily check-in (`Rewards`), Hunt (`SocialFollowClaim`, `BrandVideoWatchClaim`, `AdRewardView`), referrals (`Referral`, `ProcessReferralRewardJob`) | Reuse them as task sources (§9). |
| Brand subscription | `BrandSubscriptionPlan` (name, price_usd_per_month, handles_included, guaranteed_followers_per_handle_per_month, video_previews_allowed, credit_reward_per_follow, is_active, sort_order) and `BrandSubscription` (brand_partner_id, plan_id, status `active|past_due|cancelled`, started_at, cancelled_at, next_billing_at, last_charged_at, grace_reminders_sent) | **The subscription is keyed to a `BrandPartner`, not directly to a user.** Pro entitlement for a user = the user owns a `BrandPartner` whose subscription is `active`. Resolve through `BrandPartner->owner`. |
| Billing | `brand-subscriptions:bill` (`BrandSubscriptionsBillCommand`) debits the wallet monthly with reference `brand-sub:{id}:{Y-m}`; on `InsufficientBalanceException` the private `pause()` sets the subscription `past_due`, the listing `paused`, increments `grace_reminders_sent`, and notifies with `BrandBillingReminderNotification`; on success it sets `active` and advances `next_billing_at`. `BrandSubscriptionService::subscribe()` debits the first month before creating the row; `cancel()` exists | These are the exact hooks for §8. |
| Scheduler | `routes/console.php` uses `Schedule::command(...)` (queue worker each minute, `virtual:renew` daily 04:00, etc.) | Register new commands here. |
| Feature gating | `FeatureLocks` and `FeatureEntitlements` gate **white-label** features and are **inert on the master** and **fail open** on forks | Do not reuse them for skin access; they answer a different question. Copy only the fail-open posture (§11). |
| Settings, audit | `Setting::getValue/setValue`, `App\Support\Auditor`, `BottomNav`, `NavSlot` | Use for flags and audit lines. |
| Notifications | `JourneyGoalUnlockedNotification`, `BrandBillingReminderNotification`, bell via `notifications` | Extend/add (§12). |
| Prompt 20 objects (planned, may not exist yet) | `user_appearance`, `appearance_presets`, `AppearanceResolver`, `UpdateUserAppearance`, `Admin\UserAppearance` | If Prompt 20 batches are not merged yet, build them first; this document modifies them. |

## 3. Access model
**Effective skin = the user's requested skin if allowed, else the fallback.** "Allowed" means any of:
1. the skin is free (`appearance_presets.access = free`), or
2. it is inside an **admin free window** (`free_from <= now <= free_until`), or
3. the user holds a **skin unlock** row for it (goal, admin or promo), not revoked and not expired, or
4. the user's **Pro tier** is >= the skin's `min_plan_tier`, or
5. the user has an **active trial** for it (`ends_at > now`).
**Fallback:** `user_appearance.last_free_skin_key`, else the platform default skin.
**Accents:** requested accent is allowed if it is `teal` (free) **or** the user has an active Pro entitlement (any active plan). Otherwise the effective accent is the platform default accent. The saved custom hex is never deleted; it simply is not applied while the entitlement is missing.
**Dials, mode, and the quick sheet are free for everyone** (assumption; see §18 Q3).
**The resolver is the only enforcement point.** Both the UI and `UpdateUserAppearance` call `SkinAccessService`; the server never trusts the client's idea of what is unlocked. Because the layout renders the *effective* skin on every request, a lapsed subscription or expired trial takes effect on the next page load even if no listener ran.

## 4. Data model (new migrations, all reversible, none touch money tables)
Names assume Prompt 20 used `2026_10_01_100000_*`; use later dates.
1. `add_access_columns_to_appearance_presets` on `appearance_presets`: `access` string(8) default `pro` (`free|pro`), `trial_enabled` bool default 1, `trial_minutes` smallint nullable (null = global setting), `min_plan_tier` tinyint nullable (null = not obtainable by plan), `unlockable_by_goal` bool default 1, `free_from` / `free_until` datetime nullable. Applies to `kind=skin`; for `kind=accent` only `access` is used.
2. `add_skin_tier_to_brand_subscription_plans`: `skin_tier` tinyint unsigned default 1. Seeded by rank (lowest `sort_order` = 1, clamp to 3, never above the number of active plans). Admin-editable per plan.
3. `create_user_skin_unlocks_table`: `id`, `user_id` FK cascade, `skin_key` string(24), `source` string(12) (`goal|admin|promo`), `source_ref` string(64) nullable (e.g. `journey_goal:12`), `granted_at`, `expires_at` nullable, `revoked_at` nullable, `granted_by` nullable, unique(`user_id`,`skin_key`,`source`).
4. `create_user_skin_trials_table`: `id`, `user_id` FK cascade, `skin_key`, `started_at`, `ends_at`, `status` (`active|expired|converted`), `previous_skin_key` nullable, **unique(`user_id`,`skin_key`) = once per skin, ever**.
5. `add_access_state_to_user_appearance` on `user_appearance`: `last_free_skin_key` nullable, `last_pro_skin_key` nullable, `last_pro_accent_key` nullable.
6. `create_mood_presets_table`: `id`, `key` unique, `label`, `skin_key`, `accent_key` nullable, `enabled`, `sort`, `min_plan_tier` tinyint default 1.
7. `extend_journey_goals_for_rewards` on `journey_goals`: `reward_type` string(8) default `credits` (`credits|skin|both`), `reward_skin_key` nullable, `category` string(12) default `goal` (`journey|goal|task`), `verify_mode` string(8) default `auto` (`auto|claim|manual`), `system_key` string(48) nullable unique (seeded defaults), `admin_edited_at` datetime nullable, `cta_route` string nullable (existing route name the user should visit), `sort` smallint default 0.
8. `add_skin_unlock_to_journey_goal_claims`: `skin_key_granted` nullable on `journey_goal_claims` (audit of what a claim granted).
Seeders: `SkinAccessSeeder` (§6), `MoodPresetSeeder` (§7), `JourneyDefaultsSeeder` (§10). All idempotent (`updateOrCreate` on the natural key); the journey seeder never overwrites a row with `admin_edited_at` set.

## 5. Services and objects
- `App\Services\Appearance\SkinAccessService`
  - `proTier(User): int` (0 = none): active `BrandSubscription` where `brandPartner.owner_id = user` and `status = active` (and, per §18 Q2, the cancelled-but-paid-through case); returns the max `plan.skin_tier`.
  - `canUse(User, string $skinKey): array{allowed: bool, via: string|null}` implementing §3.
  - `availableSkins(User)`: for the picker, each skin with state `free|unlocked|tier|trial_active|trial_available|trial_used|locked` and a human reason.
  - `startTrial(User, skinKey)`: transaction, row lock on the user, refuses if not trial-enabled, already trialled (unique index), already allowed, or user is a guest; writes `previous_skin_key`, `ends_at = now + minutes`; sets the requested skin. Returns remaining seconds.
  - `expireTrials()`: marks expired and reverts users (idempotent); `reconcile(User)`: if the stored skin/accent is no longer allowed, switch to the fallback and remember `last_pro_*`; dispatches `SkinAccessChanged`.
  - `grantUnlock(User, skinKey, source, ref)`, `revokeUnlock(...)`, both audited when source is `admin`.
- `AppearanceResolver::for($user)` (Prompt 20) calls `canUse` and returns **effective** values plus a `locked_reason` and `trial_ends_at` for the UI banner. Cache per user id (key `appearance:user:{id}`); forget on any access change.
- `UpdateUserAppearance` validates every requested skin/accent/custom hex against `canUse` (and Pro for accents) and returns the effective values, never an error that leaks which skins exist behind the lock.
- Events and listeners: `SkinAccessChanged(user)` -> forget cache, optionally notify. Dispatch from `JourneyGoalService::claim` (skin goals), `BrandSubscriptionsBillCommand::pause` and the success path, `BrandSubscriptionService::subscribe` and `cancel`, admin grant/revoke, and the trial commands.
- Commands (register in `routes/console.php`): `skin-access:expire-trials` every minute with `withoutOverlapping()`; `journey:evaluate-sweep` nightly (see §9); `skin-access:reconcile-all` weekly as a safety net.

## 6. Defaults for access (seeded; every value admin-editable)
**Free:** `surface`, `neo`, `glass`, `dotgrid`, `clay`. **Recommendation to the owner (§18 Q1): make `clarity` free too**; accessibility should not sit behind a paywall. Until the owner decides, seed `clarity` as Pro and surface the question.
**Trial:** every Pro skin `trial_enabled = 1`, minutes from `Setting 'appearance.trial_minutes'` (default 60).
**Plan tiers (`min_plan_tier`)**, 30 Pro skins in three tiers (tier 3 = top plan; if there are fewer than 3 active plans, the top plan receives all remaining tiers):
| Tier | Skins |
|---|---|
| 1 | Calm, Vivid, Ledger, Daybreak, Paper, Noir, Handset, Receipt, Pop, Canopy |
| 2 | + Aurora, Halo, Boarding Pass, Chipset, Wavelength, Airmail, Harmattan, Ankara, Danfo, Aso-oke |
| 3 | + Adire, Blueprint, Clarity (if still Pro), Neon Lagos, Passport, Golden Hour, Vault, Prism, Nebula, Tide |
**Accents:** `teal` free; `ocean, violet, emerald, rose, graphite` Pro; custom accent Pro (`appearance.custom_accent_access = pro`). Any active plan unlocks all accents. Goals never grant accents (enforced in `SkinAccessService`, not by convention: a goal with `reward_skin_key` pointing at an accent key must fail validation).
**Free windows:** admin can set `free_from/free_until` on any skin (for example Independence Day, Detty December); the Appearance page shows a "Free until ..." tag.

## 7. Mood presets (Naara Pro only)
Seed (all editable): **Focus** -> Blueprint + teal; **Chill** -> Tide + ocean; **Hype** -> Neon Lagos + rose; **Cozy** -> Harmattan + emerald; **Night** -> Noir + graphite; **Travel** -> Passport (country accent). Each preset has `min_plan_tier`; a chip is shown only if the user's tier covers its skin, otherwise it is hidden (not shown locked, to avoid clutter). Chips appear on the Appearance page and the quick sheet for active Pro users only. Tapping a chip calls `UpdateUserAppearance` with the preset's skin and accent. Presets never grant access.

## 8. Subscription lifecycle (grounded in the real billing code)
1. **Subscribe** (`BrandSubscriptionService::subscribe`, first month debited): after the transaction commits, dispatch `SkinAccessChanged`. Show "Pro skins unlocked" and take the user to the Appearance page.
2. **Renewal success** (`brand-subscriptions:bill` success path): no change; ensure `grace_reminders_sent` reset (already done).
3. **Renewal failure** (`pause()` on `InsufficientBalanceException`): **immediately** call `SkinAccessService::reconcile($owner)` which (a) stores `last_pro_skin_key` and `last_pro_accent_key`, (b) sets the skin to `last_free_skin_key` or the platform default, (c) resets the accent to the platform default, (d) forgets the cache, (e) sends `SkinAccessLostNotification` ("Your Naara Pro payment did not go through. Top up your wallet to bring your skins back.") with a wallet top-up link. This is in addition to the existing billing reminder; do not remove or change any billing, listing-pause or priority behaviour.
4. **Recovery** (next successful bill after `past_due`): do **not** auto-restore silently. Show a one-tap prompt "Switch back to {last_pro_skin}?" the next time the user opens Appearance (default; see §18 Q4).
5. **Cancel** (`cancel()`): inspect what the method does to the listing and to `next_billing_at`. If the code keeps the listing live until the paid period ends, entitlement ends at `next_billing_at`; otherwise it ends immediately. Implement exactly that and document which it was (§18 Q2).
6. **Safety net:** because the resolver recomputes entitlement on every render, a missed listener can never leave a lapsed user on a Pro skin for longer than one page load; the weekly `reconcile-all` cleans stored state.
7. **Never** change wallet, listing, priority or billing behaviour from the skin code. The skin layer only reads subscription status.

## 9. Journeys, goals and tasks: engine extension
**Rewards.** In `JourneyGoalService::claim()`, after the claim row is created: if `reward_type` is `credits` or `both`, call `CreditService::earn` (existing); if `skin` or `both`, call `SkinAccessService::grantUnlock(user, reward_skin_key, 'goal', "journey_goal:{id}")` and store `skin_key_granted` on the claim. Unlocks from goals are **permanent** unless admin sets `expires_at` or revokes (default; §18 Q7). Notification text names the skin.
**Metrics to add** (one `case` each in `metricValue()` and one label each in `METRICS`). Source tables exist as models in the repo; **VERIFY column names and success states before writing each query.**
| Metric key | Source (model) | Notes |
|---|---|---|
| `orders_total` | `EsimOrder` + `SmsOrder` + `GiftCardOrder` | count of successful orders |
| `topups_completed` | `TopUpIntent` | successful only |
| `voice_calls_made` | `VoiceCall` | only if Twilio feature active |
| `messages_sent` | `OutboundMessage` | |
| `contacts_added` | `Contact` | |
| `gift_cards_bought` | `GiftCardOrder` | feature-gated; admin can retarget |
| `virtual_numbers_owned` | `VirtualNumber` | active or past_due |
| `kyc_verified` | `KycVerification` | 1 when approved |
| `social_follows_claimed` | `SocialFollowClaim` | |
| `videos_watched` | `BrandVideoWatchClaim` + `AdRewardView` | |
| `api_keys_created` | `ApiClient` | |
| `port_in_completed` | `PortInRequest` | |
| `push_enabled` | `PushSubscription` | 1 if any |
| `account_age_days` | `users.created_at` | |
| `brand_listing_active` | `BrandSubscription` | 1 if active |
| `wallet_shared_members` | `WalletGroupMember` | |
| `coupons_redeemed` | `CouponRedemption` | |
| `email_verified` | `users.email_verified_at` | **VERIFY** the app uses it |
| `two_factor_enabled` | **VERIFY** where 2FA state is stored (see `SecurityCenter`, `TwoFactorNotification`) | build a reader if absent |
| `profile_complete` | define as: name, phone, country, currency set | **new** definition in one helper `ProfileCompleteness` |
| `esim_installed` | **VERIFY** whether `EsimOrder` records activation/installation | if no signal exists, do not ship the goal; instead propose the smallest signal |
| `skin_trials_started` | `user_skin_trials` | meta goal that rewards exploring Appearance |
**Dispatch points.** `EvaluateJourneyGoalsJob::dispatch($user->id)` currently runs from `GetNumber`, `Checkout`, `MerchantClients`, `Rewards`. Add dispatches (debounced per user, for example `Cache::add("jg:eval:{id}", 1, 30)`) after: top-up success, referral recorded, KYC result, call logged, message sent, contact added, profile saved, 2FA enabled, push subscribed, gift card order, brand subscription start, Hunt claims, coupon redeemed, trial started. Add `journey:evaluate-sweep` nightly that chunks active users (last login within 90 days) and dispatches the job, so missed events still pay out. **Idempotency is already guaranteed** by the unique claim index plus `CreditService` references; do not add a second guard.
**Verify modes.** `auto` = metric reaches target; `claim` = user taps "Claim" and the server re-checks the metric (for tasks the user must do outside the app, such as follow claims that already have a flow); `manual` = admin approves from a queue (use sparingly; only for tasks no table can verify). Never mark a task `auto` without a real data source.
**Where it shows.** Journey -> Goals tab: each goal shows its reward chip (credits and/or a skin preview thumbnail), progress, and a "Go" button to `cta_route`. A skin-reward goal also appears in the skin's "How to unlock" sheet (§12).

## 10. Default pack (seed all of it; admin can edit, disable or delete any row)
`JourneyDefaultsSeeder` creates rows with `system_key`. Credit sizes below are starting values; admin edits freely. The lifetime credit total of all non-skin defaults must stay at or below **3,000 credits ($30)** per user; the seeder logs the computed total.
**A. Pro-skin unlock goals (reward = skin only, lifetime):**
| Skin | Goal | Metric >= target |
|---|---|---|
| Calm | Settle in | `checkin_streak_days` >= 3 |
| Vivid | Make your first order | `orders_total` >= 1 |
| Ledger | Keep it topped up | `topups_completed` >= 3 |
| Daybreak | Early riser | `checkin_streak_days` >= 7 |
| Paper | Complete your profile | `profile_complete` >= 1 |
| Aurora | Spread the glow | `referrals_made` >= 1 |
| Halo | Bring the crew | `referrals_made` >= 5 |
| Noir | Lock it down | `two_factor_enabled` >= 1 |
| Adire | Verified and rooted | `kyc_verified` >= 1 |
| Blueprint | Build with the API | `api_keys_created` >= 1 |
| Clarity | (recommend free) if Pro: complete profile and verify email | `profile_complete` >= 1 |
| Neon Lagos | Keep the city talking | `messages_sent` >= 25 |
| Passport | Go places | `countries_reached` >= 3 |
| Golden Hour | Two weeks strong | `checkin_streak_days` >= 14 |
| Vault | Big spender | `total_spend_usd` >= 250 |
| Boarding Pass | Frequent flyer | `esim_purchases` >= 5 |
| Chipset | Own a line | `virtual_numbers_owned` >= 1 |
| Wavelength | Number collector | `number_purchases` >= 5 |
| Airmail | Stay in touch | `contacts_added` >= 10 |
| Handset | Old-school caller | `voice_calls_made` >= 10 |
| Prism | Credit collector | `credits_earned_lifetime` >= 1000 |
| Receipt | Treat someone | `gift_cards_bought` >= 1 |
| Nebula | Thirty-day streak | `checkin_streak_days` >= 30 |
| Harmattan | Three months in | `account_age_days` >= 90 |
| Ankara | Join the community | `social_follows_claimed` >= 3 |
| Danfo | Share the ride | `wallet_shared_members` >= 1 |
| Aso-oke | One year of Naara | `account_age_days` >= 365 |
| Tide | Watch and earn | `videos_watched` >= 10 |
| Canopy | Stay in the loop | `push_enabled` >= 1 |
| Pop | Play with colour | `skin_trials_started` >= 1 |
**B. Credit journeys and tasks** (reward = credits, a few `both`): first top-up (+20), first eSIM (+30), first number (+30), first referral (+50), countries 1/3/5/10/20 (+10/+25/+50/+100/+200), spend $10/$50/$100/$250/$500 (+10/+30/+60/+120/+250), streak 3/7/30/100 (+10/+20/+60/+200, recurring variants monthly), referrals 1/3/10/25 (+0 extra, existing referral reward still applies; these grant a badge-style `both` with a skin), credits earned 100/500/2,000, verify email (+10), enable 2FA (+20), complete profile (+20), enable push (+10), follow Naara on socials (existing Hunt flow, task), watch 5 videos (task), first call (+20), first message (+10), five contacts (+10), KYC verified (+50), merchant ladder for `merchant` and `merchant_v2` audiences (clients 1/5/20, invoices paid 1/5/20), monthly goal "3 orders this month" (recurring, +30), campaign goals for seasons (admin creates, `period_type = campaign`).
**C. Subscription goals:** `brand_listing_active` >= 1 grants **no skin** (the plan itself does); it can reward credits as a welcome bonus.
Every row has `category`, `icon` (existing sprite ids), `cta_route` (existing route names only; **VERIFY each against `routes/web.php`**), `sort`, `is_active`.

## 11. White-label and forks
Skin access touches the user wallet, the brand subscription and journeys. Decide per `CLAUDE.md` before porting: default **on the master: tiered access as specified**; **on a white-label fork: `appearance.access_mode = all_free`** (all skins and accents free, no trials, no locks) unless the operator enables tiered mode, mirroring the fail-open posture of `FeatureEntitlements`. Read the fork's `BrandSubscription*` code with `git show HEAD:` first; if the fork lacks brand subscriptions the tiered mode must stay unavailable there, not error.

## 12. User interface (additions to Prompt 20 §18)
- **Appearance page, skin cards:** badge states **Free**, **Unlocked**, **Pro** (lock icon), **Trying (mm:ss left)**, **Free until {date}**. Tapping a locked skin opens a bottom sheet (existing modal engine, `x-ui.modal` rules) titled with the skin name and a live preview, with three sections: **Try for 1 hour** (button, shows "already used" if consumed), **Unlock with Naara Pro** (shows the minimum plan name and a button to `brand.get-listed`), **Or earn it** (the goal title, progress bar and Go button). Locked skins must still preview inside the sheet (the scoped mini preview from Prompt 20).
- **Trial banner:** while a trial is active, a slim non-dismissible bar **inside the content area** (not header, not bottom nav) reads "Trying {skin}: {mm} left" with "Unlock" and "Switch back" buttons. Server supplies `ends_at`; the client countdown is cosmetic and re-checks with a Livewire poll every 30 seconds and at zero. At expiry the page reverts and shows a toast.
- **Accents:** non-teal swatches and the Custom swatch show a lock for non-Pro users; tapping opens the same sheet variant ("Accent colours are part of Naara Pro", no goal route, no trial).
- **Mood chips** (Pro only, §7) above the skin list.
- **Journey page:** goals with a skin reward show a skin thumbnail; a new filter "Skins" lists all skin-reward goals.
- **Account/profile:** a small "Naara Pro" status line (active, past due, or not subscribed) linking to List my brand.
- **Notifications:** `SkinUnlockedNotification` (via the extended journey notification), `SkinTrialEndingNotification` (in-app toast at 10 minutes and 1 minute, not a bell spam), `SkinAccessLostNotification` (lapse), all with i18n keys in `lang/en|fr/appearance.php`.
- **Admin, Admin -> User appearance -> Access tab** (route `admin.user-appearance`, same page as Prompt 20 §19): table of all skins with free/pro, trial on/off, trial minutes, min plan tier, unlockable-by-goal, free window dates, enabled; accents table; plan -> `skin_tier` editor (reads the real plans); mood presets editor; global settings (trial minutes, custom accent access, access mode); a **user lookup** to grant or revoke a skin or view a user's trials and unlocks (all writes audited with `Auditor`).
- **Admin, Journey goals page:** extend `Admin\JourneyGoals` with reward type, reward skin picker (Pro skins only), category, verify mode, CTA route picker, sort, a "System default" badge, "Reset to default" (restores the seeded values, clears `admin_edited_at`), a preview of the user-facing card, and a computed credit liability line.

## 13. Integrity and abuse rules
- **One trial per user per skin, ever:** unique index plus a transaction with a row lock; server time only.
- **No client trust:** trial end, entitlement and unlocks are computed server-side on every render; client timers are cosmetic.
- **Idempotent grants:** unlock rows are unique per (user, skin, source); goal claims already unique; credit grants keep their reference.
- **Account churn:** new accounts get new trials; acceptable. Optional later: tie trials to `KnownDevice` to rate-limit. Not required now.
- **Guests:** no trials, no unlocks, platform defaults only.
- **Impersonation:** read-only appearance (Prompt 20 §17.4); starting a trial or granting an unlock while impersonating is refused.
- **Admin grants** are always audited and show the granting admin.
- **Cache:** per-user keys only; forget on any access change; authenticated HTML is never shared-cached.
- **Skin code never writes** to wallet, brand, listing, billing or credit tables.

## 14. Tests (add to the existing suite)
`SkinAccessServiceTest` (free, window, unlock, tier, trial, fallback order), `SkinTrialTest` (once per skin enforced by DB, 60 minute expiry, revert to previous free skin, no second trial, concurrency double-tap), `SkinSubscriptionLapseTest` (past_due via `pause()` reverts skin and accent immediately; recovery prompt; cancel semantics), `AccentAccessTest` (teal free, others Pro, goals cannot grant accents, custom blocked without Pro, saved hex retained), `PlanTierTest` (tier by plan rank, fewer than 3 plans), `JourneySkinRewardTest` (claim grants the skin once, credits and skin both, idempotent re-evaluation), `JourneyDefaultsSeederTest` (idempotent, respects `admin_edited_at`, credit total <= 3,000), `JourneyMetricTest` (each new metric against factory data, including the feature-gated ones off), `MoodPresetTest` (visible only to covered tiers, applies skin and accent, never grants access), `AccessIsolationTest` (user A's unlocks and trials never affect B; cache keys), `AdminAccessTest` (permissions, audit lines, cannot make the platform default skin Pro), `ForkModeTest` (`all_free` mode). Re-run Prompt 20's `SkinContrastScanTest` with locked-state UI added.

## 15. Rollout batches (each shippable alone; update `PROGRESS.md` each time)
- **U1 Data and gating (no UI):** migrations 1 to 8, seeders for access flags, `SkinAccessService`, resolver and `UpdateUserAppearance` enforcement, commands and schedule, tests.
- **U2 Appearance UI:** locked states, preview sheet, trial start and banner, expiry revert, accents locks.
- **U3 Subscription tie-in:** tier column and admin editor, subscribe/cancel/bill hooks, lapse flow and notifications, recovery prompt.
- **U4 Goal engine extension:** reward types, skin grants, claim audit, notification text, Journey UI chips.
- **U5 Default pack and metrics:** new metrics, dispatch points, nightly sweep, `JourneyDefaultsSeeder`, admin goals page upgrades.
- **U6 Mood presets, admin Access tab, user lookup, free windows.**
- **U7 Polish:** i18n (en, fr), contrast scan, mobile checks, docs.

## 16. Acceptance checklist
- [ ] Surface, Neo, Glass, Dot-grid, Clay are free for everyone; all others lock for non-entitled users in the picker, in saved preferences and in `UpdateUserAppearance`.
- [ ] A Pro skin can be tried once, for 60 minutes (admin-editable), then reverts to the previous free skin; a second attempt is refused with a clear message.
- [ ] Goal-granted skins stay unlocked; completing a goal never unlocks an accent.
- [ ] Active Naara Pro unlocks all accents, the custom colour, and every skin up to the plan's tier; a higher plan unlocks more.
- [ ] Wallet-insufficient renewal (via the real `brand-subscriptions:bill` path) returns the user to a free skin and the free accent on the next render and notifies once; billing behaviour is unchanged.
- [ ] Mood chips appear only for active Pro users and only for skins their tier covers.
- [ ] Default pack seeded; every row editable and resettable; admin edits survive re-seeding.
- [ ] Every new metric has a verified data source; no goal is `auto` without one.
- [ ] Fork mode defaults to `all_free` and never errors when brand subscriptions are absent.
- [ ] No admin URL appears in user markup; no emoji; icons from the sprite; `PROGRESS.md` updated.

## 17. Wireframe follow-up (not built yet)
The existing wireframes do not show: locked skin cards, the unlock sheet, the trial banner, Pro-only accent locks, mood chips, the lapse prompt, the Journey skin chips, or the admin Access tab. After the owner approves this blueprint, build one wireframe file ("V16 Access") that adds them to the V9 engine, then hand both to Claude Code. Until then treat §12 text as the specification.

## 18. Open questions for the owner (each has a default so work can start)
1. **Clarity** (accessibility preset): free instead of Pro? *Default: Pro until decided; recommended free.*
2. **Cancel behaviour:** does a cancelled subscriber keep Pro until the paid period ends? *Default: follow what `cancel()` does to the listing today and document it.*
3. **Dials and mode:** free for everyone? *Default: yes.*
4. **After a failed renewal is fixed:** auto-restore the old Pro skin or ask? *Default: ask with a one-tap prompt.*
5. **Which plan names/tiers exist?** *Default: derive tiers from active `BrandSubscriptionPlan` rank.*
6. **One trial at a time?** *Default: starting a trial on another skin ends the current one (it stays consumed).*
7. **Goal unlocks permanent?** *Default: permanent; admin may set an expiry.*
8. **Credit liability ceiling:** *Default 3,000 credits ($30) per user across lifetime non-skin goals.*
9. **Do white-label forks get tiered mode?** *Default: `all_free`.*
10. **Promo codes that unlock a skin** (reuse of `Coupon`/`CouponRedemption`, or a new table)? *Default: out of scope; the `source = promo` value is reserved.*

## 19. What was verified and what was not
**Verified by reading the repository:** the journey goal model, claim model, engine, metrics list, claim and idempotency logic, evaluation dispatch points, the Journey page tabs, the absence of seeded default goals, the credit service API, the brand subscription plan and subscription models, the monthly bill command and its insufficient-balance `pause()` path, the scheduler file, the white-label feature-gating classes and their fail-open behaviour, the notification classes, the model list used as metric sources, and the existing admin pages for goals and credits.
**Not verified (VERIFY in code):** every column name on the metric source models, success states and feature gates for each new metric, where two-factor state lives, whether eSIM installation is recorded, the exact behaviour of `BrandSubscriptionService::cancel()`, the seeded plan names and count, whether `BrandPartner` exposes `owner_id`/`owner` as assumed, and whether Prompt 20's objects already exist. Do not skip these checks.
