# NAARA PROMPT 13 — Organizations, Seats, and Business Workspace Access
**For: Claude Code, working directly against the NaaraSim-main codebase**
**Depends on:** NAARA-PROMPT-11-CLOSE-GAPS-Sept9.md §3 must be merged first. This prompt does not create a new shared-wallet primitive — it generalizes the one Prompt 11 §3 already built (`WalletGroup`/`WalletGroupMember`) rather than duplicating it. Read Prompt 11 §3 in full before starting this one.

**Context for the agent:** This is the foundation prompt for the Naara Business Suite. Every later business-suite prompt (14–19) assumes the models and access pattern built here exist.

**Reconciliation note — read this first, this is the one part of this prompt that changed after Prompt 11's real text was available:** an earlier draft of this prompt specified a brand-new `Organization`/`OrganizationMember` pair. That would have duplicated `WalletGroup`/`WalletGroupMember` from Prompt 11 §3, which already implements a parent-wallet-holder + capped-member spend model (`WalletGroup(owner_user_id, name)`, `WalletGroupMember(wallet_group_id, user_id, spend_cap_usd, spend_cap_ngn)`, plus `WalletTransaction.spent_by_user_id` for attribution). Building both would mean two tables doing the same job. **This prompt extends `WalletGroup`/`WalletGroupMember` instead of introducing a parallel model.** Everywhere below that would otherwise say "Organization," read it as "`WalletGroup`, generalized by this prompt."

