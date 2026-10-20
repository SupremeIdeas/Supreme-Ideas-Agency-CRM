# Video calling for Naara (MASTER ONLY): provider research and placement

Status: **1:1 video calling and on-device beauty looks are BUILT** (2026-10, master only, see "Shipped" below). Group video and managed providers are still plan only.

## Shipped: 1:1 video with beauty looks
- A video button next to the call button (chat header, profile, calls list). The callee sees "Incoming video call" with **Video** or **Audio only**; either side can turn the camera off and on, flip the camera, and change their beauty look at any time. The picture is full-screen with a draggable self-view; controls fade out on an active call and return on any tap.
- **Screen layout (owner reference, 2026-10-12):** voice and video share one header (minimise top-left, name and timer centred, quality bars and a lock replace the buttons while the More menu is open). Video: pill bar with More, Camera, Speaker, Mic, End; your picture is a small tile with Flip and a magic-wand (beauty) button, tap it to swap with the other person's picture; More = six reactions that float up on both screens, Share screen (desktop browsers only, where `getDisplayMedia` exists) and Send message; Minimise turns the call into a small live window you tap to restore. Voice: big round avatar on a subtle doodle background and a rounded card with Speaker, Mute, More, End. Not built: **Add person** (group calls are voice-only on LiveKit; a real button needs group video first) and switching a voice call to video mid-call (the billing rate is set when the call is placed).
- It rides the existing 1:1 WebRTC call (same signalling, TURN, billing rules). The video line is always open (a tiny black track when the camera is off), so turning the camera on is a track swap, never a renegotiation. A `media` signal tells the other side when the camera goes off so they see a "camera is off" placeholder.
- Billing: video is the voice rate times an admin multiplier (Admin > N2N > Calls, default 2x, never below voice). Admin can switch video off without touching voice calls (`n2n.calls_video_enabled`).
- **Beauty looks** (`resources/js/n2n/beauty.js`): runs on the person's own device in a WebGL shader (2D-canvas fallback), no vendor SDK, no upload. Skin-only edge-preserving smoothing with fine detail added back (so it never looks plastic), a soft exposure lift, glow and warmth. Looks: No filter, Natural, Soft focus, Glow, Radiant, Warm, Studio, plus an intensity slider. **Off by default**; the panel is hidden until the person taps Beauty; the choice is remembered on that device only. A slow device automatically gets a cheaper blur. It does not reshape faces or use face landmarks.
- Verified with two real browsers and a real WebRTC link (`tests/visual/n2n/video-call-smoke.mjs`: video bytes flow both ways, switching a look keeps video flowing, camera-off placeholder, remembered look) and with a real portrait as the camera feed (the received picture shows smoothed skin with eyes, hair and background intact).
- Found and fixed on the way: the site-wide `Permissions-Policy` header had `camera=()`, which blocked every camera use. It is now `camera=(self)` (own origin only).
- Not verified (needs production): behaviour on real phones (battery, heat, iOS Safari tab suspension), real carrier NAT, and relay bandwidth cost for video.

## Beauty-filter providers (researched 2026-10; confirm pricing and terms on each vendor page before committing)
| Option | What it adds over our on-device looks | Notes |
|---|---|---|
| **Banuba Face AR SDK** (web, iOS, Android; Agora/Twilio samples) | Face-landmark based touch-up, teeth and sclera whitening, eye and hair colour, live AR makeup, backgrounds | Custom quotes only (flat annual licence or monthly-active-user model), 14-day free trial. One documented limit: a captured stream can freeze in an inactive Safari tab. |
| **DeepAR** (Agora partner) | Face filters, advanced beauty and skin smoothing, background removal, try-on | Listed on Agora's partner page. Pricing not confirmed. |
| **Agora Extensions Marketplace** (Banuba, DeepAR) | The same engines as a plug-in on Agora's RTC | Only useful if we also move calls onto Agora. |
| MediaPipe Face Landmarker (Google, Apache-2.0) | Free landmarks for a future in-house upgrade (eye/lip effects, face-aware smoothing) | Needs model hosting and tuning; not required for the current looks. |
| Snap Camera Kit | Not verified in this research | Check the Snap developer terms before considering it. |

