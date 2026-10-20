# Naara Respond: what is built, what you add at launch (2026-10-19, master only)

Everything below marked BUILT runs and is covered by tests. Switch it on in Admin > Business Suite > "Naara Respond inbox" (off by default).

## Built and proven with no provider keys
| Area | What works | Proof |
|---|---|---|
| Omni core | Contacts, identities, conversations, messages, raw-event inbox, idempotent pipeline, out-of-order statuses, 24-hour window, outbox with idempotency key | `OmniPipelineTest` |
| Telegram | Signed webhook, private chats, attachments as descriptors, text replies, connect via bot token (omni webhook when the switch is on) | `TelegramAdapterTest`, `ConnectWebChatTest` |
| Web chat | Signed visitor token, per-site CORS, honeypot, limits, embed script, connect card with snippet | `WebChatTest`, `ConnectWebChatTest` |
| Email | Signed forwarding webhook in, replies out from the workspace address, no mail loops (bounces and auto-replies ignored) | `OmniAutomationTest` (email) |
| Inbox | List, search, filters, Mine/Unassigned, bulk actions, overdue badge, notes, saved replies, AI suggestion, broadcast consent toggle | `RespondInboxTest`, `RespondAutomationPageTest` |
| Knowledge base | FAQ, notes, web pages (SSRF-guarded crawler), chunking, FULLTEXT search, workspace isolation | `OmniAutomationTest` |
| AI assistant | Off / Suggest / Auto; answers only from the knowledge base; hands over on "human", refund, injection attempts; daily cap; one decision per message; token log | `OmniAutomationTest` (fake model) |
| Flows | Triggers (new conversation, keyword, outside hours), steps (send, wait, status, tag, note), validation, test mode, loop protection, starter pack | `OmniAutomationTest` |
| Broadcasts | Opt-in only, STOP/START handling, segments, dry run, scheduling, throttling, cancel | `OmniAutomationTest` |
| Webhooks | Signed outbound events, public https only, retry, auto-off after 10 failures | `OmniAutomationTest` |
| Insights and health | Volumes, median first reply, AI counts, per-channel green/amber/red | `OmniAutomationTest`, `naara:verify omni` |

## You add these on the live server
1. **Anthropic key**: the assistant uses the existing platform gateway (`ANTHROPIC_API_KEY`, `services.anthropic`). Without it the assistant logs an error and the customer simply is not answered by the AI.
2. **Queue + cron**: shared host: one `schedule:run` cron every minute and `QUEUE_CONNECTION=database`. VPS: Redis + Horizon. Broadcasts start from the scheduler (`omni:start-due-broadcasts`).
3. **Mail transport**: standard `MAIL_*` settings, needed for email replies.
4. **Telegram**: a BotFather token per workspace (they paste it in Channels).
5. **Broadcast price**: set `OMNI_BROADCAST_COST` once you decide how broadcasts are billed (0 = no estimate shown).
6. **Run** `php artisan migrate`, then `php artisan naara:verify omni`.

## Not built yet (needs something from you first)
- **WhatsApp, Instagram, Messenger** (blueprint Phase 3): the adapters, the signed webhooks, the Meta GET handshake, the 24-hour window and one-click Embedded Signup are BUILT and tested against Meta's documented payloads. What is left is yours: Meta app secret and verify token saved in Admin > API Keys, the Embedded Signup Config ID, Business verification, Tech Provider status and App Review, then one live round trip per channel. Broadcasts on these channels stay blocked until approved templates exist.
- **SMS into the inbox** (Phase 4): BUILT. A workspace turns on Business > Channels > "SMS on your workspace number"; inbound texts come through the existing signature-verified SMS webhook, replies use the one SMS pipeline. What is left is yours: point the carrier's inbound webhook at `/webhooks/sms-inbound/{provider}` on the live account and run one real text each way (GATE 9). Voicemail into the inbox is not built.
- **Calendar booking, Google Sheets, Zapier/Make listings, payment links** (Phase 10 rest): need those accounts' OAuth apps. Outbound webhooks already cover Zapier/Make/n8n.
- **WhatsApp template manager** (Phase 3): BUILT (Respond > Templates, with Meta's status and quality rating, create and delete, and send-template from a closed conversation). Needs a live WhatsApp Business Account to exercise.
- **Measured accuracy report** (Phase 5/6 proof): needs three real brands' content and a live model key.
- **WordPress plugin for web chat**: BUILT (download it from the web chat card on Business > Channels). **Brand-profile auto-import**: not started.
