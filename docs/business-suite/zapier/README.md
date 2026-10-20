# Naara Business for Zapier (unpublished definition)

A Zapier Platform app definition against the read-only Business Suite API and webhooks (Prompt 18). Three triggers (new message, call completed, recording ready) as REST Hooks, plus polling fallbacks for sample data. There are no actions: the first version is read-only by design.

Not yet published or run against Zapier's validator: `npm install && npx zapier validate` from this folder, then `zapier push` with a Zapier developer account. Verify deliveries with `X-Naara-Signature` (HMAC-SHA256 of the raw body, hex, prefixed `sha256=`, compared in constant time).
