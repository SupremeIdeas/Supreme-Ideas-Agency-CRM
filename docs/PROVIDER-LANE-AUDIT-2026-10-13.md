# Provider lane audit (2026-10-13)

Rule: match product and country to the owning lane; fall back only inside the lane; never cross lanes. A provider only takes part when its keys are configured, so an unkeyed provider is skipped, never an error.

## Lanes as coded

| Lane | Order (first = primary) | Code |
|---|---|---|
| eSIM data | eSIM Go, Airalo, Quibity, Zendit | `ProviderRouter::$chain` |
| eSIM voice/number bundles | Zendit, 1Global, Monty Mobile, Gigs | `ProviderRouter::$voiceChain` |
| SMS verification and rentals, US | Getatext, 5sim, HeroSMS, VirtSMS, SMSPool, OnlineSIM | `SmsNumberRouter::laneFor` |
| SMS verification and rentals, rest of world | 5sim, HeroSMS, VirtSMS, SMSPool, OnlineSIM | `SmsNumberRouter::laneFor` |
| Permanent numbers (voice + SMS) | Twilio, Telnyx, Vonage, Sinch, Plivo, Sonetel | `PermanentNumberRouter::$lane` (SMS router hands permanent orders here) |
| Voice calls | Twilio (primary), Telnyx | voice `BINDINGS` |

Findings:
- Data and number products never share a chain. Quibity is data-only and sits only in the data chain; Getatext is only in the US SMS lane. No cross-lane leak found.
- The permanent lane lists six providers, the SMS router's own permanent list only two. That is by design: permanent orders leave the SMS router for the permanent module, which owns the wider lane. Providers without keys are skipped.
- Within each lane the live registry orders by circuit-breaker state, so an open circuit is skipped before a fallback is tried.

## Live verification status (keys supplied 2026-10-13)

| Provider | Lane | Verified | Open item |
|---|---|---|---|
| eSIM Go | data primary | catalogue (5,434 bundles, paginated), balance, order body shape, refund-on-refusal | one funded order to confirm the assignment read; webhook secret |
| Airalo | data secondary | code reviewed against the public API; catalogue now paginated (`page`, `meta.last_page`) | no credentials, so nothing live |
| Quibity | data tertiary | client rebuilt on the partner API (`esim.sm/api/reseller/v1`, countries and plans, purchase, top-up, refund); the supplied key is live, so `QUIBITY_SANDBOX` must be false | true wholesale price (`price` vs `salePrice`) only visible in a purchase response; the conservative higher figure is used |
| Zendit, 1Global, Monty, Gigs | voice eSIM | not tested | no credentials |
| Getatext | US SMS primary | balance, price list, US routing | none |
| 5sim | non-US SMS primary | balance, price | callers must pass its country slugs |
| HeroSMS | SMS fallback | not verified | key rejected as BAD_KEY; re-copy from profile |
| VirtSMS, SMSPool, OnlineSIM | SMS fallbacks | not tested | no credentials |
| Twilio | permanent and voice primary | monthly cost, number search, SMS price | none |
| Telnyx | permanent and voice backup | same read-only checks | search returns masked numbers |
| Vonage, Sinch, Plivo, Sonetel | permanent fallbacks | not tested | no credentials |
