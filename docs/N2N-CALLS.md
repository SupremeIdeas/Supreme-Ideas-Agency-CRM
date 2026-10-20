# Naara chat voice calls — deployment (MASTER-ONLY)

Calls are off unless **both** are true: Reverb realtime is running (see `N2N-REALTIME.md`) and a TURN relay is configured.
They need a VPS. cPanel installs keep calls off; chat, profiles and updates are unaffected.

## 1. Install coturn (Ubuntu/Debian)

```bash
sudo apt-get install -y coturn
sudo sed -i 's/#TURNSERVER_ENABLED=1/TURNSERVER_ENABLED=1/' /etc/default/coturn
```

`/etc/turnserver.conf`:

```
listening-port=3478
tls-listening-port=5349
fingerprint
use-auth-secret
static-auth-secret=<long random secret>
realm=<your domain>
total-quota=100
no-multicast-peers
no-cli
# deny private ranges so the relay can't be used to reach your internal network
denied-peer-ip=10.0.0.0-10.255.255.255
denied-peer-ip=172.16.0.0-172.31.255.255
denied-peer-ip=192.168.0.0-192.168.255.255
denied-peer-ip=127.0.0.0-127.255.255.255
# TLS (recommended: lets calls pass strict firewalls on 5349/443)
cert=/etc/letsencrypt/live/<domain>/fullchain.pem
pkey=/etc/letsencrypt/live/<domain>/privkey.pem
```

Open UDP+TCP 3478, TCP/UDP 5349 and the relay range (default UDP 49152-65535, narrow it with `min-port`/`max-port`).

## 2. Configure NaaraSim

`.env`:

```
N2N_TURN_URLS=turn:turn.example.com:3478?transport=udp,turns:turn.example.com:5349?transport=tcp
N2N_TURN_SECRET=<the same static-auth-secret>
```

Then Admin -> Naara chat -> Calls: turn calls on, set per-minute rates and the ring timeout.
Credentials are short-lived (HMAC-SHA1 of `expiry:userId`, the coturn REST scheme); the secret never reaches the browser.

## 3. Schedule

`php artisan n2n:calls-sweep` runs every minute from the scheduler: it ends stale rings, settles calls whose peer vanished (at the last good heartbeat) and releases orphaned credit holds.

## 4. What was verified (local, real browsers)

`tests/visual/n2n/call-smoke.mjs` drives two Chromium contexts with a fake microphone against a local coturn:

- direct path: both sides reach `active`, audio bytes received > 0 on both, relay not used, mute and hang-up work;
- forced relay (`iceTransportPolicy: relay`): same, with the relay candidate in use on both sides;
- the call is billed (credit settled once, at the hang-up).

**Not verified (needs production):** behaviour behind real carrier NAT / corporate firewalls, relay bandwidth cost at volume, TLS TURN on 443, and mobile-browser background behaviour (iOS suspends the tab; native ringing/CallKit is out of scope).
