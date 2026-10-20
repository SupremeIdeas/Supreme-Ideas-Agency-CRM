# NaaraSim — Batch 7: Media Sending & Naara Pro Entitlement
### Master repo only. Images, voice notes and video in N2N, gated by an active brand subscription (Primitives P4 + P7). For Claude Sonnet + Graphify.

**Depends on:** B5 (meter), B6 (messages). **Unblocks:** B12 (media gallery).

## 0. Findings from the codebase
- Pro = "active brand-directory subscription". Models: `BrandPartner.owner_user_id`, `BrandSubscription` (`status`, `next_billing_at`, `grace_reminders_sent`), `BrandSubscriptionPlan`. **Phase 0 must read the real `status` values and how grace is handled** and define Pro precisely: brief says "for as long as their subscription is active", so default = grace period does **not** count.
- Storage rule: Wasabi S3 for all files (CLAUDE.md). Existing `resources/js/image-compress.js` and B1's attachment module handle client-side compression.
- The Naara Line SMS modal has an MMS size cap (~5 MB); N2N media has its own limits.

## Phase A — `NaaraPro` entitlement service
1. `NaaraPro::active(User): bool`, cached briefly, busted on subscription webhook/status change. Single source of truth for every Pro gate (media send, CTAs, extended gallery, Updates ring).
2. Verification: cancel/lapse/grace/renew all flip the gate at the right moment; cache cannot leave Pro on after lapse for more than the TTL.

## Phase B — Send matrix
| Content | Non-Pro sender | Pro sender | Recipient (anyone) |
|---|---|---|---|
| Text (no links) | yes (0.5 credit) | yes | receives |
| Image / voice note / video (uploaded) | **blocked** with an "Unlock with Naara Pro" prompt | yes, metered by size/duration | **can view and download** |
| Provider GIF/sticker search results | allowed (not an upload) — *sub-decision, default yes* | yes | receives |
| Location / contact card | allowed (small, no storage) | yes | receives |
Server enforces; UI merely hides. Captions obey the no-links rule.

## Phase C — Upload pipeline (never trust the client)
1. Presigned upload direct to Wasabi + a **finalize** endpoint (works around PHP upload limits on cPanel); finalize does a HEAD, reads the first bytes, checks **real MIME** (finfo/magic bytes) against an allow-list, enforces size/duration caps (admin settings), then meters.
2. Images: re-encode to strip EXIF (**GPS leaks**), generate thumbnail, store original and compressed separately as B1 requires. Video: no server transcoding assumed (FFmpeg is not available on shared hosting) → cap size/duration and rely on client thumbnails; report what was possible on the VPS.
3. Voice notes: server validates duration and format; keep transcript from B1, exposed to screen readers.
4. Optional malware scan (ClamAV) only if available — **UNVERIFIED**; otherwise state clearly that re-encoding is the only sanitization.
5. Randomised keys, private bucket, **no public URLs**.
6. Verification: a `.exe` renamed `.jpg` is rejected; oversize is rejected with a JSON error the composer shows; declared-vs-actual size mismatch is rejected; tests assert media never lands on the local disk when S3 is configured.

## Phase D — Metering by size
1. Cost preview **before** send: "This video will use 12.4 credits" from `N2NPricing::mediaCost` (B5), computed from the **server-measured** size at finalize.
2. Charge on finalize, idempotent by `client_id`; failure after charge refunds via the ledger, not by deleting rows.
3. Verification: preview equals final charge; retry does not double-charge; insufficient credit blocks the send but keeps the uploaded draft for a limited time.

## Phase E — Delivery and lifecycle
1. Recipients get **short-lived signed URLs** after an authorization check (participant only). Download = signed URL with `Content-Disposition`.
2. Delete-for-everyone removes the object and any thumbnails. Note: object stores can bill minimum retention after deletion — do not promise cost savings from deletes (**UNVERIFIED** for your provider terms).
3. Orphan cleanup job for uploads never finalized; retention policy setting.
4. Pro lapse: already-sent media stays; new uploads blocked with the unlock prompt.

## Constraints
- All checks server-side; presigned URLs expire in minutes and are scoped to one key and one size.
- Media metadata is not logged with user identifiers in URLs.
- Erasure removes objects.

## Deliverable
Pro gate matrix results, upload-attack test results, the pricing preview evidence, and what was and wasn't possible for video on the target hosting.
