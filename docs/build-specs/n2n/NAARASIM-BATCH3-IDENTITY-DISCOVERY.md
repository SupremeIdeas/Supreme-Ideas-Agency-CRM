# NaaraSim — Batch 3: Identity & Discovery
### Master repo only. Foundation for everything in N2N. Shared rules: Master Blueprint §6. For Claude Sonnet + Graphify.

**Depends on:** nothing. **Unblocks:** B5, B6, B8, B9, B10.

## 0. Findings from the codebase (verify, don't re-derive)
- `users` has **no `username` and no `naara_id`** (nothing in `database/migrations` or `app/Models/User.php`).
- `users.phone` exists but **nothing verifies it**. Treat every existing `phone` value as *unverified claim*, never as a linked number.
- `Contact` = `user_id, name, phone_number, is_favorite`, with `Contact::normalizePhone()`. No link to a registered user.
- Signups come from Fortify **and** Google OAuth (`google_id`), so username assignment must work for both paths.
- **Check:** is a libphonenumber-grade library already in `composer.json`? If not, add one (say why) instead of hand-rolling E.164 parsing across countries.

## Phase A — Naara ID and username
1. `users.naara_id`: unique, immutable, generated, non-sequential, non-guessable (e.g. `NR-` + Crockford base32 + check character so typos are caught). Backfill every existing user in a chunked, resumable command.
2. `users.username`: unique, case-insensitive, 3–20 chars `[a-z0-9_.]`, change cooldown and reserved list as **admin settings** (`Setting::getValue`), not constants. Reserved list must include `admin, support, naara, nia, staff` and **existing brand names** (protect brand owners from squatting).
3. Onboarding: prompt for a username after signup (both Fortify and Google paths); auto-suggest from name; existing users get a one-time "claim your username" prompt.
4. Verification: two users cannot claim the same name in any casing; concurrent claim race handled by the unique index (catch the exception, return a friendly error).

## Phase B — Verified phone link
1. New `user_phones`: `user_id, e164, country_code, verified_at, discoverable (bool)`, **unique on verified e164** (one number → one account). Do not overload `users.phone`.
2. New `phone_verifications`: hashed code, attempts, expires_at, sent_count, ip. OTP via the platform's **existing SMS provider** (Master Blueprint D3), with per-user/day, per-number/day and per-IP caps. Code expiry ≤ 10 min, attempt lock-out.
3. Re-link / change number: re-verification required; old number released; conflict handling when a number is already linked elsewhere (support-assisted release, audit-logged).
4. Verification: cannot link a number you cannot receive an SMS on; brute-force lock-out works; two accounts cannot hold the same verified number; cost per activation is measured and reported (do not guess the SMS price).

## Phase C — The identity resolver (Primitive P1)
1. `IdentityResolver::resolve(string $input, string $purpose): ResolveResult`. Detects: email, phone (any local format → E.164 using the user's country as default), `@username` or bare username, Naara ID.
2. `purpose = search` respects the target's discovery settings (`discoverable`, email discovery, "hide me"); `purpose = deliver` is server-side only and never reveals to the caller whether a hidden account exists.
3. Returns a small DTO (id, display name, avatar, match type). **Never** returns email/phone of the target.
4. Anti-enumeration: per-user and per-IP rate limits, uniform response shape/timing for hit vs miss, no bulk endpoint.
5. Registered-check helper used by B5 (share credit), B6 (send), B10 (call). One code path, never duplicated.
6. Verification: same input in five formats resolves to the same user; a hidden user is unreachable via `search` but valid for `deliver`; enumeration attempt is throttled.

## Phase D — Contact linking and import
1. Add `contacts.linked_user_id` (nullable, indexed) + `linked_at`. Queue job `LinkContactsJob` matches normalized contact numbers to **verified, discoverable** `user_phones`; re-run when a number is verified or removed.
2. Import: Android-Chrome Contact Picker API where available; **vCard/CSV upload** everywhere else (iOS Safari has no picker). Capacitor contacts plugin is a later, optional path.
3. Consent screen before any import; a "delete imported contacts" control; rate-limit imports.
4. Honest privacy note in the PR: hashing phone numbers client-side gives weak protection (small number space), so real protection = consent + rate limits + discoverability toggle.
5. "Known" flag on a contact = `linked_user_id IS NOT NULL` (feeds the Known/Unknown filter in B6).
6. Verification: importing 500 contacts links only verified+discoverable numbers; unlinking a phone unlinks contacts within one job cycle.

## Phase E — Settings and admin
1. Account settings: username, Naara ID (copyable), phone link/unlink, discovery toggles (by phone, by email), "hide me from search".
2. Admin: lookup by Naara ID/username/phone, force-rename, release a phone. Put it inside the existing users/account admin screen (no new top-level nav), with `AuditLog` entries.

## Constraints
- OTP endpoints are the most abusable surface in this batch: rate limit, log, and never echo whether a number is registered.
- No E.164 or contact data in URLs or logs.
- Erasure: unlink phones, null contact links, release username on account deletion (`docs/ACCOUNT-ERASURE.md`).

## Deliverable
Per-phase verification results, measured OTP cost per activation, and the list of existing users backfilled with any collisions resolved.
