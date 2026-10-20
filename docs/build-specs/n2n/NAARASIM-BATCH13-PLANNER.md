# NaaraSim — Batch 13: Naara Planner (scheduled calls, RSVP, reminders)
### Master repo only. **Suggested by the screenshots, not by your brief — confirm before building.** For Claude Sonnet + Graphify.

**Depends on:** B10, B11. Screenshots: 26–42 (Google Calendar/Tasks), 56–59 and 61–64 (WhatsApp Schedule call, event card, RSVP).
Your own Tasks screenshots contain a task "Call John To get Updated for Business schedule" — that pattern (a follow-up tied to a person) is the useful part of Tasks for Naara.

## Phase A — Scheduled calls
1. `n2n_events` (creator, title, description, `starts_at` UTC, `ends_at`, `timezone`, `type`, `call_link_id`, `require_approval`, `reminder_minutes`), `n2n_event_rsvps` (going | maybe | declined | no_response).
2. Creating an event generates a call link (B11). Validation copied from the screenshot: cannot create a link for an event in the past. Join button activates a set time before start.
3. **Event card** message type in chat with **Edit event** and **Add to calendar**; RSVP shows Going / Not responded (screenshot 59).
4. Verification: participants in Nigeria and the UK see correct local times across a DST change; RSVP concurrent updates are consistent.

## Phase B — Reminders and calendar export
1. Reminder job (scheduler, exactly once per recipient per event) → push + in-app + tone (B2). Register with `JobHeartbeat`.
2. **Add to calendar** = generated `.ics` file (free, no API) and a Google Calendar template URL. Validate the `.ics` opens in Google, Apple and Outlook.
3. Agenda list view for upcoming events (month grid optional later).

## Phase C — Follow-up reminders (optional)
"Remind me to call…" on a contact or a finished call. A tiny follow-up list with due date/time and star — **not** a general Tasks app (generic task lists are deferred).

## Constraints
- All times stored in UTC with the event's IANA timezone; repeat rules limited (daily/weekly/monthly) until proven needed.
- A scheduled group call bills speakers exactly as in B11 (no pre-charge).
- Event content is visible only to invitees; erasure removes events the user created.

## Deliverable
Timezone/DST test results, reminder exactly-once proof, and `.ics` compatibility results.
