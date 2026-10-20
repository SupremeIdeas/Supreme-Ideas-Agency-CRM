# Trustpilot reviews (display layer)

Admin: Admin > More > Trustpilot reviews. Everything is OFF until an admin turns it on.

## What ships
- Official TrustBox widgets only (carousel, mini, micro combo, review list). The admin pastes the Business Unit ID and the template IDs from Trustpilot's own TrustBox builder; the code never guesses an ID.
- Placements: homepage reviews section, rewards page, sign-in/register badge, end of every blog article, and a `/reviews` page (404 until its switch is on).
- With no template ID a placement is a plain link to the profile. Nothing is curated, filtered or gated; ratings are never required for any reward.
- The platform's own testimonials stay on unless an admin switches them off.
- CSP: Trustpilot origins are added to script/style/frame/connect-src only while the layer is live. The list is editable.
- Optional "click to load": the widget script loads only after the visitor presses a button (the platform has no cookie-consent banner).
- `php artisan naara:verify trustpilot`.

## NOT built (on purpose)
Review invitations with reference IDs, review sync, and any reward for leaving a review.

## UNVERIFIED, confirm before relying on them
1. The default CSP origins (widget.trustpilot.com, www.trustpilot.com, *.trustpilot.com): check the browser console on a live page and adjust the list.
2. Whether the Trustpilot plan in use allows the review-list widget and invitations.
3. Trustpilot's rules on incentivised reviews: sources conflict. Rewards stay off until the owner has read Trustpilot's current guidelines and signed off.

## Master: what Naara still needs to paste from the Trustpilot account
1. Business Unit ID (from any TrustBox snippet: `data-businessunit-id`).
2. Template IDs for the widgets you want: Carousel, Mini, Micro combo, Review list (`data-template-id` from each snippet).
3. Confirm the plan you are on allows the Review list widget.
4. The real addresses the widget loads, from the browser console on a live page (to confirm the allowed list).
5. Your decision on any review invitations or incentives, after reading Trustpilot's current guidelines. Both stay off until then.
The Naara profile address (naara.app) is preset on master only; white labels start empty and follow `docs/TRUSTPILOT-WHITE-LABEL-SETUP.md`.

Invitation script: the Trustpilot JavaScript-integration script is wired (`x-trust.invite-script` in the page head). Master has its key preset in `NaaraTrustpilotProfile` (master only); white labels paste their own in the admin panel. Sending invitations with reference IDs and review sync remain unbuilt.

## After-call rating sheet (master only, N2N calls)
After a call that actually connected, a "How was your call?" sheet (5 stars, Not now / Submit) can appear, followed by an invitation to review Naara on Trustpilot. Admin > N2N > Calls > "Ask for a rating after a call" (OFF by default; also needs the Trustpilot profile address above). The Trustpilot button opens Trustpilot's own review form (`/evaluate/<your profile>`); we never see or touch what a person writes there.
Rules it follows: the invitation is offered to everyone who answers, whatever stars they chose (Trustpilot does not allow inviting only happy customers); a 1-3 star answer additionally shows a support link, never instead of the invitation; nothing is offered or paid for a review. The stars are stored with the call as a private quality signal (`n2n_call_ratings`). Short calls (default under 20 s) and the same person within the cool-down (default 7 days) are not asked. Confirm Trustpilot's current guidelines before changing any of this.

## Review moments (all installs; Brain and Business Suite moments are master only)
Admin > Trustpilot > Review moments. After an eSIM is delivered, a verification code arrives, a number goes live or a gift card is delivered, the app can ask "How did it go?" the next time the person is idle (default 15 minutes) or comes back after being away that long. OFF by default; needs the Trustpilot profile address.
Rules: only an objective completed experience qualifies someone (never sentiment); every rating gets the same Trustpilot invitation, and a 1 to 3 star answer also gets a support link; a refunded or failed order is not asked about; nothing is offered or paid for a review; asks are rate-limited per person (default one every 14 days across the whole app, including the master after-call sheet). Stars are kept privately in `review_moments`. Confirm Trustpilot's current guidelines before changing any of this.
