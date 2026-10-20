# NaaraSim — Batch 6: N2N Messaging Core
### Master repo only. The chat interface itself. Naara-branded, WhatsApp/Google-Messages *patterns*, no third-party assets. For Claude Sonnet + Graphify.

**Depends on:** B1 (composer), B3 (resolver), B4 (realtime), B5 (meter). **Unblocks:** B7, B9, B10, B12.

## 0. Findings from the codebase
- `MessageThread`/`InboundMessage`/`OutboundMessage` are **Naara Line SMS** (tied to `virtual_number_id`). They stay as they are. N2N is a **separate** user-to-user model.
- Support chat = `SupportConversation`/`SupportMessage` (unrelated).
- Composer contract (B1, decision D2 = dedicated HTTP endpoint): multipart `meta` + files, `Idempotency-Key = clientId`, session-cookie auth.
- Reuse for abuse: `SpamReport`, `BlockedNumber`, `SpamBlockedCaller`.
- Reference screenshots: 13–16 (list + New chat), **17 and 25** (the "With:" picker — your annotation: Naara credit or Wallet balance), 18–24 (search filters), 43–46 (chat + menu).

## Phase A — Data model
1. `n2n_conversations` (type `direct` now, group later; `status` `request|active|blocked`; `requested_by`), `n2n_participants` (conversation, user, last_read_message_id; nullable columns reserved for B12: `muted_until, archived_at, pinned_at, theme_json`), `n2n_messages` (conversation, sender, **`client_id` unique per sender**, `type`, `body`, `reply_to_id`, `deliver_at`, `cost_credits`, `ledger_ref`, `deleted_for_all_at`), `n2n_message_receipts` (delivered/read).
2. **Decision to make explicit in the PR:** app-layer encryption of `body` (Laravel `encrypted` cast) blocks server-side search and reporting excerpts; disk-level encryption does not. Recommend disk/host-level unless the owner wants app-layer. Either way, the UI says "encrypted in transit and at rest", never "end-to-end".
3. Verification: unique `(sender_id, client_id)` returns the existing message on repeat.

## Phase B — New-chat flow and the "With:" picker
1. **To:** field accepts name, username, Naara ID, phone or email (resolver, live validation).
2. **With:** picker with two lines, exactly like the SIM picker in the screenshots:
   - **NaaraCredit — Naara user** → requires a registered recipient (N2N).
   - **Wallet balance — Naara Line** → requires the sender to own an active Naara Line; routes into the existing Naara Line SMS pipeline (`SendMessage` / `MessageSenderService`). Do not merge the two thread models.
3. Unregistered recipient on the NaaraCredit line → offer **Invite** (share-sheet/`navigator.share` and email first; SMS invites cost money) with an optional attached credit gift (B5 pending gift).
4. Conversation list shows both kinds with a **line badge** (like the SIM badges) and distinct tone categories (B2 Phase C item 3).
5. Verification: the same person reachable by username, phone and Naara ID lands in the same conversation; choosing Wallet without a Line shows the "Get a Line" prompt.

## Phase C — Send pipeline
Order inside one DB transaction with idempotency by `client_id`:
`authorize (participant/state/block) → resolver valid → link filter → Pro/media rule (B7) → charge 0.5 credit (B5) → insert → commit → Realtime::publish → web push if offline → tone event`.
1. Offline-queued messages are charged only when the server receives them; the composer shows the cost estimate.
2. Insufficient credit: message stays a draft with **Buy / Ask a friend to share** actions.
3. **Link filter** (best-effort, say so): URLs, bare domains, markdown links, obfuscations (`dot`, spaced/zero-width characters, IDN look-alikes). Allow-list Naara-issued links (call links, profile links).
4. Rate limits: messages/min, new conversations/day, body length.
5. Verification: duplicate send charges once; blocked link returns a clear `422`; non-participant gets `403`; realtime event fires once; poll driver also receives it.

## Phase D — Message requests and privacy
1. First message to a non-connected user creates a `request` conversation the recipient can accept, decline or block; sender still pays per message but is capped until accepted.
2. Settings: who can message me (everyone / connections + brands / nobody), read receipts on/off, typing indicator, last seen (B4).
3. Verification: request → accept → normal chat; blocked sender sees "delivered" but recipient gets nothing (no oracle).

## Phase E — Inbox, search, actions
1. Conversation list with unread counts, line badges, unified with Naara Line threads via one `InboxEntry` DTO.
2. Search with filters shown in the screenshots: Unread, **Known** (linked contacts), Unknown, Naara/Line, Images/Videos (B7), Places, Links (= Naara links only — the general "Links" filter contradicts "no links").
3. Chat header/menu: View contact/profile, Search, Report, Block, Mute (B12 completes), call buttons (B10). Report/Block use `SpamReport` and a new `n2n_blocks`.

## Phase F — Tones and notifications
Wire B2 events: `message.n2n`, `message.line_unknown`, `credit_alert` on insufficient/received credit. Web push for offline recipients uses the existing `WebPushSender`; body content policy: configurable (hide preview by default).

## Constraints
- Escape all message output (XSS); render text as text.
- Authorization on **every** message/conversation/media id (IDOR tests).
- Old Naara Line surfaces keep working during rollout; feature-flag the N2N inbox.
- Naara tokens (Teal `#0A6E6E`, Gold `#D4A017`, Navy `#0D1B2A`), light/dark, RTL, reduced motion.

## Deliverable
Verification results per phase, the encryption-at-rest decision, and a screenshot-by-screenshot checklist (13–25, 43–46) of what was matched.
