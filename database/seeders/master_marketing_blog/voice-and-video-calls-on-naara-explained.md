---
title: Voice and Video Calls on Naara: What Makes a Call Connect (and Why TURN Matters)
category: Naara Chat & Community
excerpt: Calls inside Naara are peer to peer when they can be and relayed when they must be. Here is what that means for call quality and what to check if a call will not connect.
published: 2026-10-12 09:00:00
---
Most calls on Naara just work. When one does not, the reason is almost always the network between the two people, not the app.

## How a call finds its way

Two phones try to talk directly. A phone on a mobile network or behind a strict office router often cannot be reached directly, so a **relay server** (TURN) carries the audio instead. The relay is a safety net. It makes calls connect in places where direct connection fails.

## What the platform does

Naara hands each call short-lived relay credentials, so a credential cannot be reused later. If the relay is not configured, messaging still works, but some calls will fail on strict networks. That is why a live install should have a relay.

## Video

One-to-one video is available when the admin switches it on. A natural beauty filter is **off by default** and each person chooses whether to use it from a list. Nothing is applied unless you pick it.

## Who pays

Calls draw from NaaraCredits or your wallet, whichever you choose to fund them. Group calls follow the same rule.

## If a call will not connect

1. Check you have a data connection, not only Wi-Fi with no internet.
2. Allow microphone (and camera) permission for the app.
3. Try switching between Wi-Fi and mobile data.
4. Make sure the other person has the app open or notifications on.
5. If it only fails on one network, that network is probably blocking calls. Tell Support the network name.

## Quality tips

- Use headphones to avoid echo.
- Move away from heavy interference such as a microwave or a crowded Wi-Fi channel.
- Close apps that use the camera or microphone in the background.

## For admins

Set the relay addresses and secret in the environment, then place a test call between two phones on different networks. The Verify page shows whether the relay is configured.

## Questions people ask

**Why does a call work on Wi-Fi but not mobile data?**
Some mobile networks block direct connections. The relay server fixes most cases; if it still fails, tell Support the network name.

**Is call audio recorded?**
Naara Chat has no call recording feature. Recording exists only for Business workspace lines, where it is off by default and announced to every caller.

**Do group calls cost more?**
They draw from your credits or wallet the same way, and the cost is shown before you start.
