# Owner credentials guide: what to gather, where to get it, where it goes

Save this file and work through it top to bottom. Everything is ordered by what unlocks the most first. Last updated 2026-10-19.

> **STOP: one thing to do first.** The Telegram bot token for `@NaaraConnectBot` was pasted into a chat. Treat it as public. Open Telegram, message **@BotFather**, send `/revoke`, choose the bot, and let BotFather issue a new token. Keep the new token only in your password manager, in `.env`, or in the Naara Channels page. Never in chat, email or a screenshot. (Your Meta **App ID** and **Business ID** are not secrets; they appear in URLs. The **App Secret**, **access tokens** and **bot tokens** are secrets.)

## How to give me things safely
1. **Do not paste secrets in chat.** Put them in the live server's `.env` (or Admin > API keys) yourself.
2. Then run `php artisan naara:credentials:status` and `php artisan naara:respond:credentials` and paste me the table. They show only `empty / saved` and `DEFAULT / SET / MISSING`, never a value.
3. For tests I run, use **sandbox or test keys only**, supplied through the session's secret settings (environment secrets), never in a message.
4. After you add a credential, run `php artisan naara:verify` (or Admin > System > Verify). I read the result and fix what is red.

## Status legend used below
- **DEFAULT** = already filled in for you in `config/omni.php`; override only if it is wrong.
- **YOU** = only you can create it.
- **SANDBOX OK** = a free test key exists, use that first.

---

## 0. The server itself (everything depends on this)
| Item | Where it goes | Where to get it | Notes |
|---|---|---|---|
| `APP_URL` (must be https) | `.env` | Your live domain | Meta and Telegram refuse non-https webhook addresses. |
| Database | `DB_*` | Your host | MySQL 8, utf8mb4. |
| Queue + cron | `QUEUE_CONNECTION` | VPS: Redis + Horizon. cPanel: `database` + one cron: `* * * * * php artisan schedule:run` | Respond broadcasts and the AI run from the queue. Without it replies wait. |
| Email transport | `MAIL_*` | Postmark, SES, Resend, or your host's SMTP | Needed for email replies, notices, handbook receipts. Admin > Email settings has a **Send test**. |
| File storage | `WASABI_*` (and `R2_*` for chat media) | wasabi.com / Cloudflare R2 | Never local disk. |
| Admin path + 2FA | `ADMIN_PATH`, `ADMIN_REQUIRE_2FA` | You choose | Keep the default path secret. |

## 1. Naara Respond and the Business Suite
| Credential | Env name | Status | Where to get it |
|---|---|---|---|
| Anthropic API key | `ANTHROPIC_API_KEY` | YOU, SANDBOX OK | console.anthropic.com > API keys. Powers the AI assistant, NaaraBrain and the inbox helper. Model classes: `OMNI_AGENT_MODEL_CLASS=fast` (default, cheapest). |
| Telegram bot (per business) | pasted in **Business > Channels** by each workspace | YOU | Each business creates its own bot with @BotFather and pastes the token. Nothing for you to set. |
| Naara's own bot | `OMNI_TELEGRAM_BOT` = **NaaraConnectBot (DEFAULT)**, `OMNI_TELEGRAM_BOT_TOKEN` | YOU (token) | Easiest test: connect `@NaaraConnectBot` to your own Naara workspace from Channels, using the **new** token after the revoke. |
| Meta App ID | `OMNI_META_APP_ID` = **2195469598059043 (DEFAULT)** | done | From your screenshot: app "Naara", mode *In development*. |
| Meta Business ID | `OMNI_META_BUSINESS_ID` = **4491570984493160 (DEFAULT)** | done | Business portfolio "NAARA". |
| Verified domain | `OMNI_META_DOMAIN` = **naara.app (DEFAULT)** | in progress | Your screenshot shows *Verify Domain* with a DNS TXT record: add it in Cloudflare DNS (see 1a). |
| Meta App Secret | `OMNI_META_APP_SECRET` | YOU | developers.facebook.com > your app > **App settings > Basic > App secret > Show**. Used to prove a webhook really came from Meta. |
| Webhook verify token | `OMNI_META_VERIFY_TOKEN` | YOU | Any long random text you invent (32+ characters). You paste the same text into Meta's webhook setup. |
| WhatsApp number/WABA/token | `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_WABA_ID`, `WHATSAPP_ACCESS_TOKEN` | YOU | Only for **Naara's own** WhatsApp (support autopilot). Customers connect their own. |
| Email replies | `MAIL_*` (above) | YOU | Each business forwards its support mail to the address shown on its Email channel card. |

### 1a. Meta: what your screenshots show and what is still missing
- The **Marketing API welcome email** is about advertising. Respond needs the **WhatsApp**, **Messenger** and **Instagram messaging** products instead. In the new Meta dashboard these are added as **use cases** (the banner in your screenshot says tools to create an app and add a use case are rolling out gradually; use the normal **Add use case** button if your agent tool is not available).
- **Business verification** (Meta Business Settings > Security centre): upload your company registration and proof of address. Without it WhatsApp stays on test numbers and low limits.
- **Domain verification**: add the TXT record Meta shows (`facebook-domain-verification=...`) to `naara.app` in Cloudflare (DNS > Add record > TXT, name `@`), then press **Verify domain**.
- **Tech Provider** status (needed so *your customers* can connect their own WhatsApp from Naara with one click): later, after business verification. Until then each business pastes its own tokens, which already works.
- **App Review**: submit `whatsapp_business_messaging`, `whatsapp_business_management`, `instagram_manage_messages`, `pages_messaging` with a screen recording of Respond answering a message. Do this **after** the sandbox test below passes.
- **Test first**: in the app's WhatsApp > API Setup use the free **test phone number** and add your own phone as a recipient. That is enough for me to prove send/receive.
- Webhook addresses to paste into Meta (shown per connection in Business > Channels): `https://YOUR-DOMAIN/webhooks/meta/{connection}` plus the verify token above. Subscribe to the `messages` field.

