# White-label safe update, live provider check, and owner decisions (2026-10-11)

Master-only working document. Not for forks.

## 1. Live provider check (read-only, 2026-10-11)

Calls were catalogue, price, stock and balance reads. **Nothing was purchased.** Keys were passed through the process environment only and are not in any file. The keys were pasted into chat, so rotate them.

| Provider | Result | Purchasable now? |
|---|---|---|
| eSIM Go | Auth OK (org "NAARA CONNECT TECHNOLOGIES LIMITED", prepaid, USD). Catalogue returns bundles with live prices, e.g. NG 1GB/7D. **Balance is 0**, minimum spend 1000. | Catalogue yes. Orders will be refused until the account is funded. |
| Getatext | Auth OK. `/prices-info` returns services with live price, TTL and stock. **Balance 16.62.** | Yes (OTP/rental) |
| 5sim | Auth OK (rating 96). Nigeria prices and stock are live (e.g. WhatsApp 0.2769, 280,945 in stock). **Balance 0.** | Catalogue yes. Buying needs a top-up. |
| HeroSMS | Provider answers `BAD_KEY`. The key as pasted is rejected. | No. Re-issue the key in the HeroSMS dashboard. |
| Twilio | Error 20003: the auth token is not valid for that account SID. Number search, voice pricing and calling are all blocked by it. | No. Re-copy the token (or use an API key + secret). |
| Quibity / eSIM.sm | Our configured base `https://esim.sm/api/reseller/v1` returns **404 (HTML)**. `https://esim.sm/api/v1/*` exists but answers "unsupported version of the app", so it is the consumer app API, not a reseller API. The key is a valid JWT. I could not find public reseller docs. | **Unverified. Ask Quibity support for the reseller base URL and auth header.** Do not sell Quibity plans until confirmed. |
| Airalo, Telnyx, Virtsms | No keys supplied. Not tested. | n/a |

## 2. Worldwide calling: what was found and fixed

* The dialer accepts any E.164 destination, so geography is not restricted by our code.
* **Fixed:** a failed Twilio price lookup used to fall back to a flat 0.02/min, which would sell expensive destinations below cost. A configured account now refuses to quote (user is not charged).
* **Fixed:** every call showed one shared platform caller ID. The callee now sees the caller's own active Twilio Naara line, else the platform default. Other providers' numbers are never presented (Twilio can only present numbers it owns or has verified).
* **Operator action (not code):** Twilio blocks outbound calls to countries not enabled under Voice, Geo Permissions. Enable every country you intend to sell. Twilio also needs a verified or owned caller ID for each presented number.
* **Voice drivers.** Twilio and Telnyx (TeXML) exist. Vonage uses JSON NCCO, Sinch uses SVAML callbacks and Plivo uses its own XML. None is TwiML-compatible, so each would need its own call-control layer and a browser SDK, and none can be verified without sandbox keys. I did not ship unverified drivers. The in-browser dialer is Twilio-only because it loads the Twilio Voice SDK. Next step when keys exist: one driver at a time, starting with Plivo.

## 3. Sender-ID SMS decision: recommendation is NO for line messages

A message sent from a customer's Naara line must leave from that line. Routing it through an aggregator under a sender ID:

1. Removes the number the recipient knows, so replies go nowhere. That breaks the product promise of a permanent two-way line.
2. In Nigeria, alphanumeric sender IDs must be registered with the operators. Unregistered ones are blocked or rerouted to a generic ID, which also feeds the DND and spam filters. In the US, alphanumeric is not supported on domestic routes at all.
3. Presenting a different origin than the line owner's is a spoofing and consent problem regulators and carriers treat as fraud.
4. A silent reroute hides a capability gap. If a line cannot send SMS (for example Sonetel), the honest answer is to say so.

Use the aggregator driver only for **platform-originated** messages (OTP, receipts, security notices) under one registered "NaaraSim" sender ID with a numeric fallback. Keep it send-only and off by default, as built. If line messages are ever allowed, make it an explicit per-message opt-in with a visible "recipient cannot reply" warning, never a default.

## 4. Safe little update for the white labels

**Ship (generic, this install's own operation):**
* Worldwide-calling pricing fail-closed and per-user caller ID (ported in the working tree, tests green).
* Permanent skin picks: once saved, a pick can never be swapped or dropped. Upgrade to Extended adds only the extra three. Everything else stays hidden. Basic = 2, Extended = 5 (ported in the working tree).

**Never port (strict criteria).** A change is master-only if it touches any of:
1. another instance, licence issuing, approval, purchase or oversight (License Surgery, SkinAllowance, `config/white_label_skins.php`);
2. N2N, Naara Widgets, NaaraBrain or Business Suite paths or tokens;
3. the `/faq` page, Provider Capability Radar, the Tier 3 #9 billing and hot-menu work;
4. the Bootstrap engine and its registry (see decision 3 below);
5. anything with `n2n`, `merchant_widget` or `merchant-widget` in its path.

## 5. Owner decisions needed

1. **Telnyx voice and the regional SMS gateway in forks.** Telnyx voice is a provider-capability feature and fits the criteria, but it is a large multi-file port (controllers, forwarding, keys UI). Recommend a separate reviewed batch. Default: not in this update.
2. **Quibity.** Get the real reseller URL before it stays enabled anywhere.
3. **Fork bootstrap.** Forks have no Bootstrap engine today. Recommend a small fork-native preflight (env, keys present, migrations, queue/scheduler, storage) built only from fork code, not a port of the master engine.
4. **Skin lock for existing licensees.** Anyone who already saved a selection becomes locked on update. Confirm that is wanted (matches your stated rule).
5. **Rotate** every key pasted in chat.
