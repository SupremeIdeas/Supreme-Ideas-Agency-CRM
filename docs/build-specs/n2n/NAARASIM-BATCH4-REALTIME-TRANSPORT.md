# NaaraSim — Batch 4: Realtime Transport
### Master repo only. The push-to-browser layer messages, presence, calls and tones all ride on (Primitive P3). For Claude Sonnet + Graphify.

**Depends on:** nothing. **Unblocks:** B6, B10, B11 and every "instant" behavior. Can run in parallel with B3.

## 0. Findings from the codebase
- `.env.example` has `BROADCAST_CONNECTION=log`; no Reverb/Pusher/Echo in `composer.json` or `package.json`. **There is no real-time layer.** Today the UI polls (`notification-center.blade.php`: `wire:poll.30s`).
- Stack: Laravel 12, Livewire 3, Vite 6, Redis/Horizon target, `QUEUE_CONNECTION=database` in the sample env.
- Deployment reality (CLAUDE.md): **both VPS and shared cPanel**. A WebSocket daemon cannot run on typical shared hosting.
- `docs/CLOUDFLARE-SETUP.md` exists: check its WebSocket implications before choosing ports/proxying.

## Design: one API, two drivers
`config/realtime.php` → `driver = reverb | poll | null`. Application code only ever calls:
- Server: `Realtime::publish(int $userId, string $type, array $payload)`
- Client (`resources/js/realtime.js`, lazy-loaded on N2N pages only): `realtime.subscribe(type, cb)`
Both drivers deliver the **same event envelope** `{id, type, payload, at}`. Events are small (ids + minimal fields); the client fetches details.

## Phase A — Event log
1. `realtime_events` (id, user_id, type, payload JSON, created_at) with short TTL and a pruning job (register it with `JobHeartbeat`).
2. Purpose: **replay after disconnect** for both drivers and the entire feed for the poll driver.
3. Verification: kill the connection for 60 s, reconnect, missed events arrive in order, once.

## Phase B — Reverb driver (VPS)
1. Laravel Reverb (first-party, self-hosted, no per-message fee). Echo client via Vite. Private channels: `n2n.user.{id}` (personal events), `n2n.conv.{id}` (conversation events) authorized by policies; presence only if the user allows it.
2. Ops artifacts: Supervisor unit for `reverb:start`, reverse-proxy block for `wss://`, firewall/port notes, `.env` keys. Document in `docs/INSTALLATION.md` (VPS section).
3. Verification: an unauthorized user subscribing to another user's channel is rejected; two browsers exchange an event with measured latency reported.

## Phase C — Poll driver (cPanel fallback)
1. `GET /realtime/sync?cursor=` returns events since the cursor. Adaptive interval: fast while the tab is visible and active, slow when hidden, exponential backoff on errors. One request per interval, not one per feature.
2. Server chooses the driver per install; the client asks `GET /realtime/config` once and picks the transport.
3. Verification: with `driver=poll`, a message event still reaches the recipient within one interval; hidden tab reduces request rate.

## Phase D — Presence, typing, last seen
1. Ephemeral typing events (not logged). Presence/last-seen with per-user privacy setting (hide = neither see others' nor be seen).
2. Verification: hide-last-seen user is invisible to others and cannot see others' last seen.

## Phase E — Ops and capability report
1. Health check: reachable, connection count, event lag, poll load. Feed into `SchedulerHealth`/admin status.
2. **Do not assert capacity numbers.** Measure connections per process on the target VPS and report the result and the OS limits (file descriptors) that constrain it.
3. Feature matrix in docs: VPS = instant + calls; cPanel = polling, no calls.

## Constraints
- No feature may open its own WebSocket or its own poll loop. Everything uses `realtime.js`.
- Payloads never contain message bodies for the poll driver longer than needed; events reference ids.
- Channel authorization is tested for every channel type.

## Deliverable
Latency and reconnect results, the measured capacity note, and the deployment matrix (what works where).
