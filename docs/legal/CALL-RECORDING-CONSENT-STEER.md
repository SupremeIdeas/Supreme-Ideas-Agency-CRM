# Call recording and intelligence (Business Suite Prompt 15): consent steer

Prepared 2026-10-04 before Prompt 15 starts. **Nothing is built yet.** This is a recommendation for the owner to accept, change or reject. Not legal advice; the per-country matrix below needs counsel review before launch **[COUNSEL]**.

## Why this is the risky part of the suite
Recording is lawful in some places with one party's consent, in others only with everyone's, and a few ban it outright without a lawful purpose. Naara's users call across borders, so the law of *each end* can apply. Transcription and "intelligence" (summaries, sentiment, keyword spotting) is separate processing of voice data, which GDPR and the NDPA treat with extra care. A single mistake here becomes a criminal-offence and regulator problem, not a bug.

## Recommended policy: strictest-wins, announce-and-log
1. **Off by default.** Only a Business workspace owner/admin can enable recording, per number or per ring group, never per individual agent silently.
2. **Always announce.** Every recorded call plays a short spoken notice to *every* party at connect ("This call may be recorded for quality and training") and the caller can hang up or continue. No silent mode, no setting to disable the notice. Announcing turns a one-party-consent country into an everybody-informed call, which is also what all-party-consent countries need.
3. **Strictest-wins by default.** If either end is in an all-party-consent or restricted jurisdiction, the notice is mandatory and, where the matrix says so, the caller must also press a key to consent (or the call proceeds unrecorded). Unknown country: treat as strictest.
4. **Consent is evidence.** Store per recording: who announced, timestamp, the announcement version, jurisdictions of both ends, and the caller's action (continued, pressed 1, hung up). A recording without a consent record is deleted by the job that finds it.
5. **Inbound vs outbound:** an agent calling out should state it in the greeting too (script, enforced by the same prompt).
6. **Never record:** emergency numbers, calls to/from numbers marked sensitive (health, legal, financial hotlines the workspace flags), and any call where either party said "stop recording" via keypad (a *0 mid-call pauses recording).
7. **Retention:** default 30 days, workspace-configurable up to 12 months, hard maximum set by the platform. Auto-delete job registered with `SchedulerHealth`. Right-to-erasure and the account export reach recordings and transcripts; a legal hold can pause deletion with an audit entry.
8. **Access:** only workspace owner/admin and the agent on that call; Naara staff cannot play recordings (support sees metadata only). Playback and download are audited. Stored encrypted at rest on Wasabi with signed, short-lived URLs.
9. **Intelligence (transcripts, summaries, sentiment) is opt-in separately** from recording, discloses the processor (the AI provider), excludes the caller's number from prompts where possible, and is never used to train models on customer audio. DPA with each provider; **[COUNSEL]** on cross-border transfer (Nigeria to US/EU).
10. **Terms:** the workspace owner is the controller for their callers' data and accepts the recording duty in the Business terms; Naara is the processor. The caller-facing notice links to the workspace's privacy page.

## Jurisdiction matrix (starting point, **[COUNSEL]** must verify each row)
| Market | Recording rule (summary) | Product behaviour |
|---|---|---|
| Nigeria | No single recording statute; NDPA 2023 consent/notice and constitutional privacy apply; treat as notice + lawful basis required | Announce + log |
| Ghana, Kenya, South Africa | Participant recording generally allowed with notice/consent; data-protection law applies (RICA in SA restricts interception by non-participants) | Announce + log; keypad consent where counsel advises |
| UK | Lawful for business with notice and legitimate purpose (UK GDPR, PECR) | Announce + log |
| EU | GDPR: lawful basis, notice, minimisation; several states stricter | Announce + log; keypad consent in strict states |
| US | Federal one-party; about a dozen states (e.g. CA, FL, IL, PA, WA) require all parties | Announce + keypad consent when either end is in an all-party state |
| Canada | One-party federally, but privacy law needs notice | Announce + log |
| Unknown | n/a | Strictest |

## Decisions I need from you before Prompt 15
1. Accept announce-always plus strictest-wins? (My strong recommendation: yes.)
2. Default retention 30 days with a workspace-configurable ceiling of 12 months?
3. Is keypad consent required for all-party-consent markets, or is announce-and-continue acceptable to you? (Counsel call. Keypad is safer, costs some completion.)
4. Do we ship recording first and intelligence later? (Recommended: yes, as two switches, so recording does not wait on AI-processor contracts.)
5. Who is counsel for sign-off per market? Prompt 15 should not reach production until Nigeria and one EU/UK reviewer have signed the matrix.
