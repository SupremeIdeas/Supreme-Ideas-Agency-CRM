# NaaraSim — N2N (Naara-to-Naara) Master Blueprint
### Master repo only. Read this first; each batch file below is the executable brief for Claude Sonnet + Graphify.

Sources used: your brief, Batch 1 + Batch 2 specs, `chat-composer-pro.html`, **all 68 screenshots** in the N2N zip, and a direct read of `NaaraSim-Master_Platform_Sept_22.zip`. Anything I could not confirm from those is marked **UNVERIFIED**.

---

## 1. What the codebase already has vs. what N2N needs

| Need | Exists today (evidence) | Gap |
|---|---|---|
| Naara ID / username | **Nothing.** No `username` or `naara_id` anywhere in `database/migrations` or `app/Models/User.php` | Brand-new identity layer |
| Verified local phone | `users.phone` exists, but **no verification flow anywhere** (no `phone_verified_at`, no OTP for linking) | Ownership proof must be built, otherwise anyone can claim any number |
| Contacts | `Contact` = `user_id, name, phone_number, is_favorite` only | No `linked_user_id`, no matching, no import |
| Real-time | `.env.example`: `BROADCAST_CONNECTION=log`; no Reverb/Pusher/Echo in `composer.json` or `package.json` | No push-to-browser channel at all. Notifications poll (`notification-center` uses `wire:poll.30s`) |
| Chat data model | `MessageThread` is tied to `virtual_number_id` + `counterpart_number` (Naara Line SMS). `SupportConversation`/`SupportMessage` is support-only | New user-to-user conversation model needed |
| NaaraCredit | `CreditService` (locked, audited `credit_ledger`), USD peg via `CreditSettings::creditsToUsd/usdToCredits`, daily hunt cap | **Conflicts with your rules**: loyalty credit is redeemable toward eSIM (`quoteRedemption`) and has a `withdrawable` bucket. See §2.4 |
| Wallet sharing | `WalletGroup` / `WalletGroupMember` (owner's wallet, member caps) | Shares by member add, not by Naara ID/username lookup |
| Referral | `Referral` (`reward_pct`, `rewarded`), `ReferralRewardService`, `ReferralEarningsService` | Needs a "credit-gift attribution" path (Batch 5) |
| Brand directory | `BrandPartner`, `BrandSubscription`(+Plan), `BrandPartnerImage/Video/Handle/Follower` | Follower is user→brand only; no brand→user; no music profile; no per-plan media limits; plan has no "Pro" or credit-grant fields |
| Push | `PushSubscription`, `WebPushSender`, `SendWebPushJob`, `public/sw.js` (`push` + `notificationclick` handlers) | No tone logic; no sound handling |
| Calling | `VoiceCall` (hold → settle billing), `resources/js/dialer.js` + `@twilio/voice-sdk` — **PSTN via Twilio** | N2N must be a **separate** WebRTC path; reuse only the hold/settle billing pattern |
| Block / report | `BlockedNumber`, `SpamReport`, `SpamBlockedCaller` | Reuse for N2N block/report |
| Pricing/margin | `PricingEngine`, `DiscountMarginGuard`, `PricingArchitect` | Reuse for per-MB and per-minute margin math |
| Storage | CLAUDE.md: **Wasabi S3 for all file storage, never local disk** | Batch 2 spec says `file_path`; must be Wasabi |
| Native shell | `capacitor.config.json` (remote `server.url`), Android/iOS deps, `codemagic.yaml`; **no** push-notifications, haptics or contacts plugins | Opens an option for real vibration/background tones (Batch 2 Phase E) |
| Existing composer surfaces | `support-chat.blade.php` (+`resources/js/nia-chat.js`, `support-voice.js`), `send-message.blade.php` = **Naara Line SMS modal (MMS capped ~5 MB)**, `contact-form.blade.php` = **one-shot mail form, not a chat**. Also `emoji-picker.js`, `image-compress.js` already exist | Batch 1 must absorb the existing JS, not duplicate it. Exclude `contact-form` |
| Tests | 347 `*Test.php` files, PHPUnit | Baseline before each batch |

---

## 2. Corrections to the brief (things to handle honestly, not build as literally described)

1. **"End-to-end encrypted" cannot be claimed.** Blocking links, metering media by size, and moderation all require the server to read content. The WhatsApp reference screenshots show an E2EE line; Naara's copy must say *encrypted in transit and at rest*. Group calls through an SFU are also decrypted at the server.
2. **"Free calls, zero API cost" is true only in part.** No per-minute vendor fee is achievable (WebRTC + self-hosted signaling). But: STUN is free, **TURN is not** — on many mobile networks (carrier-grade NAT) a share of calls will fail without a relay, and relays cost server bandwidth. Group calls (2–100, 5 speakers, 95 listeners) **cannot be peer-to-peer**; they need an SFU (self-hosted LiveKit is the usual open-source choice, **UNVERIFIED for your VPS size**). So: *zero API fees, not zero infrastructure cost*, and calling is **VPS-only** (a cPanel shared host cannot run a WebSocket daemon, TURN, or an SFU).
3. **Closed-tab custom tones and iOS-Safari vibration** — Batch 2 §0 is correct for the web. New: the Capacitor shell can change this (native haptics on iOS, native push with sound). Treated as an optional Batch 2 Phase E, must be device-tested.
4. **Do not reuse loyalty NaaraCredit as-is for N2N.** It is USD-pegged, redeemable on eSIM checkout and partly withdrawable. Purchased-from-wallet credit that can flow into a redeemable/withdrawable bucket is a cash-out loophole and contradicts your rule ("only for platform-independent usage"). Batch 5 adds a separate non-redeemable, non-withdrawable **usage bucket**; UI name stays "NaaraCredit".
5. **Buying credit with wallet money and spending it in-app is a consumable digital good.** Your own `docs/APP-STORE-PAYMENTS-COMPLIANCE.md` states that digital goods consumed inside the app fall under Apple IAP (3.1.1). eSIMs escape that as real-world service; N2N credit may not. Decision D9.
6. **"Links are not allowed"** can only be enforced best-effort (detect URLs, domains, obfuscated forms). It reduces phishing; it cannot be absolute. Copy must not promise more.
7. **Cloud pricing is not "per megabit".** Object storage is billed per stored volume (and sometimes retention minimums), not per MB sent. The burn formula must take an **admin-set effective $/MB derived from the real bill**, times your markup, converted through the existing credit peg. I have not verified Wasabi's current terms; check minimum-retention/minimum-storage rules before pricing deletions.
8. **Raw HTML music embeds are an XSS door.** Accept only allowlisted provider embeds (Spotify, SoundCloud, Bandcamp, Apple Music, YouTube, Vimeo…), parse to provider + ID, render as sandboxed click-to-load iframes. This also answers your speed concern: nothing loads until tapped.
9. **The "Links" search filter (Google Messages screenshot) contradicts "no links".** In N2N it should show only Naara call links/profile links, or be dropped.
10. **Copy WhatsApp/Google patterns, not their assets.** Use Naara tokens (Teal `#0A6E6E`, Gold `#D4A017`, Navy `#0D1B2A`), own icons, own bubble/wallpaper art.
11. **Phone-linking has a real per-SMS cost** (§3 P1). Skipping verification is not an option; cost is a decision (D3).
12. **Contact discovery is an enumeration/privacy risk.** Matching an address book against registered phones needs consent, hashing, rate limits and a "hide me from discovery" setting.
13. **Regulatory**: rules on VoIP/OTT calling and messaging vary by country. Get local legal advice for each market before launch. **UNVERIFIED** here.

---

## 3. Shared primitives — how the layers relate

Every feature you described is a consumer of one of nine primitives. Build the primitive once, in the batch shown, and never re-implement it inside a feature.

| # | Primitive | Built in | Consumed by |
|---|---|---|---|
| P1 | **Identity resolver**: Naara ID / username / verified phone / email → user, with registered-check | B3 | new chat, credit share, calls, contact linking, invites, profile |
| P2 | **Usage-credit ledger + meter** (server-authoritative hold → settle) | B5 | message send, media send, call minutes, credit share, subscription grant |
| P3 | **Realtime channel** (Reverb driver + polling fallback) | B4 | message delivery, typing/presence, call signaling, status updates, tone triggers |
| P4 | **Entitlement: Naara Pro** = active `BrandSubscription` | B7 | media send, call/message CTAs, extended gallery, Updates ring |
| P5 | **Tone/vibration player** | B2 | message, ring loop, credit alert, reward, push |
| P6 | **Composer** | B1 | N2N chat, support chat, Naara Line SMS modal |
| P7 | **Storage pipeline** (Wasabi, MIME allow-list, scan, size meter) | B7 | media, voice notes, tones, brand gallery, wallpapers |
| P8 | **Relationship graph** (contact link, block, mutual connection, privacy level) | B3 seeds, B9 completes | who can message/call whom, visibility of status/profile, discovery |
| P9 | **Abuse layer** (reuse `SpamReport`, `BlockedNumber`) | B6 hooks, B14 completes | everything |

**Who may message/call whom** (one policy, enforced in one place): recipient must resolve as a registered user (P1). If sender and recipient are **not connected**, the first message is a *message request* the recipient accepts (sender still pays). Recipient privacy settings can restrict to "connections only". Brands with active Pro are reachable by CTA. Blocked users are silent-dropped. (Decision D8.)

---

## 4. Batch map

Status legend: **Written** = full executable brief in this pack. Batches 1 and 2 already exist; amendments are in §7.

### Wave 0 — independent, can run now
| Batch | Title | Depends on | File |
|---|---|---|---|
| 1 | Chat Composer Pro | none | existing + §7 amendments |
| 2 | Notification Tones, Sound & Vibration | none (Phase C items 3 and 5 wait for B6/B10) | existing + §7 amendments |

### Wave 1 — foundations (B3 ∥ B4 ∥ B5-schema can be parallel)
| Batch | Title | Depends on | File |
|---|---|---|---|
| 3 | Identity & Discovery (Naara ID, username, verified phone, contact linking) | none | `NAARASIM-BATCH3-IDENTITY-DISCOVERY.md` |
| 4 | Realtime Transport (Reverb + polling fallback, presence) | none | `NAARASIM-BATCH4-REALTIME-TRANSPORT.md` |
| 5 | Usage-Credit Economy (bucket, buy, share, pending/24h refund, meter, pricing) | B3 | `NAARASIM-BATCH5-CREDIT-ECONOMY.md` |

### Wave 2 — core product
| Batch | Title | Depends on | File |
|---|---|---|---|
| 6 | N2N Messaging Core (chat UI, new-chat picker, metering, message requests) | B1, B3, B4, B5 | `NAARASIM-BATCH6-N2N-MESSAGING.md` |
| 7 | Media & Naara Pro Entitlement | B5, B6 | `NAARASIM-BATCH7-MEDIA-PRO.md` |
| 8 | Brand Profile Extension & Local Profiles | B3 | `NAARASIM-BATCH8-BRAND-PROFILES.md` |
| 9 | Connections & Updates (follow-back, connect list, brand-gallery carousel) | B3, B6, B8 | `NAARASIM-BATCH9-CONNECTIONS-UPDATES.md` |

### Wave 3 — calling (VPS-only)
| Batch | Title | Depends on | File |
|---|---|---|---|
| 10 | N2N Voice Calls 1:1 (WebRTC, TURN, ring loop, billing) | B2, B4, B5, B6 | `NAARASIM-BATCH10-VOICE-CALLS.md` |
| 11 | Group Calls & Call Links (SFU, 5 speakers, listeners) | B10 | `NAARASIM-BATCH11-GROUP-CALLS.md` |

### Wave 4 — polish and safety
| Batch | Title | Depends on | File |
|---|---|---|---|
| 12 | Conversation Power Features (theme, pin, star, archive, mute, disappearing, lock, media gallery) | B6, B7 | `NAARASIM-BATCH12-CONVERSATION-POWER.md` |
| 13 | Naara Planner (scheduled calls, RSVP, reminders) — *suggested by screenshots* | B10, B11 | `NAARASIM-BATCH13-PLANNER.md` |
| 14 | Hardening, Compliance & Oversight | all | `NAARASIM-BATCH14-HARDENING.md` |

Dependency picture: `B3 → B5 → B6 → B7`, `B3 → B8 → B9`, `B4 → B6/B10`, `B2 + B5 + B6 → B10 → B11 → B13`, `B14` last. Per CLAUDE.md: a batch does not start until its dependencies pass their acceptance check.

---

## 5. Decisions needed from you (defaults apply if you don't answer)

| # | Decision | My recommendation (default) |
|---|---|---|
| D1 | Real-time transport | Laravel Reverb on VPS; cPanel installs get an adaptive-polling fallback (chat works, slower); **calling disabled on cPanel** |
| D2 | Composer transport (Batch 1's open fork) | (b) one dedicated authenticated HTTP send endpoint, so offline queue works and N2N inherits it |
| D3 | Phone-link verification | SMS OTP through your existing SMS provider, capped per user/day; treat cost as a one-time activation cost. Do not skip verification |
| D4 | Credit architecture | Separate usage bucket, one-way (wallet → credit, never back out, never redeemable/withdrawable). Loyalty credit **spendable** on N2N; N2N credit never converts back |
| D4b | Which credit is shareable | Purchased + subscription-grant credit shareable; loyalty-earned credit **not** (farming risk). Per-recipient and daily caps |
| D5 | Rates | 0.5 credit/message as you said. Call rate = admin setting anchored to 50% of a reference rate you name, with a hard floor = measured infra cost × margin. Media = $/MB (admin-set from real bill) × markup ÷ credit peg |
| D6 | Group-call scale | Ship 1:1 first; group launches capped (e.g. 5 speakers + 25 listeners) until load-tested, then raise toward 100 |
| D7 | Video calls (shown in WhatsApp screenshots, not in your brief) | Voice only for v1; video later |
| D8 | Non-connected messaging | Message-request gate + daily new-conversation cap |
| D9 | iOS purchase of N2N credit | Web-only credit purchase first; hide purchase UI in the native iOS build until you decide on IAP |
| D10 | "Music brand" | Explicit `profile_type` flag set at listing and admin-confirmed (higher limits are an abuse target) |
| D11 | Tone seeding | Generate original synthesized tones by script (no licensing risk) + an optional CC0 pack you supply; admin uploads add more |
| D12 | Status semantics | Updates ring = the brand's gallery slides, no 24h expiry, as you described. Confirm no separate story uploads |
| D13 | Channels (broadcast) and Disappearing/“AI images” from screenshots | Deferred; not in your brief |

---

## 6. Rules that apply to every batch (do not restate per batch)

1. **Master-only.** Per CLAUDE.md, N2N is master-only until proven otherwise. Do not port to `NaaraSim-WhiteLabel` or `-Extented`; do not add nav entries to children. Gate with `FeatureEntitlements::isMaster()`.
2. **One module at a time**, dependency passing its acceptance check first. After every batch: update `PROGRESS.md` (DONE + exact NEXT).
3. **Test locally** (`vendor/bin/phpunit`, `npm run build`). CI is manual-only until 2026-10-01; push with `[skip ci]`. Record the baseline before changing anything.
4. **Feature flags** per surface; old behavior stays until the new one is verified.
5. **Money is server-authoritative**: hold → settle, idempotency keys, `Cache::lock` + DB transaction (copy the `CreditService::apply` pattern). Never trust client durations, sizes or amounts.
6. **Wasabi for every file**; MIME allow-list, size cap, randomised names, no public listing.
7. **Every new admin screen**: check for an existing home first (admin-menu-consolidation discipline).
8. **Extend account erasure and legal-hold** (`docs/ACCOUNT-ERASURE.md`, `admin/legal-hold-records`) for every new table that holds user content.
9. **Flag unverified data** in the batch report instead of assuming (vendor prices, browser behavior, device results).
10. **Copy honesty**: no E2EE claim, no closed-tab custom-sound promise, no "unlimited free".

---

## 7. Amendments to Batch 1 and Batch 2 (apply before running them)

**Batch 1 — Chat Composer Pro**
- Confirmed by code: `send-message` = Naara Line SMS modal with MMS size cap; `contact-form` = one-shot form → **exclude from the replacement map**.
- Phase 0 must inventory `resources/js/nia-chat.js`, `support-voice.js`, `emoji-picker.js`, `image-compress.js` and **fold them into the composer** rather than run two implementations.
- Take decision D2 = (b) now, so Batch 6 inherits the send endpoint contract.
- Tailwind 3.4 with `darkMode: 'class'`, tokens in CLAUDE.md; map to `--bg/--panel/--border/--text/--muted/--accent/--hover`.
- No N2N surface in Batch 1; Batch 6 adopts the component.

**Batch 2 — Tones, Sound & Vibration**
- Tone files live on **Wasabi**, and the bucket needs **CORS** for the Web Audio `fetch`; otherwise decoding fails silently.
- "Exactly one default per category": MySQL 8 has no partial unique index → use a generated column (`default_key = IF(is_default, category, NULL)`) with a unique index, or a locked transaction.
- Seed with **generated original tones** (D11), not downloaded packs.
- Phase C items 3 (N2N vs Naara Line distinction) and 5 (incoming call loop) ship as **stubbed event names** now; real wiring happens in B6/B10.
- The top-up toast: there is no `resources/views/layouts` directory in this repo and I did not locate the toast component; Phase 0 must find it before wiring `credit_alert`. Existing top-up notice is `TopUpReceiptNotification` (title "Wallet topped up").
- **Phase E (optional, Capacitor)**: `@capacitor/haptics` gives vibration on iOS inside the app; native push (FCM/APNs) can carry a sound. Android per-notification sound is tied to notification channels, so per-user tone choice may need a channel per tone. **UNVERIFIED — device test required.**

---

## 8. Screenshot traceability (numbered by filename order, 1–68)

| Screenshots | Source app | Shows | Goes to |
|---|---|---|---|
| 1–5 | Google Messages | Composer, voice recorder (noise cancellation, restart/attach), emoji/GIF/sticker | B1 (already in spec), B12 |
| 6 | Google Messages | Overflow menu: Add people, Details, Starred, Search, Chat theme, Archive, Trash, Block & report | B12, B6 |
| 7–12 | Google Messages | Chat theme: colours + wallpapers, "only you see this" | B12 |
| 13–16 | Google Messages | Conversation list, **New chat: To name/phone/email** | B6 |
| 17, 25 | Google Messages | **"With: SIM" picker** (your note: Naara credit or Wallet balance) | B6 (§ line picker) |
| 18–24 | Google Messages | Search filters: Unread, Known, Unknown, Starred, line, Images, Videos, Places, Links | B6, B12 |
| 26–42 | Google Calendar/Tasks | Month/agenda, task lists, due date/time/repeat, star | B13 (tasks generic = deferred) |
| 43–46 | WhatsApp | Chat, pinned message, quoted reply, ticks, call dropdown (voice/video/**send call link**), menu (View contact, Search, Report, Block, Mute, Disappearing, Chat theme) | B6, B10, B11, B12 |
| 47–51 | WhatsApp | Contact/business profile: hours, category, address, map, media/links/docs, storage, chat lock, add to list, block/report business | B8, B12 |
| 52 | WhatsApp | Updates: Status carousel + Channels | B9 (Channels deferred, D13) |
| 53–55 | WhatsApp | Calls tab: Call, Schedule, Keypad, Favorites, Recents | B10, B13 |
| 56–59, 61–64 | WhatsApp | Schedule call, RSVP, event card in chat with "Edit / Add to calendar" | B13, B11 |
| 60 | WhatsApp | Status viewer with reply + reactions | B9 |
| 61–63 | WhatsApp | Voice note pause/resume, playback speed | B1 (already specified) |
| 64 | WhatsApp | Attachment sheet: Gallery, Camera, Location, Contact, Document, Poll, Event, AI images | B1; Event → B13; "AI images" out of scope |
| 65–68 | WhatsApp | Media picker with HD toggle, poll creator, emoji/GIF/sticker | B1 |

New ideas the screenshots add beyond your brief: **video calls**, **Channels**, **disappearing messages**, **chat lock**, **scheduled calls with RSVP**, **tasks**, **chat themes**, **starred/archive/mute**. Only those marked in §4 are planned; the rest are flagged, not built.
