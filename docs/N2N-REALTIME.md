# N2N realtime: operations guide (MASTER ONLY)

One API, two transports. Application code only calls `Realtime::publish($userId, $type, $payload)` on the server and `window.N2NRealtime.subscribe(type, cb)` in the browser. Both deliver the same envelope `{id, type, payload, at}`.

| Install | Transport | What works |
|---|---|---|
| VPS | Reverb (WebSocket) | instant delivery, typing, presence, calling |
| Shared cPanel | Polling (`/n2n/realtime/sync`) | chat a few seconds slower; **no calling** |

Admin > N2N picks the transport. Polling is the default and the safe fallback: even with Reverb on, every event is also written to `n2n_realtime_events`, a slow safety poll runs, and the client replays from its cursor on every reconnect.

## Turning on Reverb (VPS only)
1. `.env` (server side; the browser never sees the secret):
   ```
   REVERB_APP_ID=naara
   REVERB_APP_KEY=<random>
   REVERB_APP_SECRET=<random>
   REVERB_HOST=127.0.0.1
   REVERB_PORT=8080
   REVERB_SCHEME=http
   REVERB_PUBLIC_HOST=ws.your-domain.com
   REVERB_PUBLIC_PORT=443
   REVERB_PUBLIC_SCHEME=https
   ```
2. Supervisor unit:
   ```
   [program:naara-reverb]
   command=php /var/www/naara/artisan reverb:start --host=127.0.0.1 --port=8080
   autostart=true
   autorestart=true
   user=www-data
   stopwaitsecs=30
   minfds=10000
   ```
3. Nginx (TLS terminates here; proxy `wss://ws.your-domain.com` to the local port):
   ```
   location / {
     proxy_http_version 1.1;
     proxy_set_header Upgrade $http_upgrade;
     proxy_set_header Connection "Upgrade";
     proxy_set_header Host $host;
     proxy_read_timeout 120s;
     proxy_pass http://127.0.0.1:8080;
   }
   ```
   Cloudflare (see `docs/CLOUDFLARE-SETUP.md`): WebSockets must be enabled for the zone, and the `ws.` host must be proxied on 443 (Cloudflare only proxies certain ports).
4. Firewall: port 8080 stays closed to the internet; only the reverse proxy reaches it.
5. Admin > N2N: set Transport to Reverb. The page shows whether the server is reachable.

## Capacity: not measured
Connections per process depend on the box and its open-file limit (the admin page prints `ulimit -n` when the host allows it). Raise `minfds` / `LimitNOFILE`, then load-test on the target VPS and record the number here before promising any figure.

## What is stored
Events hold ids and minimal fields only (never message bodies), pruned after `n2n.realtime_ttl_hours` (default 48) by `n2n:prune-realtime` (hourly, registered with the scheduler heartbeat). Typing is ephemeral and never stored. A user who hides presence is neither recorded nor able to see anyone else's last seen or typing.
