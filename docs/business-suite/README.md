# Naara Business Suite — master-only build record

> **MASTER ONLY. Never port to the white-label repos. Never merge this branch to `main`
> until Prompts 13–19 are ALL complete** (standing owner instructions, 2026-09-21 and
> 2026-10-02). The white-labels get their own front end later; nothing here lands there.

This folder is the permanent, in-repo record of the Business Suite so the work can be resumed
cold. Read this first, then `PROGRESS.md` (top of NEXT), then the spec for the next prompt in
`specs/`.

## 1. What the Business Suite is

A set of seven sequential build prompts that turn NaaraSim's consumer numbers product into a
business phone/communications product for organizations (teams with shared numbers, call
routing, recording, an omnichannel inbox, an AI voice agent, CRM integrations, a native
softphone). It is built on top of the existing wallet-group (`WalletGroup`) primitive instead of
a new `Organization` table.

| # | Prompt (spec in `specs/`) | Status | Depends on |
|---|---|---|---|
| 13 | Organizations, seats, business workspace access | **DONE** | Prompt 11 §3 (WalletGroup) |
| 14 | Business Phone Core: IVR, queues, ring groups, business hours | **DONE** | 13 |
| 15 | Call recording, transcription, conversation intelligence | **DONE** (counsel sign-off required before production, see below) | 13 (14 helpful) |
| 16 | Omnichannel inbox (WhatsApp two-way, Telegram, Instagram, email) | **DONE except inbound email** (needs a vendor decision, see below) | none (13 for shared inbox) |
| 17 | AI voice agent (Twilio ConversationRelay) | **DONE** (VPS only; needs a live Twilio ConversationRelay test) | **14 and 15** |
| 18 | CRM & integration marketplace (scoped API keys, webhooks) | **DONE** | 13 loosely |
| 19 | Native mobile softphone | **Server half DONE** (pairing, one-purpose tokens, Twilio access tokens with push credentials, device list, readiness check). **Native apps: starter source only** (`mobile/business-suite-softphone/`, not compiled), real-phone tests pending. See `NATIVE-SOFTPHONE.md` | 14 |

**Remaining: Prompt 19 only**, a native-mobile spec rather than Laravel work.

## 2. Branch discipline

- Work lives on the **`business-suite`** branch of the master repo only.
- Keep it current by merging `main` into it (never the reverse): `git checkout business-suite &&
  git merge main`. PROGRESS.md conflicts are resolved by keeping both sides.
- Commits use `[skip ci]` (CI is manual-only until the 2026-10-01 reset, then per owner).
- Test locally: `vendor/bin/phpunit` (sqlite in-memory) **and** boot against real MySQL for
  anything touching migrations — see §6, the Prompt 13 migration passed sqlite and failed MySQL.
- After every prompt: update PROGRESS.md (DONE entry + exact next step at the top of NEXT).

## 3. What is built

### Prompt 13 — Organizations, seats, workspace access
- `wallet_groups.kind` (`family|business`), `business_email` (+verification);
  `wallet_group_members` gained `role`, `status`, `invited_email`, nullable `user_id`
  (invite an email with no account yet; `WalletGroupService::attachRegisteredInvite()` links
  them at registration via `CreateNewUser`); `virtual_numbers.wallet_group_id` (nullable).
- **Roles = real Spatie team-scoped permissions** (`config('permission.teams') = true`), four
  business roles stored as `business_owner|admin|agent|viewer` (prefixed so they can never
  collide with the staff `admin` role). Permissions: `business.manage_group`, `manage_seats`,
  `manage_numbers`, `use_workspace`, `view_reports`, `spend` (`App\Support\Permissions\*`).
