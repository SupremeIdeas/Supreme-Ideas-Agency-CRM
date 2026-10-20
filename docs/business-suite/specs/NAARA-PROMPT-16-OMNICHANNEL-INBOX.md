# NAARA PROMPT 16 — Omnichannel Inbox (WhatsApp Two-Way, Telegram, Instagram, Email)
**For: Claude Code, working directly against the NaaraSim-main codebase**
**Depends on:** none functionally — this can ship independently of Prompts 13–15. If shared/org-level inbox access (multiple seats seeing the same conversations) is wanted immediately, do Prompt 13 first; otherwise this prompt is self-contained.

**Confirmed before writing this prompt — read this carefully, it changes the shape of the whole prompt:** `Messages.php`, `MessageThread`, `InboundMessage`, and `OutboundMessage` already exist and already work as a general inbox, not an SMS-only feature. Exact current schema:
- `inbound_messages`: `user_id`, `virtual_number_id` (**nullable**), `from_number`, `body`, `attachment_url`, `provider`, `provider_ref`, `read_at`, `received_at`. Unique constraint on `(provider, provider_ref)` — this is already the webhook-idempotency mechanism a Telegram/Instagram webhook needs.
- `message_threads`: `user_id`, `virtual_number_id` (**nullable**), `counterpart_number`, `last_body`, `last_direction`, `last_at`, `unread_count`. **Unique constraint today is `(user_id, counterpart_number)` only** — this is the one thing that must change (§1).
- `MessageThread::record(...)` is an existing static upsert method already used by both inbound and outbound message writes to keep the thread summary current.
- `WhatsAppCloudClient::sendTemplate()` exists (outbound, pre-approved templates only). There is no `sendMessage()` for free-form replies yet.
- `WhatsAppWebhookController::handleInbound()` today does exactly one thing with an inbound message: checks for `STOP`/`UNSUBSCRIBE`/`CANCEL` and flips `whatsapp_opt_in` off. Every other inbound message is read and discarded — it never becomes an `InboundMessage` row.

**This prompt is not "build an omnichannel inbox." It is "add a channel dimension to the inbox that already exists," plus wire the webhooks that currently discard everything.**

---

## §1 — Schema change: add `channel`, widen the uniqueness constraint

**Build:**
1. Migration: add `channel` column to `inbound_messages`, `outbound_messages`, and `message_threads` — string, default `'sms'`, backfill every existing row to `'sms'` explicitly in the migration (don't rely on the column default alone for historical rows if any existing row-count is large enough that an implicit backfill could be slow/locking — batch it if needed).
2. Migration: drop the existing `message_threads` unique constraint on `(user_id, counterpart_number)` and replace it with `(user_id, channel, counterpart_number)`. This is the one schema change with real behavioral consequences — the same phone number texting AND WhatsApp-ing today would incorrectly collapse into one thread; after this change they're correctly two threads. Write a short data-audit query before running this in production to check whether any existing `counterpart_number` values already collide across what would become different channels (they shouldn't, since WhatsApp inbound is currently discarded — but verify rather than assume).
3. `counterpart_number` stays the column name even though Telegram/Instagram identities aren't phone numbers — store the platform-native identifier there regardless of format (a Telegram chat ID, an Instagram-scoped user ID) rather than renaming the column, to avoid touching every existing SMS-path reference to it. Add a short comment on the column noting it's channel-relative, not always a phone number.

## §2 — WhatsApp: from outbound-only to real two-way

