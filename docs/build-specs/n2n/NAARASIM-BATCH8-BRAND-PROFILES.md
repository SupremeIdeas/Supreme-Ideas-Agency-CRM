# NaaraSim — Batch 8: Brand Profile Extension & Local Profiles
### Master repo only. Richer directory profiles (music vs non-music) and extended user profiles with privacy. For Claude Sonnet + Graphify.

**Depends on:** B3. **Unblocks:** B9. CTAs (Message/Call) are flag-gated stubs until B6/B10 ship.

## 0. Findings from the codebase
- `BrandPartner` (`owner_user_id, brand_name, short_description, category, hero_image_path, …`), `BrandPartnerImage` (`image_path, caption, sort_order`), `BrandPartnerVideo` (`video_url, platform, sort_order`), `BrandPartnerHandle` (**used by the Follow-Hunt: it has `credit_reward` and `verification`** — do not turn it into a plain "social links" list; add a display-only table or a flag), `BrandPartnerFollower` (user→brand only).
- Screens: `brand-manage.blade.php`, `brand-profile.blade.php`, `get-listed.blade.php`, admin `brand-directory.blade.php`. Read them first.
- Screenshots 47–51: contact/business profile pattern (category, hours, address). Hours/address are optional here; embedded maps are **not** proposed (cost and privacy).

## Phase A — Profile types and limits
1. `brand_partners.profile_type` = `general | music`. `music` requires **admin confirmation** (`music_verified_at`) because it unlocks higher limits (Master D10).
2. Limits live in admin `Setting`s, enforced **server-side** in a service/observer (not only in the form):
| | General | Music |
|---|---|---|
| YouTube videos | 5 | 20 |
| Vimeo videos | 5 | 20 |
| Gallery images | 5 | 10 |
| Music-platform links | – | up to 20 |
| Embedded players | – | 5 |
| Albums (smart links) | – | yes |
3. Verification: the 6th image (general) and 11th (music) are rejected by the API, not just the UI.

## Phase B — Music platforms
1. `config/music_platforms.php` (target ~20: Spotify, Apple Music, Amazon Music, YouTube Music, Audiomack, Boomplay, Deezer, Tidal, SoundCloud, Bandcamp, Napster, Pandora, Anghami, Qobuz, Mdundo, etc. — the developer confirms each domain). Each entry: label, icon, **allowed host(s)**.
2. `brand_music_links` (platform, url or handle). URL host must match the platform allow-list (blocks phishing/redirect links).
3. Verification: `spotify.com.evil.tld` and shortened URLs are rejected.

## Phase C — Video carousels and embeds (performance + safety)
1. Store provider + ID only. Carousel renders **thumbnails only**; iframes load on tap using privacy-enhanced embeds. Thumbnails fetched **server-side** (oEmbed/known thumbnail URL), cached, so pages make no third-party requests before consent. Carousel keyboard-accessible, lazy, virtualized for 20 items.
2. **HTML embed field**: accept pasted embed code, **parse** it to `provider + id` against an allow-list, discard everything else, render our own sandboxed iframe. Never store or output raw HTML. Update CSP `frame-src`. Provider-specific `sandbox` flags must be tested (some players break without `allow-same-origin`).
3. Verification: `<script>`, `onerror=`, `javascript:`, nested iframes and unknown providers are all rejected; page weight before/after on a 20-video profile is reported.

## Phase D — Albums and smart links
1. `brand_albums` (title, cover, slug, description) + `brand_album_links` (platform, url). Public page `/{brand}/a/{slug}` with OG/Twitter tags (reuse `admin/link-previews` conventions), streaming links list, one-tap share.
2. Verification: unfurl preview works; each link host-validated; album delete cascades.

## Phase E — Display social handles and CTAs
1. Non-music brands show social handles: use a **display-only** `brand_social_links` (or a `display_only` flag) so Hunt handles are untouched.
2. **Message** and **Call** CTAs appear only when `NaaraPro::active` (B7) and the target features are enabled; otherwise hidden.

## Phase F — Local (non-brand) user profile
1. Fields: linked phone, username, Naara ID, email, date of birth, bio, **two lightweight pictures**. New columns for visibility: `profile_visibility` (`public | connections | private`) and per-field reveal toggles.
2. **Private** shows only what the user chose from {Naara ID, email, phone, images}. Default DOB display: day/month only, year hidden.
3. Profile is reachable by tapping the avatar in chat and from directory search. Route by username/ID; respects block state and visibility; never leaks email/phone to non-permitted viewers.
4. Verification: full privacy matrix (public / connections / private × viewer relationship × each field).

## Constraints
- All uploads through the B7 pipeline rules (Wasabi, MIME, size).
- Admin moderation entry points for links/embeds (report → hide).
- Erasure removes profile media.

## Deliverable
Limit enforcement proof, XSS/host-validation test results, page-weight report, and the privacy matrix results.
