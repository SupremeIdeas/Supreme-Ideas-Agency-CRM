# NaaraSim — Batch 9: Connections, Follow-Back & Updates
### Master repo only. Who counts as a "Naara connect mate", and the Status-style Updates carousel built from brand galleries. For Claude Sonnet + Graphify.

**Depends on:** B3, B6, B8.

## 0. Findings and definitions
- `BrandPartnerFollower` is **user → brand** only. Your rule needs **brand → user** follow-back and mutual connection.
- A brand acts through its owner account (`BrandPartner.owner_user_id`). Default definition (confirm with owner): **Connection** = mutual user follow, *or* user follows brand **and** brand owner follows that user back. Following a brand alone never puts it in the user's contact/connect list.
- Screenshots: **52** (Updates: Status carousel + Channels), **60** (viewer with reply/reactions). Channels are **not** in your brief → deferred.
- Updates are **not** separate uploads: the ring = the brand's gallery (D12). No 24-hour expiry.

## Phase A — Follow graph
1. `user_follows` (follower, followed, created_at; unique pair) for user↔user and brand-owner follow-back; keep `BrandPartnerFollower` for brand pages. `connections` view/service resolves mutuality in one place.
2. Notifications: "X followed you" and "X wants to connect" with a **Follow back** action (uses the push/message tone slot from B2).
3. Verification: follow-only ≠ connected; follow-back = connected; unfollow immediately removes visibility.

## Phase B — Connect mates list and invites
1. List sources in the N2N interface: linked registered contacts (B3), connections, previous chatters, plus **add by username/ID/phone/email**.
2. If the input doesn't resolve → **Invite** flow (Master P1): share sheet / email first; optional attached credit becomes a B5 pending gift with the sender's referral link.
3. Directory → chat: "Message" on a brand card opens a message request (B6) for Pro brands.
4. Verification: unresolved email shows Invite, resolved shows Chat; invite with credit creates a pending gift.

## Phase C — Updates tab
1. Rings for **connected** users who own an **active-Pro** brand with gallery images. Ring shows brand avatar + first gallery image; tapping opens a full-screen viewer stepping through gallery items (and YouTube link previews using B8's thumbnail cache). Unseen first, then most recent.
2. `update_views` (viewer, brand, last_seen_item/updated_at) for seen state. Images persist until replaced.
3. **Reply** to an update sends an N2N message with quoted context (a normal 0.5-credit message); **reactions** send an emoji reaction event. Reply opens a message request if not connected.
4. Brand privacy control: who can see my Updates (connections only by default).
5. Verification: a non-Pro or non-connected brand never appears; Pro lapse removes the ring; replies land in the right conversation with the right quote.

## Constraints
- Visibility computed server-side; the client never receives hidden brands' media URLs.
- Tone/notification for follows respects mute and block.
- Viewer uses lazy loading and B7 signed URLs.

## Deliverable
Graph-rule test matrix, invite/gift verification, and Updates visibility proof across all four relationship states.
