# NaaraSim — Batch 12: Conversation Power Features
### Master repo only. Features seen in the Google Messages/WhatsApp screenshots, in priority tiers. For Claude Sonnet + Graphify.

**Depends on:** B6, B7. Build Tier 1 first; ask before Tier 2.

## Tier 1 — build
1. **Mute / Archive / Pin chat** (columns already reserved on `n2n_participants`). Archived chats stay out of the inbox until a new message; muted = no tone, no push, badge only.
2. **Reply/quote**, **star message** (`n2n_stars`), **pinned message** in a conversation (screenshot 43), **search in conversation**.
3. **Chat theme** (screenshots 7–12): per-user only ("only you will see these changes"): accent colours + wallpaper presets + optional own photo. Ship **original** wallpapers (generated gradients/SVG art) — do not reuse the reference images. Custom photo = one per chat, private Wasabi object. Theme is a scoped set of CSS variables and must pass an automatic **contrast check** for bubbles/text in light and dark.
4. **Media & docs gallery** per chat (screenshot 48), from B7 objects. The "Links" tab shows only Naara links (no general links exist).
5. **Storage manager** (screenshot 48–49): size of media in this chat, delete selected items for me.
6. **Export chat** (text only, no media) with a privacy notice.
7. **Report / Block** polish (uses B6 structures).

## Tier 2 — only if the owner confirms
- **Disappearing messages** (24 h / 7 d / 90 d, conversation-level). A deletion job removes bodies and media; **legal-hold records must override deletion** (`admin/legal-hold-records`). Explain object-store minimum-retention billing (UNVERIFIED).
- **Chat lock**: web has no reliable device-lock primitive; a WebAuthn-based lock is possible but **UNVERIFIED across your target browsers**. Recommend deferring to the native shell.

## Deferred (not in your brief)
Channels/broadcast, communities, "AI images" attachment.

## Constraints
- Every preference is per-user and per-conversation; nothing here leaks to the other participant.
- Verification: two users with different themes each see only their own; archived+muted chat stays silent; export contains no other user's private profile fields.

## Deliverable
Tier 1 verification results, contrast-check evidence, and the Tier 2 decision list.
