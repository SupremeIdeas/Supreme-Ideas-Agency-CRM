> MASTER-ONLY DOCUMENT. N2N is permanently master-only (CLAUDE.md, docs/architecture/WHITE-LABEL-LICENSE-BOUNDARY.md section 6). Never copy this file into a white-label fork.

# NaaraSim — Batch 5 Addendum: N2N Interface, Grounded in Reference Screenshots
### Master repo. Read alongside the original N2N batch plan (calling/messaging, depends on Batch 3 real-time infra and Batch 4 NaaraCredit economy). This addendum specifies the actual interface patterns to build, taken directly from a 64-screenshot reference walkthrough of a native Android messaging app.

---

## 0. The one most important finding — funding-source choice at conversation start

One of the reference screenshots is annotated directly by the person who
supplied it, calling out exactly this: **starting a new N2N chat must let
the sender choose whether the conversation runs on NaaraCredit or on real
Wallet balance.** This is the precise mechanical expression of the
Naara-vs-NaaraLine distinction already established elsewhere in this
engagement — chatting/calling a verified Naara ID uses NaaraCredit;
reaching a NaaraLine number (an international/unregistered number) draws
from real Wallet balance. Build this as an explicit choice surfaced at
the moment a new conversation is started, not an invisible rule the
system applies silently — the person starting the chat should see and
confirm which balance will be charged before the first message sends.

---

## Phase A — Composer: voice, media, and attachment behavior

Confirmed from the reference flow, in this exact sequence:
1. **Voice recording**: tap-to-record, with a live waveform, a
   **noise-cancellation toggle visible during recording** (on/off), and
   three post-record actions — Restart, Stop, Attach. After stopping, a
   **playback preview with scrubber** appears before the user commits to
   sending, not an immediate auto-send.
2. **Attachment/media picker**: a tabbed panel — Emoji, GIFs, Stickers,
   and a fourth "Photomoji"-style tab (a quick personal-photo-reaction
   format) — each with its own search. Build these as genuinely separate
   tabs with independent search, not one flat picker with mixed content.
3. Reuse Batch 1's Chat Composer Pro component for all of this rather
   than building a second composer implementation specific to N2N — this
   is exactly the kind of shared surface that component was built to be
   generic enough to serve.

## Phase B — Per-conversation customization ("Chat theme")

Confirmed as a dedicated settings screen reachable from the conversation's
overflow menu, explicitly labeled **"only you will see these changes"**
(i.e., this is a local, per-viewer preference, not something the other
party sees or that syncs as part of the conversation's actual content):
1. A row of bubble-color presets (each preset shown as a two-tone swatch
   — sent-bubble color + received-bubble color together) — apply instantly
   to a live preview above.
2. A curated wallpaper gallery, organized by named categories (the
   reference used: Animals, Architecture, Black & White, Cityscapes,
   Landscapes, Macro, Space, Sunsets, Textures) plus a "choose your own
   photo" option.
3. A "Reset to default" action.
4. This is a genuinely nice, low-cost premium-feel feature — a small
   curated asset library (admin-uploadable, same discipline as the
   notification tone library in Batch 2) plus a per-user preference
   column scoped to `conversation_id + user_id`, since the reference
   explicitly scopes this per-viewer, not per-conversation-globally.

## Phase C — Conversation list and global search

1. **Pinned conversations** with a visible unread-count badge, sitting
   above the regular chronological list.
2. **A persistent "start new chat" action** (floating action button,
   matching this engagement's existing design language rather than
   copying the reference's exact visual treatment).
3. **Global search with smart filters**, confirmed as: Unread, Known
   (registered Naara contacts), Unknown, Starred, Images, Videos, Places,
   Links — build these as real, independently-toggleable filter chips
   over the message search, not a single combined dropdown. "Known" vs
   "Unknown" maps directly onto this engagement's existing registered-vs-
   unregistered Naara ID distinction — reuse that exact check, don't
   build a second definition of "known."
4. **Per-message star/favorite**, independently searchable via the
   Starred filter.

## Phase D — Conversation-level actions (overflow menu)

Confirmed menu, to adapt (not copy verbatim — several of these don't
apply to N2N, e.g. there's no SIM/carrier concept in an internet-based
Naara-to-Naara conversation):
- **Add people** (group conversation support — confirm this is in scope
  for this batch or deferred; the earlier group-calling spec from this
  engagement's own notes implies group messaging is expected eventually,
  so at minimum design the data model to not block it later).
- **Details**, **Starred**, **Search** (within this one conversation).
- **Chat theme** (Phase B).
- **Archive**, **Trash** — both reversible, soft states, not deletion.
- **Block & report spam** — essential for a system where any registered
  user can message any other by ID/username/phone; build real abuse
  reporting from day one, not as a later addition, given this directly
  touches user safety.
- **Help & feedback**.

## Phase E — Emergency/distinguished message types

The reference includes a distinct visual treatment for an "I sent you
emergency info" system-style message (a red-flagged icon, visually
separated from normal messages). This maps onto a genuinely good idea for
N2N: a small set of **distinguished, non-spoofable system message types**
(e.g., "shared their location," a NaaraCredit gift notification, a missed
N2N call) each with their own fixed icon/treatment, built once as a
reusable "system message" bubble type rather than ad-hoc styling per
event type as they're added.

---

## Constraints

- Phase A reuses Batch 1's composer — no second composer implementation.
- Phase B's per-conversation theme is explicitly local/per-viewer, not
  synced conversation content — store it that way.
- Phase D's "Block & report spam" is not optional or deferrable — ship it
  in the same release as the first version of N2N messaging, not after.
- None of this phase ships before Batch 3 (real-time infra) and Batch 4
  (NaaraCredit spending rules) are in place, per the original batch plan.

## Deliverable

Confirmation each phase's interface matches the described behavior
(screenshotted against the actual reference images on request), and
explicit confirmation of how Phase 0's funding-source choice is surfaced
and enforced at the moment a new N2N conversation starts.
