# NaaraSim — Batch 11: Group Calls & Call Links
### Master repo only. **VPS-only.** 2–100 participants, max 5 speakers chosen by the host, everyone else listens free. For Claude Sonnet + Graphify.

**Depends on:** B10. **Unblocks:** B13.

## 0. Reality check (read before designing)
- **Peer-to-peer cannot do this.** A speaker in a mesh would upload to every listener. You need an **SFU** (media forwarder). Self-hosted LiveKit is the leading open-source option; mediasoup is the alternative. Phase 0 recommends one **with evidence for your VPS size — UNVERIFIED until tested**.
- Egress arithmetic (audio at 32 kbps, before overhead): 5 speakers × 32 kbps × 95 listeners = 15.2 Mbps ≈ **6.8 GB per hour for one full call**. This is why the launch cap is smaller (Master D6) and why bandwidth on the VPS plan matters. An audio mixer (MCU) lowers egress but raises CPU; evaluate in Phase 0.
- Listening is free per your rule, so listener cost is pure infrastructure — monitor it.

## Phase A — Rooms, roles, links
1. `n2n_group_calls` (host, capacity, `max_speakers` default 5, state), `n2n_call_links` (token, host, capacity, expires_at, revoked_at, requires_approval), `n2n_call_participants` (call, user, role host|speaker|listener, joined_at, left_at).
2. **Joining requires a registered, logged-in N2N user.** Not-yet-registered people hit a sign-up funnel that carries the **host's referral link** (growth path). Call links are Naara-issued, so they are on the B6 link-filter allow-list.
3. Host controls: appoint/remove speakers (max 5 including the host), mute, remove, ban, close the room, revoke the link.
4. Verification: expired/revoked links fail; 6th speaker appointment is refused; a removed user cannot rejoin with the same link.

## Phase B — SFU integration
1. Laravel issues a **join token per participant** with grants: speakers may publish and subscribe; listeners may only subscribe. Promotion/demotion changes grants server-side via the SFU's admin API.
2. Deploy artifacts: SFU service config, TURN/TLS, ports, capacity limits per server, `.env` keys, docs in `docs/INSTALLATION.md`.
3. Verification: a listener cannot publish even with a tampered client; demotion silences the user within seconds.

## Phase C — Billing by speaking role
1. `n2n_call_participant_intervals` (call, user, role, from, to). **Charge each speaker while appointed** (per your rule, appointment — not mute state — is the billable interval), from their own credit, via B5 hold/settle. **Host pays for their own speaking portion only.**
2. Speaker runs out of credit → auto-demote to listener (free) with a notice; host out of credit → can keep hosting as a non-speaker.
3. Server records intervals; the client never reports time.
4. Verification: an appoint → mute → un-appoint sequence bills exactly the appointed interval; a crash mid-call settles at the last heartbeat; listeners are never charged.

## Phase D — Capacity and safety
1. Admin settings: max listeners (launch value conservative), max concurrent rooms per server, graceful "room full / server busy" message.
2. No recording feature in this batch (legal exposure); report/block work inside a room.
3. Verification: synthetic-load test (SFU vendor tooling — **UNVERIFIED availability**) at the launch cap, then at the target cap; report CPU, memory and egress measured, not estimated.

## Constraints
- Group audio is encrypted in transit but **decrypted at the SFU**; copy must not say E2EE.
- Same call policy and block rules as B10.
- Feature disabled unless SFU health check passes.

## Deliverable
Load-test results, measured egress per listener, the launch cap recommendation, and the exact SFU deployment steps.