Recommendation: keep the free on-device looks as the default for everyone. Add a vendor SDK only if the owner wants AR makeup or face-shaping, behind the same `create()` surface in `beauty.js` (it returns a processed `MediaStream`). Banuba is the most direct fit for web video calls; ask for a quote with our monthly-active-user estimate.

## What already exists
- 1:1 voice calls: browser-to-browser WebRTC, signalled by Reverb, relayed by our own coturn (`config/n2n.php` `calls.turn`). Media never touches the web server or its disk.
- Group voice calls: a self-hosted LiveKit SFU (`sfu.*`, `SfuClient`), VPS only. The server signs a short-lived join token per person; the API secret never reaches a browser.

## Provider options (list prices from 2026 comparison articles; confirm on each pricing page before committing)
| Option | Fit | Rough price | Notes |
|---|---|---|---|
| Our own WebRTC + coturn (1:1) | Best first step | Our TURN bandwidth only | Adds a video track to the call we already run. No new vendor. |
| LiveKit (self-hosted on VPS) | Group video, VPS installs | Server and bandwidth | Already integrated for group voice. |
| LiveKit Cloud | Group video on cPanel installs (cPanel cannot host an SFU) | about $0.004 per audio-minute, $0.006 to $0.024 per video-minute | Same SDK and token flow as our self-hosted code, so one integration covers both. |
| Vonage Video API | Managed fallback | about $0.004 per participant-minute | Real bills are often 50 to 100 percent higher once recording, broadcast and regulatory fees land. |
| Twilio Video | Second managed fallback | about $0.004 per participant-minute | Not being sunset: Twilio reversed the end-of-life in October 2024. We already hold a Twilio account for numbers and voice. |
| Daily | Alternative | $0.004 per participant-minute after 10,000 free minutes per month | Good prebuilt UI. |
| Agora | Cheapest at scale | HD video $3.99 per 1,000 minutes | Different SDK and billing model. |

Recommendation: 1:1 video on our own WebRTC and TURN first; group video on LiveKit (self-hosted on VPS, LiveKit Cloud on cPanel); Vonage then Twilio as managed fallbacks behind one provider interface. Vonage is a valid primary if you prefer a single managed vendor, but it adds a second integration to maintain.

## Where it belongs (so it is never stale or dead)
1. Naara Chat 1:1 header: a video button beside the voice button. Same call screen, camera toggle, flip camera, audio-only fallback.
2. Incoming-call screen: Answer with video or Answer with audio.
3. Group calls: camera on/off per person.
4. Brand profile and the business card: "Video call" for a brand that has turned it on (brand-owned availability, never a surprise ring).
5. Support: an agent can upgrade a NaaraCare chat to a video call.
Not planned: standalone meeting rooms, webinars, or recording by default.

## Safety rules
- Billing follows the existing call rules: funding-source choice, hold before connect, settle on end, refund on failure. Video is priced per minute from admin settings, never a literal in code.
- Capability check before the button shows: camera permission, TURN configured, provider reachable. Otherwise the button is hidden, not dead.
- Dual server: 1:1 works on cPanel and VPS (WebRTC plus a TURN host). Group needs an SFU: self-hosted needs a VPS; cPanel installs use the managed provider.
- Disk: media flows peer-to-peer or through the SFU, never through our disk. No recording by default. If recording is ever added it must be SFU egress straight to the configured bucket (R2, Wasabi), never the local disk, with a retention limit.

## Chat files and storage (verified)
Chat photos, videos, voice notes and files (Naara Chat and NaaraCare) are written through `MediaStorage::privateDisk()`: R2 if configured, else Wasabi, else the server's private local disk. Nothing is hard-coded to local disk. `ChatStorageDiskTest` locks this order, and `N2N\MediaTest` proves chat media never lands on the public disk. The local disk only holds platform images that ship with the install, plus chat files on installs that have configured no cloud store.
