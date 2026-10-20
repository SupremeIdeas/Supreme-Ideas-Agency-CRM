# NAARA PROMPT 19 — Native Mobile Softphone
**For: a native mobile engineer (iOS/Android), NOT Claude Code working against the Laravel web codebase**
**Depends on:** Prompt 14 (there's no business calling experience worth a native app until ring groups/queues exist to actually call into).

**Confirmed before writing this prompt:** `app/Support/AppStudio.php` is a webview-wrapper app builder (Median/FlangApp-style) — it packages the existing web app inside a native shell with branding/icon/splash/config, and it is the entirety of Naara's current mobile-app story. This is genuinely fine for the eSIM/OTP/travel consumer experience, which has no "must ring while the phone is locked" requirement. It cannot deliver that requirement for business calling — a webview has no access to CallKit (iOS) or ConnectionService (Android), and cannot wake the app via VoIP push while backgrounded. **This prompt is flagged separately from Prompts 13–18 specifically because it is not a Laravel/Livewire build — it's native application engineering, and should be scoped, staffed, and timelined as such, not folded into the same estimate as the rest of the Business Suite.**

---

## §1 — Decide scope before writing any native code

**Build (a decision, not code):** confirm which call flows genuinely need true native calling versus which can stay on the existing webview app. The likely answer: Organization-owned business numbers with Prompt 14 ring groups/queues need it (a sales rep must not miss an inbound lead call because their phone was locked); the consumer browser-dialer experience for personal Naara Line calls arguably doesn't need the same guarantee today. Write this decision down explicitly — it determines whether this is a small hybrid addition or a much larger full-native rebuild.

## §2 — Hybrid architecture (recommended default, pending §1's decision)

**Build:**
1. Keep `AppStudio`'s webview output for everything that isn't a live call screen — account, wallet, eSIM management, messaging, all of it stays exactly as it is today, unlocking zero additional native engineering for the majority of the app's surface.
2. Add a thin native module (Swift/Kotlin, or React Native/Flutter if the team's native-engineering capacity favors a cross-platform approach over two fully separate native codebases — this is a real staffing/skills decision, not a purely technical one) specifically for the calling screens, embedding Twilio's Voice SDK directly rather than routing calls through the webview.
3. The native shell can still reuse `AppStudio`'s existing branding/icon/config pipeline for everything cosmetic — this isn't a full app rebuild, it's a hybrid where one specific surface (the phone/calling screen) is genuinely native and everything else stays as it is.

## §3 — Push-based incoming call handling

**Build:**
1. iOS: PushKit VoIP push notifications wake the app (even from a fully backgrounded/terminated state) and hand off to CallKit for the native lock-screen call UI — this is the standard, Apple-mandated pattern for any VoIP app that needs reliable incoming calls; Twilio's Voice SDK has documented support for wiring this up.
2. Android: Firebase Cloud Messaging with a high-priority data message triggers `ConnectionService` for the equivalent native call UI and background wake behavior.
3. Both require server-side push-credential configuration (Apple Push Notification service certificate/key for VoIP pushes, Firebase server key) — new infrastructure, genuinely not present anywhere in the current codebase, and needs to be provisioned and tested on real devices, not just simulators, before this is considered done (VoIP push behavior in particular is notoriously inconsistent in simulators/emulators).

## §4 — Verification before marketing this as "native calling"

**Build:** explicit test coverage for: a call ringing while the app is fully backgrounded, a call ringing while the phone is locked, a call ringing while the phone is in Do Not Disturb (should still ring through, the way a normal cellular call does, if that's the product intent), and behavior after a phone restart (VoIP push tokens can go stale). Do not ship this as a headline feature until these specific scenarios are verified on physical devices, since this is exactly the class of bug that's invisible in a demo and devastating in production for a business phone system.

---

## Acceptance checklist for this prompt

- [ ] Explicit written decision on which flows need true native calling vs. stay on the existing webview, made before any native code is written
- [ ] Twilio Voice SDK embedded natively for the calling screens specifically — not routed through the webview
- [ ] VoIP push (iOS PushKit + CallKit) and Android equivalent (FCM + ConnectionService) both implemented and tested on physical devices
- [ ] Locked-screen, backgrounded, and Do-Not-Disturb call scenarios explicitly verified before this is marketed as "native calling"
- [ ] Everything outside the calling screens remains on the existing `AppStudio` webview pipeline — this is scoped as a hybrid addition, not a full app rebuild