**Build:**
1. `WhatsAppCloudClient::sendMessage(string $to, string $body): array` — new method, using Meta's regular message-send endpoint (not the template endpoint `sendTemplate()` already uses), valid within the 24-hour customer-service window Meta enforces after an inbound message. If a business needs to message outside that window, that still requires a pre-approved template — keep `sendTemplate()` as-is for that case, don't remove it.
2. Rewrite `WhatsAppWebhookController::handleInbound()`: keep the existing `STOP`/`UNSUBSCRIBE`/`CANCEL` check exactly as it is (real, needed, working), but for every other inbound message, additionally write an `InboundMessage` row (`channel = 'whatsapp'`, `provider = 'whatsapp'`, `provider_ref` = Meta's message ID for idempotency) and call `MessageThread::record(...)` exactly the way the SMS inbound path already does.
3. Any outbound free-form reply a human agent sends from `Messages.php` while a WhatsApp thread is open calls the new `sendMessage()`, writes an `OutboundMessage` row (`channel = 'whatsapp'`), and updates the thread — mirroring the existing SMS outbound path structurally.

## §3 — Telegram (new, low-effort — no approval process, free API)

**Build:**
1. New `TelegramBotClient` service, same shape as `WhatsAppCloudClient` (`sendMessage()`), using the Telegram Bot API.
2. New `TelegramWebhookController`, same signature-verification-then-log-then-process shape as the existing webhook controllers, writing `InboundMessage` rows with `channel = 'telegram'`, `provider = 'telegram'`, `provider_ref` = Telegram's `update_id` for idempotency.
3. No opt-out keyword handling equivalent is required by Telegram the way Meta requires STOP-word compliance for WhatsApp, but implement a basic `/stop` command handler anyway for user-respect parity, writing to the same opt-out mechanism used for WhatsApp if one exists, or a new `telegram_opt_in` flag on the relevant model if not.

## §4 — Instagram (shares Meta credentials with WhatsApp)

**Build:**
1. New `InstagramWebhookController` using Meta's Graph API (Instagram Messaging) — this runs through the same Meta Business Manager app/credentials already configured for WhatsApp, so credential setup is additive configuration, not a new developer-account relationship.
2. Same `InboundMessage`/`MessageThread` write pattern as §2/§3, `channel = 'instagram'`.
3. Outbound replies: Instagram Messaging's own send endpoint, added to a small `InstagramClient` service (or folded into a shared `MetaMessagingClient` if the WhatsApp and Instagram send-call shapes turn out similar enough to share code — evaluate during implementation rather than deciding up front).

## §5 — Inbound email (lowest priority in this prompt — verify before building)

**Build:**
1. Before writing any new code: check whether the transactional mail provider Naara already uses for outbound notification emails supports inbound-parse webhooks (most major providers — Mailgun, Postmark, SendGrid — do, as an add-on route). If it does, this is a webhook controller addition, same pattern as everything above, `channel = 'email'`. If the current provider doesn't support it, that's a real decision point (add a second mail vendor just for inbound parsing, or deprioritize this channel) — surface that finding rather than silently picking a new vendor.

## §6 — `Messages.php` UI changes

**Build:**
1. Add a channel filter/tab (All / SMS / WhatsApp / Telegram / Instagram / Email) to the existing thread list.
2. The existing timeline-merge logic (union of `InboundMessage`/`OutboundMessage` sorted by timestamp) needs no structural change — it already works generically across rows with a `body`/direction/timestamp shape; it just needs the `channel` column available to render a small badge/icon per thread and per message.
3. The compose box, when replying inside a thread, must route the send call to the correct channel's client (`WhatsAppCloudClient::sendMessage()`, `TelegramBotClient::sendMessage()`, the existing SMS send path, etc.) based on the open thread's `channel` — a single dispatcher method (`MessageDispatcher::sendReply(MessageThread $thread, string $body)`) that switches on `$thread->channel` is cleaner than branching inside `Messages.php` itself.

---

## Acceptance checklist for this prompt

- [ ] `channel` column added and backfilled to `'sms'` on every existing row across all three tables
- [ ] `message_threads` uniqueness is `(user_id, channel, counterpart_number)` — verified with a pre-migration data audit, not assumed safe
- [ ] WhatsApp inbound messages become real `InboundMessage` rows (STOP-keyword handling preserved, not replaced)
- [ ] `WhatsAppCloudClient::sendMessage()` added for free-form replies within Meta's 24-hour window; `sendTemplate()` untouched
- [ ] Telegram and Instagram webhooks write to the same `InboundMessage`/`MessageThread` tables, not separate ones
- [ ] Inbound email is either built on a confirmed-supported existing mail vendor or explicitly flagged as needing a vendor decision — not silently skipped or silently given a new vendor
- [ ] `Messages.php` gains a channel filter and a single reply-dispatcher that routes by `thread->channel`, rather than per-channel branching scattered through the component
