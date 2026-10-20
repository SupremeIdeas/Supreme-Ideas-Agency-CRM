---
title: Build on Naara: API Keys, Signed Webhooks and Testing Safely
category: Developers & Integrations
excerpt: How to connect your own tools to Naara: create a key, call the API, receive signed webhooks and verify them properly.
published: 2026-10-11 09:00:00
---
If you are building something on top of Naara, you want three things: a key you can revoke, events you can trust, and a way to test without spending real money.

## Keys

Create an API key in your dashboard. A key belongs to your account or workspace, shows only a short preview after creation, and can be revoked at any time. Treat it like a password: keep it on your server, never in a web page or an app you ship.

## Webhooks you can trust

When something happens, such as an order finishing or a message arriving, Naara can POST a JSON body to your address. Each delivery carries a timestamp and a **signature**, a HMAC of the timestamp and body made with your webhook secret. Your server should:

1. Recompute the signature from the raw body.
2. Compare it in constant time.
3. Reject deliveries whose timestamp is far in the past.
4. Treat a repeated event id as already handled.

If you skip step one, anyone who guesses your address can send you fake events.

## Public addresses only

Webhook addresses must be https and reachable on the public internet. Addresses that point inside a private network are refused, which protects both of us. A webhook that keeps failing is switched off after ten failures in a row, and you can re-enable it once your server is healthy.

## Retries

A failed delivery is retried with a growing delay. Answer with any 2xx status quickly and do the heavy work afterwards, so a slow handler never causes duplicates.

## Test first

Use sandbox keys for providers and a test channel for messaging. For Respond, a Telegram bot and a web chat on a test page are enough to see the whole path, from message in to webhook out.

## Common mistakes

- Parsing the JSON before checking the signature.
- Logging the secret.
- Doing slow work before answering.
- Forgetting that the same event can arrive twice.

## Where to go next

The developer documentation lists every endpoint with example requests, and the guided tour of the developer dashboard shows where keys and webhooks live.

## Questions people ask

**How do I rotate a key?**
Create a new key, switch your server over, then revoke the old one. There is no downtime if you do it in that order.

**What status code should my webhook return?**
Any 2xx means received. Anything else is retried with a growing delay.

**Can I replay an event?**
Treat events as at-least-once: store the event id and ignore a repeat. That makes a replay safe.