**Confirmed before writing this prompt (do not re-investigate):**
- Auth is a single Laravel Fortify guard, one `User` table, `MustVerifyEmail` already implemented, 2FA via `TwoFactorAuthenticatable` already implemented. There is no second guard anywhere except the unrelated `/admin` staff login.
- `spatie/laravel-permission` is already a project dependency (`HasRoles` trait already on `User`), already used for internal staff roles via `StaffService.php` and `Admin/Users.php`. Do not add a second permissions package or hand-roll a role enum.
- `WalletService` (`app/Services/Wallet/WalletService.php`) has these exact methods, all taking a `User $user` as the wallet-holder, no abstraction layer: `debit()`, `credit()`, `creditTopUp()`, `refund()`, `reward()`, `charge(User $user, float $amount, string $currency, Closure $deliver, array $meta = [])`, `reserve(User $user, float $amount)`, `release(User $user, float $amount)`, `reservedUsd(User $user)`, `spendableUsd(User $user)`. None of these accept anything but a `User`. Prompt 11 §3 already committed to not changing this — this prompt holds the same line.
- `VirtualNumber` model's `$fillable` is: `user_id, provider, phone_number, sid, capabilities, monthly_cost, monthly_retail, status, next_billing_date, provisioned_at`.
- `PermanentNumberRouter::provision(User $user, string $country, string $number, string $provider): VirtualNumber` also takes a `User`, not an abstraction.
- By the time this prompt starts, Prompt 11 §3 has already added the spend-check path to `WalletService` (check the group owner's `UserWallet` balance, enforce `spend_cap_usd`/`spend_cap_ngn`, log via `WalletTransaction.spent_by_user_id`) and the "Invite to shared plan" flow off `Account.php`/`Wallet.php`. This prompt builds on top of that, it does not redo it.

---

## §1 — Extend `WalletGroup`/`WalletGroupMember` rather than creating `Organization`/`OrganizationMember`

**Build:**
1. Migration: add to the existing `wallet_groups` table — `kind` (enum: `family`, `business`, default `family` so every row Prompt 11 already created remains valid with zero data migration needed), `business_email` (nullable), `business_email_verified_at` (nullable). Do not rename the table — `WalletGroup` stays `WalletGroup` in code and in the database; "Organization" is a business-suite-facing concept label, not a new table.
2. Migration: add to the existing `wallet_group_members` table — `role` (string, resolved through `spatie/laravel-permission` team-scoped roles, see §2), `invited_by_user_id` (nullable FK to `users`), `status` (enum: `invited`, `active`, `removed`, default `active` so Prompt 11's existing family-plan members remain valid). `spend_cap_usd`/`spend_cap_ngn` already exist from Prompt 11 §3 — reuse them directly as the seat spend cap, do not add a second cap field.
3. Migration: add nullable `wallet_group_id` (FK to `wallet_groups`) to `virtual_numbers`. Nullable so every existing row and every individual-consumer purchase is unaffected — `wallet_group_id IS NULL` means "owned by `user_id` directly, exactly as today." Add this same nullable column to other resource tables only in the prompt that actually uses it (Prompt 14 for call flows, Prompt 16 for shared inbox access) — do not add it speculatively here.
4. `WalletGroup` model gains `kind`, `business_email`, `business_email_verified_at` to its casts/fillable; no new model class is created.
5. `WalletGroupMember` model gains `role`, `invited_by_user_id`, `status`; no new model class is created.
6. Wherever Prompt 11 §3 already added `belongsToMany`/`hasMany` relationships between `User` and `WalletGroup`, those are reused as-is for the business-suite workspace switcher (§4) — no second relationship is added.

## §2 — Roles, via `spatie/laravel-permission` team-scoped permissions (not a new enum)

**Build:**
1. `spatie/laravel-permission` supports team/organization-scoped roles natively (`config('permission.teams') = true`, a `team_foreign_key` column, pointed at `wallet_group_id`). Enable this rather than building a bespoke `role` string comparison — `wallet_group_members.role` is a convenience denormalized column for quick display/filtering, but the actual authorization check (`$user->can('manage_billing', $walletGroup)`) should run through Spatie's team-scoped permission check, exactly like `StaffService.php` already does for internal roles.
2. Define four roles once, seeded the same way existing staff roles are seeded: `owner` (full control, including removing the group and changing the billing owner), `admin` (manage seats, numbers, call flows — everything short of deleting the group), `agent` (use assigned numbers/inbox/dialer, cannot manage seats or billing), `viewer` (read-only — reporting/call logs, no spend capability at all).
3. **A `kind = 'family'` `WalletGroup` (Prompt 11's original shape) has no reason to expose `admin`/`agent`/`viewer` in its UI** — a family plan's simple "owner sets caps, members spend" model is still exactly what Prompt 11 shipped, unaffected by these roles now existing in the schema. The four-role system is surfaced in the UI only for `kind = 'business'` groups. Do not force family-plan users through a role picker they never asked for.
4. Every later business-suite prompt's authorization checks (who can create a `CallFlow`, who can see the shared inbox, who can spend against the group's wallet) route through this same permission check — do not invent a second authorization mechanism in Prompts 14–19.

## §3 — Creating a Business workspace: routing, business-email handling, and why this is not a second login

**Build:**
1. **No new auth guard, no second `User` table, no separate password.** The existing Fortify login/register flow is completely untouched. A person logs in exactly as they do today, with the exact same credentials, regardless of whether they're about to use personal or business features.
2. Add a marketing/entry route — `/business` (or similar) — that can have its own landing page and its own signup-flavored copy ("Create your Naara Business workspace"), but the actual form submission goes through the **same** Fortify registration endpoint as today, and the actual workspace-creation step reuses Prompt 11 §3's existing "Invite to shared plan" infrastructure rather than a new creation flow — this prompt's version simply passes `kind = 'business'` and collects `business_email` where Prompt 11's version passed `kind = 'family'` and skipped it.
3. `business_email` quality gate: if the domain matches a known consumer-email provider list (gmail.com, yahoo.com, outlook.com, hotmail.com, icloud.com, and similar — reuse or build a small denylist), send a verification email and require a click before `business_email_verified_at` is set — the same email-verification pattern `MustVerifyEmail` already implements for `User`, just pointed at a different field on `WalletGroup`. Do not hard-block workspace creation on domain alone; use it as a verification/trust signal, not a gatekeeper.
4. Because `kind = 'family'` and `kind = 'business'` are the same table, a family-plan owner from Prompt 11 could in principle later convert their group to `kind = 'business'` — that's not a required feature for this prompt, but confirm the schema doesn't prevent it, since it's a natural upgrade path this design should not accidentally block.

## §4 — The workspace switcher (this replaces "log in with a different account")

**Build:**
1. On login, if `$user->walletGroups()->exists()`, show a workspace switcher in the primary nav: "Personal" (the existing consumer Naara experience, always available, always the default) plus one entry per `WalletGroup` the user belongs to (label: the group's `name`; a `kind = 'family'` group and a `kind = 'business'` group both appear here, distinguished by icon/label, not by separate mechanisms).
2. Switching sets the active group in session (e.g. `session(['active_wallet_group_id' => $id])`), scoped to that browser session only — it does not change which `User` is authenticated, does not require re-entering a password, and does not affect any other device or tab the person is logged in on elsewhere.
3. While a `kind = 'business'` group is active, the nav and available features change to the business-suite surface (this is where Prompts 14–18's UI lives) — numbers, call flows, and inbox threads shown are filtered to `wallet_group_id = session('active_wallet_group_id')` instead of `user_id = auth()->id()`. Switching back to "Personal" reverts every query to the existing `user_id`-scoped behavior, completely unchanged from today. A `kind = 'family'` group active in this same switcher continues to show Prompt 11's existing simple shared-plan UI, not the business-suite nav — branch the UI on `kind`, not on the presence of a `WalletGroup` alone.
4. Every write action taken while a business group is active (buying a number, spending on a call) resolves the acting `User`'s role/seat cap for that `WalletGroup` (via §2's permission check, and Prompt 11's existing `spend_cap_usd`/`spend_cap_ngn` check) before calling `WalletService` with the group owner's `User` model — never with the acting member's own personal `User`, even though it's their session driving the action. This is exactly the check Prompt 11 §3 already runs for family-plan purchases; the business suite adds the role check in front of it, it does not replace it.

## §5 — Inviting seats

**Build:**
1. An `owner`/`admin` can invite by email — this reuses Prompt 11 §3's existing invite mechanism, adding `role` and `invited_by_user_id` to the `WalletGroupMember` row it already creates, rather than a second invite flow.
2. If the invited email has no existing `User`, the invite leads to the **same** Fortify registration flow as any new signup — after registering, they're attached to the pending `WalletGroupMember` row and land directly in that workspace.
3. If the invited email already has a `User`, they simply accept the invite while logged in — no new account, no new password — and the group now appears in their existing workspace switcher alongside their personal account, any family plan, and any other business group they're already part of.

---

## Acceptance checklist for this prompt

- [ ] No new `Organization`/`OrganizationMember` tables exist anywhere in the migrations — `wallet_groups`/`wallet_group_members` from Prompt 11 §3 were extended, not duplicated
- [ ] `kind = 'family'` rows created by Prompt 11 remain fully functional with zero data migration required (defaults absorb every new column)
- [ ] No new auth guard, no second password system — one `User` table, one login, exactly as before this prompt
- [ ] Roles implemented via `spatie/laravel-permission` team-scoped permissions, surfaced in the UI only for `kind = 'business'` groups
- [ ] All group spend still calls unmodified `WalletService` methods, using the group owner's `User` — no `WalletService` signature changes anywhere, consistent with Prompt 11 §3's own commitment
- [ ] `business_email` is a verification field on `WalletGroup`, never a login credential, never touches the `users` table
- [ ] Workspace switcher changes session-scoped context only — never re-authenticates, never affects other sessions/devices
- [ ] A person with an existing personal account, or an existing family-plan membership, can be invited into a business workspace without creating a second account

Report back before starting Prompt 14: confirm whether `config('permission.teams')` was already false anywhere else in the app in a way that would conflict with enabling team-scoped roles here — that's the one part of this prompt with a real chance of touching existing behavior outside the Business Suite.