## 2. Naara Chat (people, brands, calls)
| Credential | Env name | Where to get it | Notes |
|---|---|---|---|
| TURN server for calls | `N2N_TURN_URLS`, `N2N_STUN_URLS`, `N2N_TURN_SECRET` | Your own coturn server (VPS) or a TURN provider | Voice calls fail behind strict networks without it. Messaging works without it. |
| Push notifications | `ONESIGNAL_APP_ID`, `ONESIGNAL_API_KEY` | onesignal.com | So messages reach a phone that is not open. |
| GIFs | `GIPHY_API_KEY`, `TENOR_API_KEY` | developers.giphy.com, Google Cloud (Tenor) | Optional. |
| Chat media storage | `R2_*` | Cloudflare R2 | Photos, voice notes, files. |
| Video calling | Admin > Naara Chat > Calls | per the VIDEO-CALLING-PLAN | Off by default. |

## 3. Money in (accept payments)
| Provider | Env names | Notes |
|---|---|---|
| Paystack (NGN cards, bank) | `PAYSTACK_SECRET_KEY`, `PAYSTACK_PUBLIC_KEY` | SANDBOX OK. The webhook addresses to paste into each console are printed by `php artisan naara:credentials:status --urls`. |
| Flutterwave | `FLUTTERWAVE_SECRET_KEY`, `FLUTTERWAVE_PUBLIC_KEY`, `FLUTTERWAVE_SECRET_HASH` | SANDBOX OK. |
| Stripe | `STRIPE_SECRET_KEY`, `STRIPE_PUBLIC_KEY`, `STRIPE_WEBHOOK_SECRET` | SANDBOX OK. |
| PayPal | `PAYPAL_CLIENT_ID`, `PAYPAL_CLIENT_SECRET`, `PAYPAL_WEBHOOK_ID` | SANDBOX OK. |
| Crypto | `NOWPAYMENTS_API_KEY`, `NOWPAYMENTS_IPN_SECRET` (and Cryptomus) | **Regenerate** the NOWPayments keys that were pasted in chat earlier. |

## 4. Products Naara sells
| Area | Env names | Notes |
|---|---|---|
| eSIM | `ESIMGO_API_KEY`, `AIRALO_CLIENT_ID/SECRET`, `QUIBITY_API_KEY`, `ZENDIT_API_KEY`, `ONEGLOBAL_*`, `MONTYMOBILE_*`, `GIGS_*` | Use the `*_SANDBOX=true` switches first. Funded balances are needed for the real $5 order tests. |
| Numbers / SMS | `GETATEXT_API_KEY`, `FIVESIM_API_KEY`, `HEROSMS_API_KEY` (**regenerate**), `VIRTSMS_API_KEY`, `TELNYX_*`, `TWILIO_*`, `AFRICASTALKING_*` | Lane rule: US = Getatext, global = 5sim, backup = HeroSMS/VirtSMS, calls = Twilio, permanent/voice backup = Telnyx. |
| Gift cards | `BITREFILL_API_KEY`, `TILLO_API_KEY/SECRET`, `RELOADLY_*` | |
| Monty Mobile | Tenant ID, Sender ID, your server IP to whitelist | Only you can get these from Monty. |

## 5. Safety, support and ops
| Item | Env | Notes |
|---|---|---|
| Cloudflare Turnstile | `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | Bot protection on forms. |
| Google Safe Browsing | `SAFE_BROWSING_API_KEY` | Checks brand website links. |
| Sentry | `SENTRY_LARAVEL_DSN` | Error alerts. |
| Support WhatsApp/email | `SUPPORT_WHATSAPP`, `SUPPORT_EMAIL` | Shown in the app. |
| Backups | `BACKUP_ARCHIVE_PASSWORD` + Wasabi | Test one restore. |

## 6. Accounts I need for end-to-end tests (create these on the live or staging site)
Create them yourself; tell me only the **email addresses and roles**, never passwords in chat.
1. **Admin** (super admin): your existing owner account.
2. **Staff** user with a limited role (to prove staff cannot delete users).
3. **Normal user A** and **normal user B** (for Naara Chat, People and Brands).
4. **Brand owner** with an active Naara Pro plan (granted free by you in Admin > Brand directory > Grant Pro is fine).
5. **Business owner** workspace on a Pro plan that includes *Shared inbox* and *NaaraBrain assistant* (Admin > Brand directory > plan capabilities).
6. **Business agent** (member with the *Agent* role) to prove agents cannot open Automation.

## 7. Switch-on order at launch
1. `php artisan migrate --force`, `php artisan db:seed --class=BrandPlanSeeder --force`, then the seeders in `docs/GO-LIVE-RUNBOOK-2026-10.md`.
2. Admin > Business Suite settings: turn on **Business Suite**, **Shared inbox**, **NaaraBrain assistant**, **Naara Respond inbox** (all off by default). Set **Who can use the Suite** to *Naara Pro plans* and grant features to plans.
3. Run `php artisan naara:respond:pricing --assume-granted` to size each plan's AI allowance (see `docs/RESPOND-PRICING-REPORT.md`).
4. Connect the Telegram bot and a web chat for your own workspace; send yourself a message from a phone; answer it in Respond.
5. Add 3 FAQs in Respond > Automation, set the assistant to **Suggest**, ask a question, press **Use this reply**.
6. Only then consider **Auto** mode and Meta App Review.
