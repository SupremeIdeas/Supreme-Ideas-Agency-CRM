# NaaraSim — Announcements Upgrade, Shared-Wallet Live Notification, and Business KYC Automation
### Three findings, grounded in direct code inspection. Master repo unless stated. For Claude Sonnet + Graphify.

---

## Phase A — Announcement system: dual styles, image support, sitewide deep-link library

### Confirmed findings
`app/Models/Announcement.php` exists with a real send pipeline
(`BroadcastAnnouncementJob`, `BroadcastAnnouncementNotification`,
admin-managed via `app/Livewire/Admin/Announcements.php`) — but its
`$fillable` is `title, body, icon, cta_label, cta_url, coupon_code,
audience, recipients, status, created_by, sent_at`. **There is no image
field, no style/template selection, and only a single free-text CTA
pair** — no deep-link picker. Everything requested here is genuine new
work, not a misconfiguration of something that already exists.

### A1 — Two selectable presentation styles
Add a `style` field (`'banner_hero'` | `'dark_feature'`, extendable) to
`Announcement`:
- `banner_hero` — mirrors the first reference screenshot: a wide image
  banner up top (should reuse the exact same aspect ratio/dimensions
  already established for the home-dashboard hero banner, so admins get
  a familiar, already-correctly-sized upload target rather than a new
  dimension to learn), a bold headline and body below it, and a single
  prominent primary-color CTA button.
- `dark_feature` — mirrors the second screenshot: a dark, premium
  "what's new" card with a smaller inset preview image/QR-style visual,
  bullet-point feature callouts, and a lighter-weight secondary link
  ("view all changelogs") alongside the primary CTA.
Store both an `image_path` (for `banner_hero`, sized to the dashboard-hero
dimensions) and a distinct `feature_image_path` (for `dark_feature`, a
different, smaller aspect ratio appropriate to an inset preview) — don't
force one image field to awkwardly serve two different visual purposes.

### A2 — Sitewide deep-link CTA library
Replace the single free-text `cta_url` with a **picker** backed by a
maintained list of every real in-app destination, so an admin never has
to guess or hand-type a path incorrectly. Build this as a simple, centrally
maintained array/config (e.g. `App\Support\DeepLinkLibrary`, following the
same "one source of truth" discipline as every other support-class pattern
already established in this codebase) covering at minimum: eSIMs, Numbers,
Gift Cards, Profile, Account, "My Journey," Referrals, Become a Merchant,
White Label purchase (confirm this route only appears in the picker on
**master** — never expose a White Label license-purchase deep link inside
a White Label instance's own announcement composer, per every prior
boundary rule in this engagement), shared-wallet cap/wallet groups, "List
my brand," and any other real named route already live in this codebase —
audit the actual route list rather than only using the names given here,
since this list should reflect everything real, not just what was named
verbally.
- Keep `cta_url` as the stored field (so nothing about delivery changes),
  but have the admin UI populate it from this picker rather than a raw
  text box — still allow a raw-URL fallback for the rare case of linking
  externally, but make the in-app picker the default, easiest path.
- This library is also the natural home for the coupon-linked catalogue
  CTA already described in `Announcement::toInApp()`'s docblock — confirm
  that existing behavior isn't broken by the new picker, just made easier
  to reach for every other destination too.

### A3 — Verification
Create one announcement in each style with a real uploaded image, send it
to a test audience, and confirm: the correct style renders correctly on
the actual notification surface (not just in the admin preview), the
chosen deep link correctly navigates on tap, and — specifically for the
White Label boundary check — confirm the White Label purchase deep link
option is genuinely absent from the picker when composing from a White
Label instance's own admin.

---

## Phase B — Shared wallet: live in-session notification + confirm debit correctness

### Confirmed findings (mostly reassuring — read before building)
`WalletGroupInvitedNotification` already sends on `['mail', 'database',
'webpush']` — email and push already fire today, this is not missing.
`WalletGroupService::chargeFromGroup()` is constructed directly on top of
`WalletService` (the same class already confirmed elsewhere in this
engagement to use `lockForUpdate()` inside real DB transactions for every
debit) — shared-cap spending is not a separate, less-safe code path; it's
the same audited primitive. **The one genuinely missing piece is a live,
in-session toast** for a user who is actively using the app at the exact
moment they're invited, rather than needing to notice the bell/email/push.

### B1 — Live toast for an active session
This codebase has no real-time broadcasting/WebSocket infrastructure
today (confirmed elsewhere in this engagement). Two honest options,
in order of effort:
1. **Lightweight polling (recommended first version)**: the existing
   customer dashboard shell already runs Livewire; add a short-interval
   poll (e.g. every 15–20 seconds, on whatever component already sits
   persistently in the layout — check for one before adding a new
   always-mounted component) checking for a newly-created, not-yet-seen
   `WalletGroupMember` invite for the current user, and surface it as a
   toast (accept/decline inline, matching the "fintech-inspired" polished
   toast treatment described) the moment the poll picks it up. This needs
   no new infrastructure and ships fast.
