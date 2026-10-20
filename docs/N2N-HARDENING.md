# Naara chat (N2N): hardening, moderation and compliance (MASTER-ONLY)

Never ported to `NaaraSim-WhiteLabel` or `NaaraSim-White-Label-Extented` (CLAUDE.md). Guards: `MasterOnlyModules`, each fork's `WhiteLabelBoundaryFitnessTest`.

## Moderation
- **Reports** (`n2n_reports`) capture the reporter's reason, an optional note and an excerpt of the reported person's last 5 messages at report time. Staff never see the whole chat.
- **Admin > N2N > Chat reports** (`/adminmaster/n2n/reports`, roles `admin` and `super_admin`): Open, Closed, Restricted tabs. Actions: Dismiss, Warn, Restrict 24 hours / 7 days / until lifted, plus a private staff note. Every action is audited (`n2n.report_resolved`, `n2n.restriction_lifted`).
- **Restriction** (`users.n2n_restricted_until`) stops *sending* (messages, forwards, scheduled sends) and *placing calls*. The member can still read, react, receive, export and erase their data. Enforced in `MessageService::send` and `CallService::start`.
- **Nothing is automatic.** Reports never restrict anyone by themselves (mass-reporting must not be a weapon). A person reported by 3 or more different members in a day is only flagged "Needs attention" for a human.

## Who may moderate (decided 2026-10-04)
Staff who are not admins may moderate **if** a super admin grants them the `n2n.moderate` scope. Why delegate at all: abuse reports are time-sensitive, a two-person admin team cannot cover nights and weekends, and a report left unread for a day is a safety failure and a legal one. Why bound it: moderation means reading excerpts of private messages, so it follows least privilege.
- Staff with the scope: see the report excerpt only, Dismiss, Warn, Restrict for 24 hours or 7 days.
- Admin and super admin only: permanent restriction, lifting a restriction, anything involving another staff member.
- A super admin can remove the scope from any staff member at any time (Admin > Staff). Every action is written to the audit log with the actor.

## Disappearing messages and chat lock
Disappearing is a view filter plus a purge job (`n2n:expire-messages`, five-minute cadence). It limits what remains in the app, not what a recipient can screenshot, copy or forward. Chat lock hides chats behind a PIN on top of the account login; it is not encryption and Naara staff access is unchanged.

## Live location, scheduled media, events
- **Live location** is time-boxed (15 min, 1 h or 8 h; an admin cap can shorten it), keeps only the latest point, is readable by the other member only (404 for anyone else), is wiped on stop, expiry (purge job), block or erasure, and never appears in message bodies, previews, search, export, reports or moderation excerpts. It can only report while Naara is open in the browser.
- **Scheduled media** holds staged uploads until the send time and deletes them on cancel, failure or erasure; Pro is checked at queue time and again at delivery; nothing is charged until delivery.
- **Planner events** are chat messages (so cost, block, disappearing and moderation apply). The reminder is claimed with a compare-and-set before it is sent, so it is exactly-once per person. `n2n:event-reminders` is registered with SchedulerHealth. Event title and notes are link-filtered; the reminder push is generic unless the admin allows previews.
- **Planner follow-ups** are a private per-member list (`n2n_followups`): never shown to or announced to the person they mention, attachable only to a chat the member is in, capped at 100 open, reminded exactly once (compare-and-set), erased with the member and listed in their export.

## Privacy controls that are view filters, not deletions
`Clear chat`, `Delete chat` and `Delete for me` hide history from one member's view (`participants.cleared_before_id`, `removed_at`, `n2n_message_hides`). The other person's copy and the stored rows are untouched, so moderation, legal hold and erasure keep working. Every per-member read goes through `Message::scopeForViewer`.

## Erasure, export, legal hold
`HardeningTest::DATA_POLICY` lists every `n2n_*` table with its policy (erased, retained with a named reason, system, brand-owned); the test fails when a new table has no entry. Erasure (`N2NErasure`) additionally: cancels and blanks queued scheduled messages, deletes reactions, hides, stars, follows, Update views/reactions, invites sent, presence, resets personal chat prefs and the reaction bar, deletes earlier versions of edited messages and uploaded media objects. Export (`UserDataExporter`) includes the person's own messages (never anyone else's), reactions given, queued messages and reaction bar. Legal-hold lists the usage-credit ledger and gifts.

## Fitness guards (run in the normal suite)
- Every `n2n_*` table has an erasure policy. Every scheduled `n2n:*` command is registered with `SchedulerHealth` (silent stops show on the health page). Every write route under `/n2n` is behind `auth` and a throttle.
- Existing: closed-set reactions, ChatFormat escape-first rendering, link filter, MediaSniffer + ImageSanitizer, idempotent sends, usage-credit metering, IDOR-safe numeric conversation routes (missing and not-yours look identical).

## Degraded-mode matrix (cPanel / no realtime / no SFU)
| Capability | VPS | cPanel shared |
|---|---|---|
| Messaging, reactions, forward, edit, clear/delete, voice notes | yes | yes |
| Live delivery | Reverb push | polling fallback (seconds of delay) |
| Scheduled send | scheduler every minute | needs the cPanel cron for `schedule:run` |
| Voice-note duration probe | ffprobe | declared duration used |
| 1:1 voice calls | WebRTC + TURN | needs TURN credentials |
| Group calls | self-hosted LiveKit | off |

## Owner decisions still open
Legal review of the "encrypted in transit and at rest, not end-to-end" wording and of forwarding received text.
## Not verified
Real Wasabi/Reverb/Cloudflare, SFU at scale on the real internet, native ringing on phones, iOS Safari MediaRecorder, real-device microphones.
