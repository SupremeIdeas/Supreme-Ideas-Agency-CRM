# Naara Respond: Phase 0 grounding (master only)

Checked 2026-10-19 against the committed code and the providers' official pages. Nothing below is assumed: every row names its evidence. Blueprint: `NaaraSim-Omnichannel-Automation-Blueprint.md`; credentials list: `NaaraSim-Omnichannel-Credentials-Checklist.md`.

## 1. What exists in the code today

| Area | State | Evidence |
|---|---|---|
| Telegram channel | WORKING (connect, send, receive) | `app/Services/Inbox/TelegramBotClient.php` (getMe, setWebhook with `secret_token` and `allowed_updates`, deleteWebhook, sendMessage, any send method); `Webhooks/TelegramWebhookController` compares `X-Telegram-Bot-Api-Secret-Token` with `hash_equals` before reading the body |
| WhatsApp Cloud API | PARTIAL: send text and media, inbound webhook, autopilot | `app/Services/WhatsApp/WhatsAppCloudClient.php`, `WhatsAppAutopilot.php`, `routes/web.php` (`/webhooks/whatsapp`, `/webhooks/meta/{connection}` with `X-Hub-Signature-256` HMAC and per-connection verify token); `Jobs/SendWhatsAppTemplateJob.php` sends a template |
| Instagram / Messenger | PARTIAL: send text and media through the Graph API | `app/Services/Inbox/InstagramClient.php`; connect button in `Livewire/BusinessChannels` |
| Channel connect UI | Connect, pause, disconnect for Telegram, WhatsApp, Instagram | `Livewire/BusinessChannels`, `Models/ChannelConnection`, `Services/Inbox/ChannelConnector` |
| Shared inbox | WORKING in the Business shell (Messages) | `Livewire/Messages`, `Services/Inbox/*`, `Services/Messaging/ConversationTimeline` |
| Brain / AI reply | EXISTS for Naara users and the AI voice agent | `Services/Brain/*`, `Services/Business/AiVoice`, `Livewire/BusinessAssistant` |
| Call flows | WORKING for voice | `CallFlowBuilder`, `CallFlowResolver` |
| Business hours | EXISTS | `Models/BusinessHoursSchedule` |
| Integrations | Workspace API key and signed webhooks | `Services/Business/Integrations` |

## 2. What does not exist (searched, nothing found)

No Embedded Signup, no human-agent tag handling, no 24-hour window state machine, no template manager (only a single template send job), no `omni_*` tables, no business CRM contacts or lifecycle stages, no broadcasts to business contacts (the only broadcast code is the email one and realtime events), no knowledge base or retrieval, no unified flow engine for chat. These are the real build list; the blueprint's phases 1 to 12 stand.

## 3. Provider facts verified today from official pages

| Fact | Source | Result |
|---|---|---|
| Telegram `setWebhook` takes `secret_token` (1 to 256 chars, A-Z a-z 0-9 _ -), echoed in header `X-Telegram-Bot-Api-Secret-Token` | core.telegram.org/bots/api | VERIFIED; our client and controller match it. Current Bot API version 10.3 |
| Latest Meta Graph API version | developers.facebook.com/docs/graph-api/changelog/versions | v26.0 (29 Jul 2026). v25.0 expires 29 Jul 2028; **v21.0, which this code pinned, expires 21 Jan 2027** |
| WhatsApp pricing model | developers.facebook.com/docs/whatsapp/pricing | VERIFIED: per delivered message by category and country since 1 Jul 2025; 24-hour service window; 1,000 free service messages per number per month; free entry-point window of 7 days after a Click to WhatsApp ad |
| The blueprint's UNVERIFIED claim that service and in-window utility messages become chargeable from 1 Oct 2026 | same page | VERIFIED: from 1 Oct 2026 Meta charges service messages beyond the 1,000 monthly free tier and utility templates sent inside an open window; a payment method had to be added by 30 Sep 2026 |
| New: "Meta Business Agent" AI responses on WhatsApp (from 1 Jul 2026) are billed per token, about USD 2 per million tokens | same page | NEW FACT to design the AI cost meter around; the Naara agent must still be a business-specific assistant (policy read still owed) |

## 4. Fix made now from this grounding

The WhatsApp and Instagram clients defaulted to Graph **v21.0**, which Meta retires on 21 Jan 2027. The default is now **v25.0** (supported to 29 Jul 2028) in master and both forks (`config/services.php` `WHATSAPP_GRAPH_VERSION`, the three clients, `docs/WHATSAPP-AUTOPILOT.md`). Message payloads are unchanged between these versions for the calls we make. An install that set `WHATSAPP_GRAPH_VERSION` in `.env` keeps its own value; the admin Updater version-watch flags the old one.

## 5. Still owed before Phase 1 (owner and spikes)

1. Meta Business verification, a Business-type app, Tech Provider onboarding and App Review (the credentials checklist section 1), the long pole.
2. Decision: WhatsApp billing model (client pays Meta directly is the default).
3. Read Meta's current policy on AI chatbots on WhatsApp (not fetched yet; the pricing page now treats Meta's own agent as a separate billable category).
4. Spikes S1 to S9 from the blueprint, starting with S8 (Telegram plus web chat vertical slice), which the existing Telegram client already half answers.

## 6. Why the images were held back

The owner's four mock-ups are stored (trimmed, WebP, 8 to 23 KB each) under `public/images/marketing/business-suite/`. Only the team and call routing picture is on the landing page now, because ring groups, call menus, roles and the shared inbox exist today. The AI click-to-chat ad, the conversation analytics panel and the integrations grid depict Naara Respond features and third-party logos that are not built yet; they go on the page when the matching phase ships, so the page never claims more than the product does.
