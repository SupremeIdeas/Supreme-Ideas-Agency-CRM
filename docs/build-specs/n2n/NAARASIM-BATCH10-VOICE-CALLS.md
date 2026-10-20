# NaaraSim — Batch 10: N2N Voice Calls (1:1)
### Master repo only. **VPS-only.** WebRTC peer-to-peer with self-hosted signaling and TURN. For Claude Sonnet + Graphify.

**Depends on:** B2 (ring loop), B4 (Reverb), B5 (meter), B6 (chat). **Unblocks:** B11, B13.

## 0. Findings and reality
- Existing calling is **PSTN via Twilio** (`resources/js/dialer.js`, `@twilio/voice-sdk`, `VoiceCall`). N2N calling is a **different system**; reuse only the hold → settle billing shape from `VoiceCall`.
- No per-minute vendor fee is possible here, but **STUN is free, TURN is not** and must be self-hosted (coturn). Many mobile networks use carrier-grade NAT, so a share of calls will need the relay. **Measure your relay share; do not assume it.**
- Arithmetic for pricing floors: an Opus stream at 32 kbps ≈ 14.4 MB per hour per direction before overhead; a relayed call carries both directions. Use **measured** coturn relay bytes for the cost floor.
- cPanel shared hosting cannot run this. Feature is off unless `realtime.driver = reverb` and TURN is configured.

## Phase A — Server call state machine
1. `n2n_calls` (caller, callee, `state` ringing|active|ended|missed|declined|busy|failed, `hold_ref`, `answered_at`, `ended_at`, `end_reason`). **Server clock is the timing authority.**
2. Signaling over B4: invite, accept, decline, offer, answer, ICE candidates, hangup. Authorization on every event (participant only); one active call per user (busy handling).
3. Heartbeat every 5–10 s from both sides; lost heartbeat beyond a threshold ends the call and settles at the last good heartbeat.

## Phase B — ICE/TURN
1. coturn with **time-limited REST credentials** (HMAC, `use-auth-secret`) issued by Laravel per call. Never ship static TURN credentials in JS.
2. Own STUN via coturn (optional public STUN as fallback). Opus with DTX. ICE restart on network change.
3. Ops artifacts: ports (3478 UDP/TCP, TLS port, relay range), firewall, `.env`, supervisor/systemd notes in `docs/INSTALLATION.md`.

## Phase C — Billing (caller pays, callee free)
1. Rate from B5 (`callRatePerMinute`); billing increment is an admin setting (default 60 s per your brief; recommend a smaller increment as a later option).
2. Hold N minutes at start; extend the hold in a rolling fashion; when credit is low warn ~30 s ahead, then end the call. Settle from **server `answered_at` to end**; release the unused hold.
3. Verification: abrupt tab close settles correctly; client clock manipulation has no effect; insufficient credit blocks the call with **Buy / Ask a friend** actions.

## Phase D — Ringing, missed calls, push
1. Incoming call uses B2's reusable `NotificationSound` **loop** with stop on answer/decline/timeout (30–45 s). Foreground only.
2. Tab closed: web push with actions (Answer/Decline) opens the app to join. **No custom ringtone in the background** (B2 §0). True native ringing needs CallKit/ConnectionService in the native shell — out of scope, flagged.
3. Missed calls: log, notification, "Call back". Recents/favorites views (screenshots 53–55).

## Phase E — Call UI and entry points
Mute, speaker/output device where supported, network-quality hint, reconnect state, mic-permission flow. Entry: chat header, profile CTA, contacts, recents. No keypad/dialing numbers — N2N is registered users only.

## Phase F — Policy
Who can call me: connections + Pro brands by default; blocked = silent; do-not-disturb; per-minute call-attempt rate limit.

## Verification matrix (report real results)
- Wi-Fi ↔ mobile data, mobile ↔ mobile, same NAT.
- **Forced relay test** (`iceTransportPolicy: relay`) proves TURN works.
- iOS Safari, Android Chrome, desktop Chrome/Firefox.
- Percentage of test calls that used the relay; relay bytes per minute measured.
- Missed, declined, busy, two incoming at once.

## Constraints
- Call content is encrypted in transit by WebRTC; do not claim E2EE anywhere.
- Video is out of scope (Master D7).
- Legal review per market (Master §2.13).

## Deliverable
Results of the matrix above, measured relay share and bandwidth, and the exact deployment steps for coturn.
