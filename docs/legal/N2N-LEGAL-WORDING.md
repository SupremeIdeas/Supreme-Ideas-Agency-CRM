# Naara chat: wording that needs counsel's sign-off (MASTER-ONLY)

Prepared 2026-10-04. This is engineering's best draft of what the product does and says. It is not legal advice. Everything marked **[COUNSEL]** needs a lawyer in each launch market (Nigeria first: NDPA 2023 and NDPC rules; then UK/EU GDPR, Ghana, Kenya, South Africa) before the wording is treated as final.

## 1. "Not end-to-end encrypted"

**What is true in the code today** (so the wording must not promise more or less):
- Messages travel over TLS and are stored encrypted at rest. Naara servers can decrypt them (that is what "not end-to-end" means). Staff do **not** have a screen that browses chats.
- Content reaches staff only through a **report**: the report stores the reporter's reason plus an excerpt of the reported person's last 5 messages at the time of the report (Admin > N2N > Chat reports, scope `n2n.moderate`).
- Content can also reach a regulator, court or law enforcement through a lawful request. No such tool exists yet; it would be a manual, audited export.
- Chat lock and disappearing messages do not change any of this. Disappearing messages remove content from the app and storage after the timer; they cannot stop a recipient screenshotting, copying or forwarding.

**In-app text now shipping** (chat thread banner):
> Messages are encrypted in transit and at rest, but not end-to-end encrypted: Naara can see a message if it is reported or the law requires it. Links are not allowed.

**Do not say** "private", "secure chat", "nobody can read your messages", "military-grade", or compare to WhatsApp/Signal encryption. Marketing copy must use the same sentence shape as the banner. **[COUNSEL]**: confirm the sentence satisfies transparency duties (NDPA s.27 notice content, GDPR Art. 13) and does not need to live in the privacy policy as well. Recommended: yes, mirror it in the privacy policy under "How we handle chat content", with the retention periods below.

**Retention to state (engineering proposal):** live messages until the member deletes them or the disappearing timer fires; reported excerpts 12 months after the report is closed; erased accounts per `docs/ACCOUNT-ERASURE.md`. **[COUNSEL]** confirm the 12 months.

## 2. Forwarding received text

Forwarding copies another person's words to a third party. Risks: confidentiality, personal-data disclosure (the original sender never agreed), and harassment (forwarding to shame someone).

**What the product does:** only text can be forwarded (not photos, video or voice notes), each copy is metered, marked "Forwarded", capped at a small number of targets per action, and subject to the same link filter, restriction checks and rate limits as a normal send. The original sender is not notified. Forwarded copies cannot be recalled.

**In-app text now shipping** (forward panel):
> The person who wrote a message did not agree to it being shared further, so only forward what you are comfortable with them knowing about. Forwarded copies cannot be pulled back once sent.

**[COUNSEL] questions:**
1. Is the notice enough, or must the Terms of Use put responsibility for onward sharing on the forwarder explicitly? (Recommended: add one clause in Terms.)
2. Should forwarding be blocked when the original sender has disappearing messages on? (Recommended: yes, as a product rule: a disappearing message cannot be forwarded. Not built yet; say the word and it is a few lines in `MessageActions`.)
3. Does a report about forwarded content need to carry the chain (who first sent it)? Not stored today.

## 3. Staff moderation (explains the delegation decision)
Staff may moderate only when a super admin grants `n2n.moderate`. Staff can dismiss, warn and restrict up to 7 days; permanent restriction and lifting are admin-only. See `docs/N2N-HARDENING.md`. **[COUNSEL]**: confirm staff-level access to message excerpts is covered by the privacy policy's "our team" language and that confidentiality terms in staff agreements name it.