2. **True real-time push**, if/when the broadcasting infrastructure from
   the earlier platform-reconciliation blueprint's theme-live-refresh
   phase has landed: fire a broadcast event on invite creation instead of
   relying on polling, for a genuinely instant toast rather than a
   15–20-second-delayed one. Don't build a second, separate broadcasting
   setup just for this — only take this path once that shared
   infrastructure already exists for another feature.
3. Pick option 1 now unless the broadcasting work is already done; this
   is explicitly not a reason to go build broadcasting infrastructure from
   scratch just for this one feature.

### B2 — Verification (mostly confirming, not fixing)
1. Confirm, by actually reading a real invite email and a real push
   notification during this work (not just trusting the `via()` array),
   that both currently render correctly and aren't silently broken — the
   channels being declared doesn't guarantee they're actually delivering
   correctly in production.
2. Build the toast per B1, and verify it appears within the polling
   window for a logged-in, actively-browsing test user.
3. **Explicitly verify the debit correctness the user asked about**: as
   an invited member, spend against the shared cap, and confirm the
   resulting `WalletTransaction` record and balance change are identical
   in shape/correctness to a normal, non-shared wallet debit — this is
   likely to simply pass, given `chargeFromGroup()`'s construction, but
   verify it directly rather than taking the architecture's word for it,
   consistent with this engagement's whole "trust but verify" discipline.

---

## Phase C — Business KYC (KYB): automated document/registration verification

### Confirmed findings
`KycVerification::L3` already exists as "business: KYB documents" — the
status level is modeled, but neither `DojahKycProvider` nor
`SmileIdKycProvider` shows any business-registration/document-verification
logic in this pass — both appear scoped to personal identity verification
(BVN/NIN/document/biometric for individuals). This strongly suggests L3
today either falls back to fully manual admin review, or isn't really
wired to a live provider at all yet — confirm which, precisely, before
building, since the fix differs (add automation to a level that's
currently manual vs. build out a level that's currently a stub).

### C1 — Add automated business-document verification
1. Confirm which of your existing KYC providers (or a new one) actually
   offers business/company-registration verification — Dojah, specifically,
   offers CAC (Nigerian business registry) verification as a real product
   alongside its personal-ID product; for non-Nigerian businesses, check
   whether SmileID or another already-integrated provider covers it, or
   whether this genuinely needs a new provider added — do not assume
   Dojah's existing personal-ID integration automatically covers business
   documents without checking its actual documented business-verification
   endpoint.
2. Extend the KYC upload flow so an L3 (business) applicant submits: the
   business registration/incorporation number, and the actual document
   upload — following this codebase's confirmed-safe pattern of never
   storing raw sensitive documents locally (per the earlier audit finding
   that personal KYC documents go straight to the provider, never sit on
   NaaraSim's own disk) — apply that same discipline here.
3. Wire the registration number + document to the chosen provider's real
   verification endpoint, following the exact same `KycProviderInterface`
   contract this codebase's existing providers already implement — this
   is an extension of the existing pattern, not a new one.
4. Route the result through the existing `KycResult`/`KycEvent` pipeline
   (`approved | rejected | pending | failed`) exactly as personal KYC
   already does, so admin's existing `KycReview` screen and notification
   pipeline (`KycResultNotification`) handle business verification for
   free, without needing a second, parallel review UI.
5. Keep manual review as the fallback path specifically for whatever the
   automated check can't confidently resolve (`pending`/`failed` with
   ambiguous reasons) — per this engagement's repeated design principle
   (port-in requests, KYC generally): automate the clear cases, keep a
   human in the loop for the genuinely ambiguous ones, rather than forcing
   every business through a human queue by default.

### C2 — Verification
Submit one real (or provider-sandboxed) business verification end-to-end
and confirm: the correct provider is called with the registration number
and document, a clear `approved`/`rejected` result reaches the admin
review screen and the applicant's own notification correctly, and no raw
document ever persists on NaaraSim's own storage disk.

---

## Constraints

- Phase A's White Label boundary check (deep-link picker excluding the
  White Label purchase route from White Label's own admin) is
  non-negotiable per every prior boundary rule in this engagement — treat
  it with the same seriousness as the original license-issuer surgery.
- Phase B does not require new broadcasting infrastructure unless it
  already exists for another reason — do not scope-creep this into a
  general real-time system build.
- Phase C reuses the existing `KycProviderInterface`/`KycResult` pipeline
  — no second, parallel KYC review system.

## Deliverable

Per phase: confirmation of the verification steps above, and for Phase C
specifically, explicit confirmation of which provider was actually used
for business verification and why (since this pass could not confirm one
already exists) — plus, for Phase B, honest confirmation of which
notification option (polling vs. real broadcast) was actually built and
why, given the state of the broadcasting work at the time this is picked
up.
