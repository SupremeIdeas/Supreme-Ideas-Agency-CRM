# Business Suite: setup and hosting guide (master only)

Written for the person running the server, not for a developer. Everything here is also shown, with your real paths filled in, in
**Admin > Business Suite > Setup and health**. The same checks run in a terminal with `php artisan business:doctor` (add `--fix` to repair what can be repaired).

## 1. What the Suite needs from a server

| Need | Shared hosting (cPanel) | VPS / Cloudways |
|---|---|---|
| PHP 8.2 or newer, normal extensions | yes | yes |
| One cron entry, every minute | **required** (it also runs the queue) | required (runs the scheduler) |
| Queue worker | none: the cron drains a database queue every minute | Redis + Horizon under Supervisor |
| Public https address (so WhatsApp, Telegram, Instagram and Twilio can reach you) | required | required |
| Media converter (ffmpeg) | optional, installs itself into `storage/` | optional, installs itself or use the system one |
| AI voice agent process | **not possible** | Supervisor + a TLS proxy (section 6) |

Everything except the AI voice agent works on shared hosting. The voice agent is the only part that needs a long-running process.

## 2. The one cron entry

Add this every minute (cPanel > Cron Jobs). Setup and health prints the exact line for your server:

```
* * * * * cd /home/you/app && /usr/bin/php8.4 artisan schedule:run >> /dev/null 2>&1
```

What it runs, all automatically: scheduled inbox messages (every minute), call-recording retention (daily), the payout and wallet jobs, and, on shared hosting, the whole
queue (`queue:work --stop-when-empty` once a minute). If this is missing, **Cron (scheduler)** shows **Fix** on the Setup and health page.

## 3. The queue

* **Shared hosting:** in `.env` set `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `SESSION_DRIVER=database` and run `php artisan migrate --force`. Nothing else: the cron drains it. A job can wait up to a minute.
* **VPS:** `QUEUE_CONNECTION=redis`, then keep Horizon alive with Supervisor (the config is on the page) and add `php artisan horizon:terminate` as the last line of your deploy script.
* **Prove it works:** press **Test the queue now**. It sends one harmless job and reports how long a worker took to pick it up. If nothing picks it up within about 75 seconds the page says **Fix** and names the likely cause (cron or Horizon).

## 4. ffmpeg (the media converter)

ffmpeg is a free program that converts video and audio. The Suite works without it; with it, every GIF, video and voice note reaches every channel in the format that channel wants.

**What happens without it**

| Case | Result |
|---|---|
| Voice note from Chrome, Edge, Android | Re-wrapped as Ogg Opus in plain PHP (no loss, milliseconds). Works on WhatsApp and Telegram. |
| Voice note from Safari | Already AAC, no conversion needed. |
| GIF to Telegram | Sent as an animation. |
| GIF to WhatsApp | WhatsApp cannot show a GIF inline, so it is sent as a file that still opens and animates. With ffmpeg it is sent as a looping video. |
| Video that is not MP4 | Sent as a file (WhatsApp, Telegram). With ffmpeg it is converted to MP4. |
| Instagram voice note or non-MP4 video | Refused with a plain sentence pointing to Setup and health. Needs ffmpeg. |

**How the platform finds ffmpeg**, in this order: the path you type, a copy it installed itself in `storage/app/bin/`, the `FFMPEG_BINARY` environment variable, then the server's own copy. Each candidate is run once before it is trusted.

**Getting it, easiest first**

1. **Press "Install ffmpeg now"** (Setup and health > Media conversion). The platform downloads a verified static build into `storage/app/bin/ffmpeg`. No root access, no compiler. It works in small steps (about 5 to 20 seconds each, with a progress bar) so a shared host's time limit cannot cut it off. Keep the page open for a minute or two. The terminal version: `php artisan ffmpeg:install`.
2. **Your host blocks downloads?** Put the static build on a server you control and paste its https address into **Download link**. It is tried first.
3. **Still blocked?** On your own computer, download a "static" Linux build from johnvansickle.com, unpack it and upload the single file named `ffmpeg` in the same card. Your host's upload limit must allow about 70 MB.
4. **Already installed by the host or on a VPS?** Type its path (for example `/usr/bin/ffmpeg`) and press **Save and check**. On a VPS, `apt install ffmpeg` works too.

**If it says "Can run programs: no"**: the host has disabled `proc_open` and `exec` in php.ini. Ask the host to enable one of them. Until then the PHP fallbacks above still cover voice notes and everything else except Instagram.

## 5. Public address and files

WhatsApp, Telegram and Instagram download each file from a link on your site, and Twilio posts events to it. So `APP_URL` in `.env` must be the real https address of the site (not `localhost`), and `public/storage` must exist (the page has a **Create the storage link** button; if your host blocks symbolic links, create it by hand: `ln -s ../storage/app/public public/storage`).

## 6. AI voice agent (VPS only)

1. Run it under Supervisor: `php artisan business:ai-voice-server --host=127.0.0.1 --port=8089` (config on the page).
2. Put a TLS proxy (nginx or Caddy) in front, on a sub-domain such as `agent.example.com`, forwarding `/relay` to port 8089 with WebSocket headers (config on the page).
3. Set **Settings > AI voice agent > Server address** to `wss://agent.example.com/relay`.
4. Restart it after each deploy: `supervisorctl restart naarasim-ai-voice`.

The page shows **Agent process: Running** only while the agent's heartbeat is fresh (under 90 seconds).

## 7. Built-in content

A fresh install already has: the four workspace roles, 79 original GIFs and stickers on nine shelves, and 30 ringtones, alerts and beeps. If any is missing (for example a database restored from an old backup), press **Seed everything** or run `php artisan business:doctor --fix`.

## 8. Stickers and GIFs: categories and the maker (Admin > GIF library)

* **Categories.** Create your own, rename, reorder, hide, or delete (the GIFs inside move to another category or go with it).
* **Where each category shows.** Tick the chats: Naara chat, Support chat, Business shared inbox, Messages and Send message, Staff replies. Tick all (or none) for every chat. A category with no GIFs, or switched off, is not shown to members.
* **Adding stickers.** From files, from a web address (checked for safety and size), or **Make a GIF from a video** right on the page: the same studio members use in chat. Choose a video, pick the moment, press **Save to library**. It goes into the category tab you have selected and is live in chat immediately.
* Limits: 3 MB and 800 px per GIF, 400 per category, 40 categories. Add only GIFs you have the right to use.

## 9. Troubleshooting in one table

| You see | Meaning | Do |
|---|---|---|
| Cron (scheduler): Fix | Nothing has run for 10 minutes | Add the cron line from section 2 |
| Waiting jobs: oldest is N minutes old | No worker is processing the queue | Shared: check the cron. VPS: check Horizon in Supervisor |
| Public address: Fix | `APP_URL` is local | Set the real https address, clear the config cache |
| ffmpeg: Not installed | Optional | Press Install ffmpeg now |
| Install failed: every source failed | Host blocks outgoing downloads | Use the download link or upload (section 4) |
| Agent process: Not running | Voice agent is down | Start it under Supervisor (section 6) |
| Twilio / Anthropic key: Fix | Key not saved | Admin > Provider keys |

`php artisan business:doctor` exits with a non-zero status while anything shows **Fix**, so a deploy script can stop on it.