- **Sentinel team id `0`** (not Spatie's default `1`/null) marks every pre-existing staff/admin
  assignment, via `SentinelTeamResolver`, so no ambient staff check changes behaviour and no
  `wallet_groups.id` can ever collide with it.
- `WorkspaceRoleService` is the ONLY place that touches `setPermissionsTeamId()` (try/finally
  back to the sentinel) and it unsets the cached `roles`/`permissions` relations around every
  check — a real stale-relation bug was found and fixed here.
- Routes: `/business` (public landing, reuses Fortify), `/business/workspace` (create, invite,
  remove seats, switcher), signed `business/verify/{walletGroup}`, `POST /workspace/switch`.
  `App\Support\Workspace` = session-scoped active workspace, never re-authenticates.
- Deliberately NOT wired into the shared app shell nav (God-node-adjacent); reached via the
  routes above. Add a nav entry when the Suite has a surface worth linking.

### Prompt 14 — Business Phone Core (Twilio-only)
- Tables (additive; `call_forwarding_rules` untouched): `call_flows`, `call_flow_nodes`,
  `ring_groups`, `ring_group_members`, `business_hours_schedules`; `call_events` gained
  nullable `call_flow_id` / `call_flow_node_id` (one call-log table for both paths).
- `TwilioVoiceWebhookController` checks for an ACTIVE `CallFlow` (by called number) after
  signature verification and **before** the forwarding-rule lookup; none → the old path runs
  unchanged. `TwilioCallFlowController` serves continuations (`webhooks/twilio/call-flow/
  {flow}/{node}` + `/digits` + `/dial`).
- `CallFlowResolver` builds TwiML one node per round trip, **stateless** (query counters `h`
  hops, `a` menu attempts, `step` sequential leg). Node types and config shapes are
  documented on `CallFlowNode`: `menu`, `queue`, `forward`, `voicemail`.
  Forward nodes reuse `VoiceProviderInterface::forwardTwiml()`; a flow voicemail files into
  the workspace owner's inbox through the existing recording webhook.
- Ring groups: simultaneous = one `<Dial>` with many `<Number>`/`<Client>`; sequential =
  chained `<Dial action>` legs by `priority`. Seats resolve to an explicit number, else their
  own personal Twilio line; optional in-browser ring via the existing dialer client identity.
- `BusinessHoursSchedule::isOpenNow()` (IANA tz, overnight windows, holiday overrides).
- Builder UI `/business/call-flows` (`CallFlowBuilder`, 4 steps), owner/admin only
  (`MANAGE_NUMBERS`), 404 unless Twilio is active; Telnyx lines are hidden (Telnyx has no
  `VoiceProviderInterface` implementation); going live dispatches `SyncVoiceWebhookJob`.
- **Finding for Prompt 17:** simultaneous + sequential needed NO custom state store. A TRUE
  hold-queue (`<Enqueue>` + agent pickup, hold music, position-in-line, agent presence) was
  NOT built (`hold_music_url` is stored but unused) — it needs real state. Prompt 17's
  human hand-off can reuse the ring-group dial + overflow node as-is; waiting-in-line is net new.

### Prompt 15: call recording, transcripts, intelligence (2026-10-05)
Built to `docs/legal/CALL-RECORDING-CONSENT-STEER.md` with its recommended defaults. **Nothing here may reach production until counsel has signed off the matrix in `config/call_recording.php` (Nigeria plus one EU/UK reviewer at minimum).** Both modules ship OFF platform-wide, and OFF per workspace and per number.
- **Switches, three levels, all must be on:** platform module (`business.module.recording` / `.intelligence`, Admin > Business Suite), workspace (`call_recording_settings`, owner/admin only, `/business/recordings`), number (`virtual_numbers.recording_enabled`). Intelligence is a separate opt-in from recording and needs it.
- **Policy** (`RecordingPolicy`): strictest-wins across both ends by country (`config/call_recording.php`, unlisted country = strict). `notice` = spoken announcement then record; `consent` = caller must press 1 (platform setting `business.recording.consent_mode`, `keypad` default or `announce_only` if counsel agrees), no press = connects UNRECORDED. The person who answers hears a notice too (`<Number url=whisper>`). Never recorded: emergency numbers, workspace-flagged sensitive numbers, owner wallet below the minimum balance.
- **Capture:** `CallFlowResolver::withRecording()` wraps queue and forward nodes; `<Dial record="record-from-answer-dual" timeLimit=...>` with a signed `recordingStatusCallback`. The consent evidence (regime, caller action, countries, announcement version, time) travels in that signed query string and is stored with every recording (`call_recordings.consent`). A recording without it is deleted.
- **Pipeline (all queued, `$tries = 1`):** `StoreCallRecordingJob` (charge, download only from api.twilio.com, private disk, then delete the provider copy) -> `TranscribeCallRecordingJob` (reuses `VoiceSynthesizer::transcribe`, the voicemail STT; no new vendor, `transcript_source` records the engine) -> `SummariseCallJob` (Anthropic via `AnthropicClient::completeJson`; no phone numbers in the prompt; talk/listen ratio stays null because no speaker data exists).
- **Billing:** only `CallAddonBilling` -> `WalletService::charge()` (price from `PricingEngine::calculateCallAddonRetail`, supplier costs are admin inputs under Admin > Business Suite > Settings > Recording costs and are never shown to a workspace). Owner's wallet pays. One reference per recording per step (`call-record:ID` etc.). No retry on failure: a retry would find the refunded debit and deliver for free.
- **Retention and erasure:** default 30 days, workspace choice capped by the platform ceiling (365), `business:purge-recordings` daily (registered with `SchedulerHealth`), legal hold pauses it, account erasure purges owned recordings, data export carries metadata only.
- **Access:** workspace owner and admins only (`WorkspaceAdminAccess`); platform admins cannot play recordings; every play and delete is audited. The "agent on that call" from the steer is NOT covered because the platform does not record who answered.
- **Deviation from the spec:** personal accounts are not recorded at all (the steer says only a workspace owner/admin can enable recording). The page is new (`/business/recordings`) because Business has no existing call-history view to extend.
- Tests: `CallRecordingTest` (19), `BusinessRecordingsPageTest` (12).
- **At merge time:** add the Business Suite tokens to `App\Support\MasterOnlyModules` and each fork's `WhiteLabelBoundaryFitnessTest`, so none of this can ship in a package.

### Prompt 17: AI voice agent (2026-10-05)
Twilio ConversationRelay does speech-to-text, text-to-speech and barge-in (`interruptible="true"`; it sends an `interrupt` message with what the caller actually heard, which is all we handle). We run the part it hands to us: the conversation.
- **Flow:** a new call-flow node `ai_agent`. `CallFlowResolver::aiAgent()` holds a funded block on the owner's wallet, then returns `<Connect action=...><ConversationRelay url=wss://... welcomeGreeting=... ><Parameter session/sig/></ConversationRelay></Connect>`. Anything that stops the agent (module off, workspace off, no server address, owner cannot fund a minute) falls through to the node's `overflow_node_id`, or a polite goodbye. The greeting always ends with "You are speaking with an automated assistant." (`AgentGreeting`, not removable); that is also the consent evidence stored with the call.
- **Server:** `php artisan business:ai-voice-server --host=127.0.0.1 --port=8089`. A small pre-fork WebSocket server (ReactPHP/Ratchet RFC6455 pieces already installed for Reverb; `pcntl` required): one child process per call so a slow model answer never stalls another call. The upgrade is refused unless `X-Twilio-Signature` matches the WebSocket URL (auth token HMAC); `setup` is refused unless the session id's HMAC (`AgentToken`) matches, the session is in progress and unsettled, and the workspace still has the agent on. **VPS only** (a shared cPanel host cannot keep a process alive). Put it behind a TLS proxy and set the public `wss://` address in Admin > Business Suite > AI voice agent. Example unit:
  ```
  [Service]
  WorkingDirectory=/var/www/naarasim
  ExecStart=/usr/bin/php artisan business:ai-voice-server --host=127.0.0.1 --port=8089
  Restart=always
  User=www-data
  ```
- **Logic** (`AgentConversation`, no transport inside, tested with a scripted model): Anthropic via `AnthropicClient::converse()` with a tool loop (max 4 rounds); caller speech is passed as user content, the system prompt says it is never an instruction; the owner's persona text is the only business knowledge it may use.
- **Tools** (`AgentToolkit`): `transfer_to_human`, `book_callback`, `not_understood`, `end_call`. **Deviation from the spec:** `check_order_status` is not built. Naara's `SupportTools` look up the signed-in Naara customer's own orders; a workspace's callers are not Naara customers and the workspace has no order system to reuse. `book_callback` records the request on the session (no scheduler exists to reuse, none was invented). Prompt 18 is where a workspace's own systems become tools: add entries to `AgentToolkit`.
- **Hand-off triggers (all explicit):** the model calls `transfer_to_human`; the caller presses 0; the caller is not understood `max_clarifications` times in a row (empty speech or `not_understood`); the model is unavailable (hand over if the team is open, else end). Hand-off is an `end` message with `handoffData`; Twilio then calls our `action` URL, which dials the agent settings' ring group with the same `<Dial>` mechanics Prompt 14 built (`ringGroup()` falls back to the agent settings for an `ai_agent` node). Outside team hours the transfer tool refuses and the agent offers a callback. The person who answers hears a whisper "Call transferred from the assistant" plus the model's one-sentence summary (signed link). Sequential ring groups do not carry the whisper past the first leg.
- **Billing:** `AgentBilling` mirrors `VoiceDialerService` exactly: retail from `PricingEngine::calculateCallAddonRetail(cost, 'agent')` (supplier cost is the admin input "AI voice agent, per call minute"), atomic hold of the funded block before connecting (capped by "Longest agent call"), settle on hangup (whole minutes, refund the rest, refund in full if it never connected), idempotent under a row lock, so the server closing and Twilio's callback can both settle. The server also winds the call up 10 seconds before the funded time ends.
- **Reporting does not fork:** `AgentSessionFinalizer` files every agent call as a `call_recordings` row with `source = ai_agent` (no audio) plus its `call_transcripts` row, so it appears in the same Call recordings page (marked "AI assistant call") and gets the same intelligence summary if the workspace turned that on. Retention, legal hold, erasure and export apply unchanged.
- **UI:** `/business/assistant` (owner/admin): switch, name, welcome, what the assistant may say, language, hand-over ring group, team hours, misunderstanding limit, price, recent calls with callback requests. Skin-aware, in coverage batch 9.
- **Verified:** `AiVoiceAgentTest` (27) including a real RFC 6455 exchange over a socket pair, and a manual smoke test of the real forking server with a raw WebSocket client and a stub model endpoint (handshake, setup, prompt, reply, close, billing, transcript filing). **Not verified:** a real call through Twilio ConversationRelay; do that on a sandbox number before enabling in production (watch the exact URL Twilio signs, which depends on your proxy's Host header).
- Not built: streaming model replies (the whole reply is sent at once), multiple languages in one call, outbound agent calls.

### Business shell and the omni-channel inbox, in the Naara chat look (2026-10-05)

Owner direction: the Business Suite and the unified Messages inbox should feel as dedicated as Naara chat, with the same colour and eye-contrast scheme.

- **One shell for the whole user-facing Suite.** `components/layouts/business.blade.php` is built on the Naara chat shell (`.nn` rail / header / docked bottom bar), so every skin's chat signature and the generated chat contrast file apply without a second copy. Header: a workspace chip (switch workspace or return to Personal), bell, language, theme, More. Phone: bottom bar with a raised "New message" centre. Desktop: rail. Destinations come from `App\Support\Business\BusinessNav` (module-aware, owner/admin-only items hidden from agents, unread badge on Inbox).
- **Pages in the shell:** Overview (with an "at a glance" strip), Call flows, Recordings, Channels, AI assistant, and Messages when a business workspace is active. Personal Messages keeps the Numbers shell and the same inbox UI.
- **Inbox UI:** channel strip with per-channel unread (SMS, WhatsApp, Telegram, Instagram, each a contrast-checked colour, white text >= 5.7:1), conversation rows with a channel marker, Naara-chat bubbles (`.ncb`) with the outgoing bubble tinted by channel, day separators, the Naara composer for channel replies (SMS keeps the send modal so billing and routing are untouched), resizable three-panel layout on desktop, one pane at a time on phones. `?compose=1` opens the new-message composer (used by the shell's centre button).
- **Moving around:** the channel strip is a slider (arrows for a mouse, drag, touch swipe, edge fades). On desktop the conversations panel slides away from the thread header button (or by double-clicking its divider) and back; its width is remembered. On phones the thread slides over the list the way Naara chat does and follows the finger on a swipe back; swiping the other way opens contact info. The roots are `wire:ignore.self` so Alpine state survives Livewire updates (without it the pane/list bindings went dead after the first server round trip).
- **Composer:** channel replies use the Naara composer docked like Naara chat (`nc__foot`, floating over the wallpaper), its `--cc-*` tokens mapped onto the skin tokens; the send button is an outlined arrow when empty and a solid accent disc with a white arrow when there is text (`composer-contrast.cjs` scans 35 skins x light/dark x 3 accents: icon and placeholder >= 4.5:1).
- **Contrast:** `tests/visual/skins/contrast-biz.cjs` + `contrast-biz.py` scan every skin x light/dark on the shell (`ROOT=.nn`, `VW=` for width, `OUT=` for the output folder). Header chips on the light-mode teal band are solid deep teal with white text. The language chip is opaque on every skin (`chip-contrast.cjs`, guard test `LanguageChipContrastGuardTest`).
- **Also fixed on the way:** `n2n/floating-chat` used a `try` statement as an Alpine `x-init`, which Alpine cannot evaluate (a console error on every page that showed the Naara chat button).
- **Tests:** `BusinessShellTest` (6).

### Inbox composer parity, file sending and GIF Studio (2026-10-05)

Owner direction: the Business inbox composer must have every Naara chat composer feature, files must really go out over WhatsApp / Telegram / Instagram, and the admin configuration behind it must be solid.

- **Same composer as Naara chat** (`<x-composer>`), trimmed per channel by `InboxComposerConfig` so a button that could only fail is never shown: formatting, emoji, hints, snippets, GIF + GIF Studio, attach (photo, camera, video, document, audio, location, contact; poll on Telegram only), voice notes (offered only when ffmpeg exists), scheduled send, offline outbox with retry. WhatsApp has no polls; Instagram has no documents, location, contacts or polls and 1,000 characters.
- **Sending path:** the composer posts to `POST /business/inbox/send` (`InboxSendController` -> `InboxComposer` -> `ChannelSender`). Multipart `meta` + `files[i]` + `voice`, `Idempotency-Key` (a retried request never delivers twice). Files are stored privately (`inbox-out/{workspace}/...`), handed to the provider as a short-lived link (object-store temporary URL, else our signed `business.inbox.media` route that only resolves `inbox-out/` and sends `nosniff` + a sandbox CSP), and shown to the team through the same kind of link. Per channel: text as HTML (Telegram) / WhatsApp markup, caption on the first file when it fits, GIF = animation on Telegram, mp4 on WhatsApp (ffmpeg), image on Instagram; voice notes re-encoded to m4a (ffmpeg). Anything a channel cannot carry is refused BEFORE anything is sent or stored, with a plain sentence; a refusal comes back as 422 `{message}` and the composer shows it and hands the text and files back.
- **Records:** one `outbound_messages` row per part that went out (`attachment_kind/name/mime`); the reference is scoped to the conversation (Telegram message ids are only unique per chat), and a clash after delivery can never become an error that makes the person send again.
- **Scheduling:** a future `scheduledAt` stores a `scheduled_channel_messages` row (files already stored). `business:send-scheduled` runs every minute, claims rows `pending -> sending` before queueing `SendScheduledChannelMessageJob` (tries 1, never retried: at most once), failures keep the reason and show in the thread with a dismiss button; pending ones can be cancelled (files deleted). Registered with `SchedulerHealth`.
- **Admin configuration (Admin > Business Suite):** Settings > "Shared inbox sending" (files on/off, scheduling on/off, largest file, files per message, furthest schedule, link lifetime, sends per person per minute; all enforced server-side, audited like every Suite setting) and a new **Sending readiness** tab (`InboxReadiness`): module on, public https `APP_URL` (providers fetch media from it), private storage round trip, ffmpeg, PHP upload limits vs the file ceiling, scheduler heartbeat, queue driver, overdue scheduled messages, connections in error. Each failing check says the one thing to change.
- **GIF Studio fix:** the Make / Save / Use bar is pinned to the bottom of the panel (always reachable on a phone) and the preview shrinks on short screens.
- **Not possible here / honest limits:** ffmpeg is not installed on the dev host, so WhatsApp GIFs, WhatsApp voice notes and non-MP4 video are refused with a clear message when ffmpeg is missing (installed on a VPS, they work); real provider delivery needs each workspace's own credentials, so tests fake the three provider APIs. Meta requires https on the media link and only allows replies within 24 hours of the person's last message.
- **Tests:** `InboxComposerSendTest` (18); `tests/visual/skins/inbox-composer-e2e.cjs` (phone: toolbar, file send, GIF Studio from a clip); `composer-contrast.cjs` (140 checks) and `contrast-biz.cjs` (inbox + admin readiness) clean across all skins.

### Channel aurora headers, GIF library and animated stickers (2026-10-05)

- **Channel aurora (conversation header only):** the thread header wears the channel's own brand as a slow, premium aurora of deep brand tones: Telegram blue, WhatsApp green, Instagram sunset (purple, magenta, orange), Naara teal for SMS (`nx-business.css`, `.nc__thread[data-ch]`). The palette follows the thread's real channel. Every stop is dark enough that white text holds >= 4.5:1 on any pixel in every skin and mode: `tests/visual/skins/aurora-contrast.cjs` samples the rendered pixels (560 checks, lowest 4.52). Buttons, name and the channel chip are re-inked for the dark band; motion stops under `prefers-reduced-motion`.
- **GIF and sticker library (shared by Naara chat, support and the inbox):** `gif_library_items` + `GifLibrary` service, shelves in the composer GIF panel (All, My GIFs, Stickers, Funny, Love, Reactions, Celebrate, Thanks, Greetings, Business, Motivation), search, tap to send, star to keep in My GIFs. 79 shipped items under `public/images/gifs/`: 31 animated stickers (cartoon characters: laughing, heart eyes, cool, alien, ghost, robot, cat, trophy, rocket...) and 48 animated text cards. All are original artwork drawn by `tools/gif-generator/` (no third-party content, so free to ship). Registered by the migration and by `php artisan gifs:seed`.
- **Importing from any source:** Admin > GIF library: upload GIFs, or import from any web address (SSRF-guarded, redirects re-checked, real-GIF check, <= 3 MB, <= 800 px), rename, move between shelves, hide, remove (audited). Live search across millions of GIFs: add a GIPHY or Tenor key in Provider keys ("Chat GIF search"); the browser never sees the key, results are family-safe, the provider's name is shown beside them. We deliberately do NOT bulk-copy memes from GIPHY/Tenor/the web into the repo: they are copyrighted and the providers' terms forbid redistribution. Admins add only GIFs they have the right to use.
- **Fixed on the way (shared composer):** `.cc button { color: inherit }` out-ranked every button class, so icons such as the GIF Studio play arrow and the save-star rendered dark on dark in several skins; the rule is now zero-specificity (`.cc :where(button)`) and the play control is pinned to the accent ink (`gs-play-contrast.cjs`: 210 checks, lowest 4.79).
- **Tests:** `GifLibraryTest` (9), `BusinessShellTest` (+2), phone checks in `inbox-composer-e2e.cjs`.

### Prompt 18: CRM and integration marketplace (2026-10-05)

Zapier-first, no native CRMs. Strictly additive on the existing Developer API; it is not an event bus.

- **Scopes.** `ApiClient::BUSINESS_SCOPES` = `contacts:read`, `calls:read`, `messages:read`, `webhook:subscribe`. They exist on a key only when it is bound to a business workspace (`api_clients.wallet_group_id`), the `integrations` module is on, and the key's owner owns or administers that workspace. `ApiClientService` drops them otherwise. Business keys also need the Developer API master switch (`developer_api.enabled`) because they live under `/api/v1`.
- **Read-only endpoints** (`/api/v1/business/{contacts,calls,messages}`), cursor-paged, scoped to the key's workspace. No cost fields, no audio, no transcript text. `EnsureBusinessApi` answers a plain 404 when the module is off, the key is not bound, or its owner stopped being an admin (an unbound key never learns the surface exists).
- **Webhooks.** `webhook_subscriptions` (per key, per event). Events: `call.completed` (a flow call reached answered/no-answer), `message.received` (SMS on a workspace line, WhatsApp, Telegram, Instagram), `call.recording_ready` (metadata only). One guarded `WebhookEvents::fire()` call at each existing event point (`LogCallEventJob`, `RecordInboundMessageJob`, `RecordChannelMessageJob`, `StoreCallRecordingJob`); errors there are swallowed so an integration can never break a call or message.
- **Delivery.** `DeliverBusinessWebhookJob`: `X-Naara-Signature: sha256=HMAC(raw body, secret)` (compare with `hash_equals`), `X-Naara-Event/Delivery/Timestamp`, https only, SSRF-checked at registration AND at delivery, no redirects, 5 tries with 30s/2m/10m/30m backoff. Three consecutive exhausted deliveries flip the subscription to `failed`; the owner sees it (and a "Switch on" button) in the Developer portal. The signing secret is shown once and encrypted at rest.
- **Developer portal** (`/developer`): workspace picker, business scope chips, per-key webhook panel. Hidden entirely while the module is off.
- **Zapier app definition** in `docs/business-suite/zapier/` (new message, new call, recording ready triggers, REST-hook subscribe/unsubscribe). Publishing it to the Zapier directory is a manual owner step (see its README).
- **Tests:** `IntegrationsApiTest` (17).

### Prompt 16: shared inbox, channels (2026-10-05)
Adds a CHANNEL dimension to the inbox that already exists (`inbound_messages`, `outbound_messages`, `message_threads`) instead of a second inbox. Module switch `business.module.inbox` (default off, Admin > Business Suite); off means channel webhooks are acknowledged and ignored, `/business/channels` shows Coming soon, and channel threads are hidden everywhere.
- **Schema** (`2026_11_12_100000_add_channels_to_the_inbox`, up/down/up verified on MySQL): `channel` (default `sms`, so every existing row is an SMS with no row rewrite) and nullable `wallet_group_id` on the three tables; thread uniqueness widened from `(user, counterpart)` to `(user, channel, counterpart)` (the new index is added before the old one is dropped; widening cannot create a collision, so no data audit was needed); `outbound_messages.virtual_number_id` made nullable (a channel reply is not sent from one of our lines); `message_threads.opted_out_at`; new `channel_connections`.
- **Design decision, not in the spec:** channels belong to a WORKSPACE, not to Naara. Each workspace connects its OWN accounts (`channel_connections`, credentials encrypted, write-only in the UI, owner/admin only at `/business/channels`). Inbound rows are owned by the workspace owner and tagged with `wallet_group_id`; seats with `USE_WORKSPACE` see them in Messages when that workspace is active. Personal Messages is unchanged and never shows channel threads.
- **Meta (WhatsApp, Instagram): bring your own Meta app.** Each connection has its own callback URL (`/webhooks/meta/{id}`), its own generated verify token and the app secret the workspace supplies, so signatures are checked per connection (`X-Hub-Signature-256`, raw body, `hash_equals`, fail closed). This means Naara's platform WhatsApp webhook (`WhatsAppWebhookController`, with its STOP handling for Naara's own users) is **untouched**, and a workspace's customer is never treated as a Naara user. The alternative (all workspaces under one Naara-owned Meta app via Embedded Signup) needs Meta tech-provider review and is a future option.
- **Telegram:** token verified with `getMe`, webhook registered with a per-connection secret echoed in `X-Telegram-Bot-Api-Secret-Token` (`hash_equals` before reading the body). Private chats only. `provider_ref` is `tg:{connection}:{update_id}` (update ids are only unique per bot).
- **Replies:** `MessageDispatcher::sendReply()` switches on `thread->channel` (sms delegates to `MessageSenderService`; whatsapp/telegram/instagram use the workspace's own account, so there is no Naara wallet charge: the channel's provider bills the workspace). `WhatsAppCloudClient::sendMessage()` added, `sendTemplate()` untouched. WhatsApp and Instagram only allow replies inside 24 hours of the person's last message; a rejected send writes nothing and says so.
- **Opt-out:** STOP / UNSUBSCRIBE / CANCEL / `/stop` marks the thread (`opted_out_at`); nothing is sent until the person writes again. SMS threads keep the existing send modal.
- **Messages UI:** channel tabs (only when the inbox module is live), channel badge per thread, inline reply for channel threads. Tests: `SharedInboxTest` (21).
- **Inbound email: NOT built, needs a decision.** Naara has no single transactional mail vendor (`MAIL_MAILER` is SMTP/log by default and cPanel installs use plain SMTP), and plain SMTP cannot receive inbound-parse webhooks. Options: (a) add one inbound-parse vendor (Postmark, Mailgun or SendGrid) for this channel only, (b) per-workspace IMAP polling, (c) skip email. The channel plumbing (`channel = 'email'`, `RecordChannelMessageJob`, `MessageDispatcher`) is ready for whichever is chosen.
- Not built (out of the spec's scope): media attachments from channels (a plain "[image received]" marker is filed), group chats, per-seat assignment of conversations.

### Control panel (2026-10-05)
Admin > Business Suite has three tabs: **Settings** (every switch and limit, rendered from `App\Support\Business\BusinessSettingsSchema`; default shown, per-field Reset, audited as `business_suite.settings_changed`, super admin writes / admin reads, risky changes need a tick and alert the other super admins), **Workspaces** (read-only oversight) and **Activity** (audit trail).
- Typed accessors: `BusinessSettings::module('call_flows')`, `::limit('seats')`, `::call('max_hops')`, `::workspaceCreationOpen()`. Adding a knob = one schema entry + (if needed) an accessor.
- **Modules:** `call_flows` (live, default on; off => builder shows Coming soon, flows stop answering, numbers fall back to forwarding). `recording`, `intelligence`, `inbox`, `ai_voice`, `integrations` are in the schema as `available: false` (default off, cannot be switched on, `module()` is false whatever is stored). When a module ships, flip `available` to true and guard its routes with `feature.live:<key>` (add the key to `ComingSoon::FEATURES` + `isLive()`).
- **Limits (never remove existing data, only stop new additions):** seats (enforced in `WalletGroupService::inviteSeat`, a re-invite never counts), numbers, ring groups, people per ring group, steps per flow (all in `CallFlowBuilder`). Sign-ups can be paused (`Business::createWorkspace`).
- **Call behaviour:** `max_hops`, `menu_attempts`, `leg_seconds` are read by `CallFlowResolver` per call (the old constants are gone).
- Tests: `tests/Feature/BusinessSuite/BusinessControlPanelTest.php`.

### Master switch (2026-10-05)
- `App\Support\Business\BusinessSuiteSettings` (`business_suite.enabled`, **off by default**, never on outside the master install) + middleware alias `business.suite` (`EnsureBusinessSuiteEnabled`) on every `/business*` route, the workspace switcher and the three call-flow webhooks. Admin page: Admin > Business Suite (`admin.business-suite`).
- **Off means:** a page load shows the friendly skin-aware "Coming soon" sheet (`App\Support\ComingSoon`, applies to every feature switch: Business Suite, N2N, Naara Gift, app download; webhooks/API/POSTs and non-master installs stay a plain 404); `TwilioVoiceWebhookController` skips the call-flow lookup so org numbers use the forwarding path exactly as before; nothing is deleted; no nav entry. Tests that need it on use `Tests\Concerns\EnablesBusinessSuite`.
- **Skins:** every Suite view is on the skin kit and registered (coverage batch 9); `BusinessSuiteSkinDisciplineTest` fails if a new Suite view (path containing business, call-flow, recording, transcript, omnichannel, voice-agent, crm, softphone) is unregistered, unconverted or has a hard-coded colour.
- Prompts 15-18 must gate their routes/jobs/webhooks the same way (`business.suite`, or `BusinessSuiteSettings::enabled()` in jobs).

## 4. Shared files the Suite modifies (review these on any merge)

`app/Actions/Fortify/CreateNewUser.php`, `config/permission.php` (teams on),
`app/Models/{WalletGroup,WalletGroupMember,VirtualNumber,CallEvent}.php`,
`app/Services/Wallet/WalletGroupService.php`, `app/Jobs/LogCallEventJob.php`,
`Webhooks/TwilioVoiceWebhookController.php`, `Webhooks/TwilioRecordingWebhookController.php`,
`routes/web.php`, `database/seeders/DatabaseSeeder.php`, `icon-sprite` (`briefcase`),
`resources/js/composer/{composer,gifstudio}.js` + `composer.css` (shared with Naara chat: server `message` surfaced on a refusal, GIF Studio pinned action bar), `lang/*/messages.php` (`sched_*`).
Everything else is new files under `app/**/Business*`, `Permissions/`, `Voice/`, the Prompt
13/14 migrations, the `business*`/`call-flow*` views, and `tests/Feature/BusinessSuite/`.

## 5. Master-only rules that apply here

1. Not shipped to either white-label repo, ever, unless the owner reverses this explicitly.
2. If/when merged to `main`, check how packages are built for white-labels
   (`UpdateManifest` `distribution_scope = master_only`, `PackageBuilder`/`PackagePublisher`):
   the Suite's files must be excluded from any package pushed to a child.
3. Money rule reminders for upcoming prompts: funded-block billing (quote → hold → settle →
   refund) is the ONLY pattern for metered minutes (15, 17); never a second billing path.
4. Provider names must not leak to users (supplier masking); no cost figures in payloads.

## 6. Gotchas learned the hard way

- **Run migrations on real MySQL before calling a migration done.** The Prompt 13 teams
  migration passed every sqlite test and failed on MySQL (`DROP PRIMARY KEY` refused: the PK is
  the only index behind the `role_id`/`permission_id` FK). Fixed by dropping/re-adding the FK
  around the key change; verified up→down→up.
- **Twilio signs the URL byte-for-byte as given, query string included.** `Request::fullUrl()`
  re-sorts query keys and breaks the HMAC; `TwilioCallFlowController::signedUrl()` rebuilds it
  from the raw `QUERY_STRING`. Any new continuation route must do the same.
- Every id arriving from a browser or a Twilio callback is re-resolved INSIDE the flow /
  workspace; never trust an id by itself (tests lock cross-flow and cross-workspace refs).
- `<Dial callerId>` uses the original caller's number, same as the forwarding-rule path.

## 7. How to resume

1. `git checkout business-suite && git merge main`; run the Business Suite tests:
   `vendor/bin/phpunit tests/Feature/BusinessSuite` (79 tests at end of Prompt 14).
2. Read `specs/NAARA-PROMPT-15-RECORDING-AND-INTELLIGENCE.md` in full — and read the actual
   committed state of `CallFlow`, `CallFlowResolver`, `CallEvent` first (do not rely on memory).
3. Build 15 → (16 may go any time) → 17 → 18 → 19(spec handoff). One prompt at a time.

## 8. Merge gate (when 13–19 are all done)

- Full suite green on sqlite AND a manual MySQL boot (migrate + seed + click-through).
- Re-verify the shared-file list in §4 against `main` (conflicts with whatever shipped since).
- Confirm white-label exclusion (§5.2), re-run the "does this reference another instance /
  license flow / oversight?" test from CLAUDE.md, then get the owner's explicit go-ahead.


## Setup, hosting and media conversion

See [SETUP-AND-HOSTING.md](SETUP-AND-HOSTING.md): shared hosting versus VPS, the one cron entry, the queue, the ffmpeg installer (system copy, managed copy in `storage/`, your own link or upload, pure-PHP voice-note fallback), the AI voice agent steps, admin-made sticker categories and the GIF maker, and a troubleshooting table. In the admin it is **Business Suite > Setup and health**; in a terminal `php artisan business:doctor [--fix]` and `php artisan ffmpeg:status` / `ffmpeg:install`.
