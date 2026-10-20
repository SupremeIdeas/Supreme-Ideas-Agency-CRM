# Naara group calls: deployment, bandwidth and launch cap (MASTER-ONLY)

Group calls are a room hosted by one member: the host appoints up to 5 speakers (host included), everyone else listens. Only speakers are billed, per appointment interval, from their own NaaraCredit; listening is free. A room is joined by link; a visitor without an account is sent through sign-up carrying the host's referral code.

Group calls need a VPS running a self-hosted **LiveKit** SFU. cPanel installs keep them off; chat and 1:1 calls are unaffected. They are off unless all of these hold: N2N is on, the admin switched group calls on (Admin > N2N > Group calls), an SFU is configured, and its health check passes.

> Privacy statement to keep in the product copy and the privacy policy: audio is encrypted in transit (DTLS-SRTP) but **decrypted at the SFU** so it can be forwarded. Group calls are **not end-to-end encrypted**. Do not describe them as E2EE.

## 1. Install LiveKit (Ubuntu/Debian, behind your existing proxy)

Verified here against `livekit-server 1.13.7`.

```bash
curl -sSL https://get.livekit.io | bash          # installs livekit-server
sudo mkdir -p /etc/livekit
```

`/etc/livekit/livekit.yaml`:

```yaml
port: 7880
rtc:
  tcp_port: 7881
  port_range_start: 50000
  port_range_end: 60000
  use_external_ip: true
keys:
  <API key>: <API secret, 32+ random characters>
```

Run it under supervisor or systemd (`livekit-server --config /etc/livekit/livekit.yaml`). Open **7881/tcp** and **50000-60000/udp** (or the range you set) in the firewall; the HTTP/WebSocket port 7880 is proxied by nginx/Caddy over TLS and does not need to be public if the app proxies it. Browsers need `wss://`, so give the SFU its own hostname (for example `sfu.example.com`) with a certificate.

## 2. Configure the app (`.env`)

```
N2N_SFU_URL=http://127.0.0.1:7880            # server-side: the app calls the SFU's admin API here
N2N_SFU_WS_URL=wss://sfu.example.com          # browser-side: where members connect
N2N_SFU_KEY=<API key>
N2N_SFU_SECRET=<API secret>
```

The key and secret stay on the server: the browser only ever receives a short-lived join token minted for one person and one room, with publish rights only while that person is appointed to speak (listeners cannot publish at the SFU, whatever their browser does). Then Admin > N2N > Group calls shows the SFU health; switch the feature on.

The scheduler runs `n2n:group-sweep` every minute (registered with the job heartbeat): it closes idle or abandoned rooms, expires links and settles anyone who vanished at their last heartbeat. A VPS must run the Laravel scheduler (`* * * * * php artisan schedule:run`).

## 3. What the server enforces (not the browser)

- Speaker limit and capacity are checked on the server on every join and every appointment; the host counts as one speaker while speaking.
- Billing is by **appointment interval, not mute**: a speaker is charged from the moment they are appointed until they are demoted, leave, are removed or the room closes. Each interval holds credit first (`grp:{id}:h{n}`); when cover runs out the room demotes them automatically.
- A removed person cannot rejoin that call with any link. A blocked pair behaves like an expired link. Links are stored hashed and encrypted; revoking or generating a new one invalidates the old.
- One open room per person; the host leaving closes the room.

## 4. Measured launch numbers

Method: a real `livekit-server` on the same 4-core / 16 GB VM as the load generator (`/tmp/lk/loadgen`, a Go program using LiveKit's server SDK: speakers publish an Opus-rate 20 ms stream of 80-byte payloads, about 32 kbps, listeners subscribe to every speaker). The load generator shares the machine, so the server's CPU figure is an upper bound. Loopback only: **this proves the SFU's CPU and memory cost and the packet arithmetic, not internet latency, packet loss or the host's real uplink.**

| Scenario | People | Streams forwarded | Received (app-level) | Server CPU (one core = 100%) | Server RSS | Failed joins |
|---|---|---|---|---|---|---|
| 1 room, 5 speakers + 95 listeners | 100 | 495 | 18.2 Mbit/s | ~50% | 288 MB | 0 |
| 5 rooms, each 5 speakers + 20 listeners | 125 | 600 | 22.1 Mbit/s | ~70% | 396 MB | 0 |

**Egress arithmetic:** each forwarded stream measured ~36.8 kbit/s including RTP headers, so 5 speakers x 100 people is about 18 Mbit/s, about **8.2 GB per hour per full room** (the earlier 6.8 GB/h estimate omitted packet-header overhead and was low). Ingress is only the speakers (about 0.2 Mbit/s). Plan the host's bandwidth on egress: a 1 Gbit/s uplink holds roughly 50 such rooms; a metered plan should be priced per GB (roughly 8 GB per full room-hour).

**Recommended launch cap:** keep the defaults (up to 20 people per room, 5 open rooms platform-wide, 10 rooms per host per day). That is at most about 100 people in rooms at once, well inside one modest VPS (about 1 core and 0.4 GB of SFU). Raise the caps in the admin page only after watching the real server's CPU and network graph; scaling past one SFU node (LiveKit supports a multi-node cluster with Redis) is not built or tested here.

## 5. UNVERIFIED (do not claim in marketing or support)

- Real internet conditions: packet loss, jitter, mobile networks, carrier NAT. The 1:1 TURN advice in `N2N-CALLS.md` applies; the SFU also needs 7881/tcp reachable for strict firewalls.
- SFU behaviour at hundreds of concurrent rooms or across nodes.
- Audio quality with real microphones at the 32 kbps target (the load test sends synthetic payloads).
- Browser coverage beyond Chromium (Safari/iOS WebRTC permission and autoplay behaviour for listeners).
- Native apps: group calls are web only; there is no native ring/lock-screen UI.
