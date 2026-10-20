<?php

/**
 * MASTER-ONLY setup guides shown in Admin > Respond setup guide. Plain language, in the order a person actually does things, with the exact addresses to paste,
 * what each credential looks like (every sample value below is FAKE and only shows the shape), and the mistakes that break each setup.
 * Provider dashboards move their menus now and then: every section says where to look and what to search for, and carries `verified_on` so a stale guide is visible.
 * Placeholders: {APP_URL} is replaced with this install's address; {OMNI_VERIFY_TOKEN_ENV} etc. are shown as the .env names.
 */
return [
    'verified_on' => '2026-10-19',

    'order' => ['basics', 'telegram', 'webchat', 'email', 'anthropic', 'meta_map', 'meta_where', 'meta_app', 'whatsapp', 'instagram_messenger', 'social_login', 'go_live'],

    'sections' => [
        'basics' => [
            'title' => '1. Before anything else',
            'minutes' => 10,
            'intro' => 'Meta, Telegram and email all need to reach your server from the internet. Get these right first or every later step fails for a reason that has nothing to do with the step.',
            'needs' => ['A live domain with https (a padlock)', 'APP_URL in .env set to that https address', 'The queue worker or cron running'],
            'steps' => [
                ['title' => 'Set APP_URL to your real https address', 'body' => 'Open .env on the server and set APP_URL=https://naara.app (your domain, no slash at the end). Meta and Telegram refuse http addresses and addresses with a port number.', 'copy' => 'APP_URL=https://naara.app'],
                ['title' => 'Clear the config cache', 'body' => 'After any .env change run: php artisan optimize:clear. Without it the old value is still used.', 'copy' => 'php artisan optimize:clear'],
                ['title' => 'Make sure the queue runs', 'body' => 'Respond saves every incoming message first and processes it from the queue. On shared hosting that is one cron line every minute; on a VPS it is Horizon.', 'copy' => '* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1'],
                ['title' => 'Check the status table', 'body' => 'Run the command below. DEFAULT means a safe value is already filled in; SET means you added it; MISSING means a feature that needs it will show Coming soon.', 'copy' => 'php artisan naara:respond:credentials'],
            ],
            'mistakes' => ['Using http:// or a server IP instead of the domain.', 'Putting the site behind a firewall or login that blocks Meta and Telegram (they must reach /webhooks/... without logging in).', 'Editing .env but not running optimize:clear.', 'A trailing slash or spaces after APP_URL.'],
            'samples' => [],
            'verify' => ['Open https://YOUR-DOMAIN/up in a private window: it should load without asking you to sign in.', 'php artisan naara:verify omni shows no red lines.'],
        ],

        'telegram' => [
            'title' => '2. Telegram bot (free, 5 minutes)',
            'minutes' => 5,
            'intro' => 'Telegram needs no approval. Each business connects its own bot from Business > Channels. You can also connect @NaaraConnectBot to your own workspace to test.',
            'needs' => ['A Telegram account', 'Business > Channels open in your Naara workspace'],
            'steps' => [
                ['title' => 'Open BotFather', 'body' => 'In Telegram search for @BotFather (the one with the blue tick) and press Start.'],
                ['title' => 'Create the bot', 'body' => 'Send /newbot, choose a display name, then a username that ends in bot (for example NaaraConnectBot). BotFather replies with a token.'],
                ['title' => 'Copy the token carefully', 'body' => 'The token is two parts joined by a colon: numbers, then letters. Copy all of it, with no spaces. Treat it like a password.', 'sample' => 'telegram_token'],
                ['title' => 'Paste it in Channels', 'body' => 'In your Naara workspace open Business > Channels > Telegram, paste the token and press Connect Telegram. Naara checks it with Telegram and registers the address that receives messages. There is nothing to paste into Telegram yourself.'],
                ['title' => 'Send a test', 'body' => 'Open your bot in Telegram, press Start and write Hello. It should appear in Business > Respond within a few seconds.'],
            ],
            'mistakes' => ['Sharing the token in chat or email. If you did, send /revoke to BotFather and use the new token.', 'Copying only the part after the colon.', 'Connecting the same bot to two workspaces (a bot belongs to one).', 'Expecting messages when the queue is not running.'],
            'samples' => ['telegram_token'],
            'verify' => ['Business > Channels shows the bot as On.', 'Your test message appears in Respond.', 'Admin > System > Verify shows the omni areas green.'],
        ],

        'webchat' => [
            'title' => '3. Website chat (free, 5 minutes)',
            'minutes' => 5,
            'intro' => 'A chat bubble on your own website. Nothing to approve and no account elsewhere.',
            'needs' => ['The address of the website where the bubble should appear'],
            'steps' => [
                ['title' => 'Create it', 'body' => 'Business > Channels > Website chat. Enter the website address exactly as visitors see it, for example https://yourshop.com (no page path). Add several addresses separated by commas if you have more than one site.'],
                ['title' => 'Copy the snippet', 'body' => 'After creating it the card shows one line starting with <script. Paste it just before the closing body tag of your site (in WordPress use any plugin that adds a footer script).'],
                ['title' => 'Test from the site', 'body' => 'Open the site, click the bubble and send a message. It appears in Respond.'],
            ],
            'mistakes' => ['Putting a page path (https://shop.com/contact) instead of the site address.', 'Using http for a live site (only localhost may use http).', 'Forgetting the www version of the site: add both https://shop.com and https://www.shop.com.'],
            'samples' => ['webchat_snippet'],
            'verify' => ['The bubble shows on the site and a test message arrives.'],
        ],

        'email' => [
            'title' => '4. Email (needs your mail settings, 10 minutes)',
            'minutes' => 10,
            'intro' => 'Customers email your support address, you answer in Respond, and replies leave from the same address.',
            'needs' => ['Working MAIL_* settings on the server (Admin > Email settings has a Send test)', 'A way to forward support mail as JSON (an inbound email service or a mail rule)'],
            'steps' => [
                ['title' => 'Set up mail sending first', 'body' => 'In Admin > Email settings choose your transport (SMTP, Postmark, SES, Resend...) and press Send test. Replies cannot leave until this passes.'],
                ['title' => 'Create the Email channel', 'body' => 'Business > Channels > Email. Enter the support address replies come from (for example support@yourshop.com).'],
                ['title' => 'Point inbound mail at the address shown', 'body' => 'The card shows an Address and a Signing secret. Configure your inbound-mail service to POST each email as JSON to that address with the header X-Naara-Signature set to sha256= followed by the HMAC-SHA256 of the raw body using the secret.'],
                ['title' => 'Send a test email', 'body' => 'Email your support address from another account. It appears as a conversation; reply from Respond and check it arrives.'],
            ],
            'mistakes' => ['Signing a re-formatted body instead of the exact raw body.', 'Replies from an address your mail provider has not verified (they get rejected or land in spam).', 'Forgetting SPF and DKIM records for the domain.'],
            'samples' => ['email_inbound'],
            'verify' => ['A test email shows up in Respond and your reply arrives in the other inbox.'],
        ],

        'anthropic' => [
            'title' => '5. AI key (Anthropic)',
            'minutes' => 5,
            'intro' => 'The AI assistant, NaaraBrain and the inbox helper all use one key. Without it they show Coming soon or log an error and do nothing.',
            'needs' => ['An Anthropic account with billing added'],
            'steps' => [
                ['title' => 'Create a key', 'body' => 'Sign in at console.anthropic.com, open API keys, press Create key, name it Naara and copy it once.', 'sample' => 'anthropic_key'],
                ['title' => 'Put it in .env', 'body' => 'Add ANTHROPIC_API_KEY on the server, then run php artisan optimize:clear. Leave OMNI_AGENT_MODEL_CLASS as fast unless you have measured a reason to pay more.', 'copy' => 'ANTHROPIC_API_KEY=sk-ant-...'],
                ['title' => 'Check the cost', 'body' => 'Run php artisan naara:respond:pricing --assume-granted to see what each plan costs you per month at full use.'],
            ],
            'mistakes' => ['Pasting the key into chat or a screenshot.', 'No billing on the Anthropic account (calls fail with a payment error).', 'Granting the assistant to a plan before checking the monthly allowance.'],
            'samples' => ['anthropic_key'],
            'verify' => ['In Respond > Automation > AI assistant, Test your assistant returns an answer.'],
        ],

        'meta_map' => [
            'title' => '6. Meta at a glance: what you are setting up (read this first)',
            'minutes' => 5,
            'intro' => 'Meta\'s dashboard has many menus with similar names, which is why it is confusing. Here is the whole picture in plain words. You have ONE Meta app called Naara, inside ONE business portfolio called NAARA. Everything below hangs off those two things. You never need a second app for WhatsApp, Instagram or Messenger.',
            'needs' => ['Nothing yet: this section is only the map'],
            'steps' => [
                ['title' => 'What Naara offers through Meta', 'body' => 'Four things, each a different product inside the same app: (1) WhatsApp Business Cloud API: Naara\'s own WhatsApp number and, later, each business\'s number. (2) Instagram messaging: replying to Instagram DMs from the Respond inbox. (3) Messenger: replying to Facebook Page messages. (4) Optional: "Continue with Facebook" sign-in for customers. The first three are used for Respond. The fourth is separate and not needed to launch.'],
                ['title' => 'The three places you will click (do not mix them up)', 'body' => 'A) developers.facebook.com > My Apps > Naara: the app itself. This is where the App ID, App Secret, WhatsApp API Setup and webhooks live. B) business.facebook.com > Business settings: the NAARA portfolio. This is where System users (permanent tokens), domains, business verification and your WhatsApp accounts live. C) The Naara admin: Admin > API Keys > Meta group, where you save what you copied.'],
                ['title' => 'Which Facebook Login screen is which', 'body' => 'Your app has "Facebook Login for Business" in its left menu. That product has two parts you will see: Settings (the Client OAuth settings page with Valid OAuth Redirect URIs) and Configurations (the "Create configuration" page). Configurations exist for ONE purpose in Naara: to produce the Embedded Signup Config ID, so a business can connect its own WhatsApp number through Meta\'s popup. Settings (redirect URIs) matter only for the optional Continue-with-Facebook sign-in. If you are only getting WhatsApp running, you can ignore Settings entirely.'],
                ['title' => 'What to do first, in order', 'body' => '1) Finish business verification and verify the domain naara.app (section 8). 2) WhatsApp > API Setup: copy the test IDs and send a test message (section 9). 3) Save the values in Admin > API Keys (section 7). 4) Add Instagram and Messenger (section 10). 5) Only then, if you want it, social login (section 11). Do not start with Configurations or redirect URIs: they are not needed for the first working message.'],
                ['title' => 'Why the app says Unpublished', 'body' => 'In the left menu, Publish shows Unpublished and the app is in Development mode. That is normal until the end. In Development mode the WhatsApp test number works for you and the testers you add. Customers can only message once the app is Live and your business is verified. You switch to Live at the end (go-live checklist), not now. If Meta shows "Required actions", open it: those are the things Meta wants before going Live (privacy policy URL, terms URL, app icon, category).'],
                ['title' => 'Three things that look related but are not', 'body' => '1) The webhook verify token you invent has nothing to do with your login password. 2) The Facebook Login redirect address (/auth/facebook/callback) and the WhatsApp webhook address (/webhooks/whatsapp) are different addresses for different jobs: never paste one where the other belongs. 3) A "Redirect URI to check" box such as https://naara.app/oauth.php is only a test box; Naara has no oauth.php, so a check on that address proves nothing. The sign-in address is https://naara.app/auth/facebook/callback.'],
            ],
            'mistakes' => ['Creating a second Meta app for each product. One app, several products.', 'Starting with Configurations or redirect URIs and getting lost: the first working WhatsApp message needs neither.', 'Mixing the Business settings site and the developers site: tokens and domains are in Business settings; the App ID, secret and WhatsApp setup are in the developer app.', 'Switching the app Live before filling Required actions.'],
            'samples' => [],
            'verify' => ['You can say in one sentence where each of these lives: App Secret (developer app > App settings > Basic), System user token (Business settings > System users), Test IDs (developer app > WhatsApp > API Setup), Config ID (developer app > Facebook Login for Business > Configurations).'],
        ],

        'meta_where' => [
            'title' => '7. Meta values: exactly where each one comes from',
            'minutes' => 15,
            'intro' => 'Seven values make Meta work. This is the map: what each one is, the exact screen it lives on, what it looks like, and where to save it in Naara. Save them all in one place: Admin > API Keys > "Meta: WhatsApp, Instagram, Messenger (Naara Respond)". Saved there they are encrypted, take effect at once, and no .env editing or redeploy is needed. (Putting them in .env instead also works.) Your App ID and Business ID are already filled in as defaults.',
            'needs' => ['A Facebook login that is an admin of the NAARA business portfolio', 'The Meta app called Naara already created'],
            'steps' => [
                ['title' => 'App ID (public, already filled in)', 'body' => 'developers.facebook.com > My Apps > Naara. The App ID is shown at the top and under App settings > Basic. 15 or 16 digits. Yours is 2195469598059043. Save in Admin > API Keys as "Meta - App ID" only if it ever changes.', 'sample' => 'meta_app_id'],
                ['title' => 'App Secret (secret)', 'body' => 'Same app: App settings > Basic > App secret > Show (Meta asks for your Facebook password). 32 letters and numbers. Save as "Meta - App Secret". Never paste it in chat, email or a screenshot.', 'sample' => 'meta_app_secret'],
                ['title' => 'Embedded Signup Config ID', 'body' => 'Developer app left menu > Facebook Login for Business > Configurations (the page that says "Create a configuration"). Press Create configuration. Name it Naara WhatsApp. Choose the login variation WhatsApp Embedded Signup (you can start from a template if one is offered, but the variation must be WhatsApp Embedded Signup). For access token choose System user access token (that is the only kind this variation allows). For assets tick WhatsApp accounts. For permissions tick whatsapp_business_management and whatsapp_business_messaging (add business_management only if Meta asks for it). Save. Back on the Configurations list, copy the Configuration ID (a long number). Save it as "Meta - Embedded Signup Config ID". Honest note: Naara stores it today; the one-click Meta popup on the Channels page is the next build, so businesses connect for now by pasting their number ID and token. You will not lose anything by creating the configuration now.', 'sample' => 'meta_config_id'],
                ['title' => 'Webhook verify token (you invent it)', 'body' => 'No Meta screen gives you this. Make up 32 or more letters and numbers (a password manager can generate one). Save it as "Meta - Webhook verify token", then paste the exact same text into Meta > WhatsApp > Configuration > Webhook > Verify token. If the two differ by one character, Meta says the callback URL or verify token could not be validated.', 'sample' => 'verify_token'],
                ['title' => 'System user access token (for testing and production sending)', 'body' => 'business.facebook.com > Business settings > Users > System users > Add (role Admin). Then Add assets: assign your Naara app (Full control) and your WhatsApp account (Full control). Then Generate token: pick the Naara app, set expiry to Never, tick whatsapp_business_messaging and whatsapp_business_management, and copy it at once (it is shown once). It starts with EAA. Save as "WhatsApp - System user access token". Do not use the 24-hour temporary token from API Setup beyond your first five minutes of testing.', 'sample' => 'wa_token'],
                ['title' => 'Test WABA ID and test phone number ID', 'body' => 'App dashboard > WhatsApp > API Setup. Under "Send and receive messages" Step 1 you see the free test number. Directly beneath the From box are "Phone number ID" and "WhatsApp Business Account ID". Copy each (15 to 16 digits, no spaces) and save as "WhatsApp - Test phone number ID" and "WhatsApp - Test WABA ID". They are IDs, not passwords. The phone number ID is NOT the phone number itself.', 'sample' => 'wa_ids'],
                ['title' => 'Save them in Naara', 'body' => 'Admin > API Keys > group "Meta: WhatsApp, Instagram, Messenger (Naara Respond)". Paste each into its own box and press Save. Saved secrets show only as a masked preview afterwards. Then come back here: the "Where your credentials stand right now" table shows SET for each one.'],
            ],
            'mistakes' => ['Pasting the phone number where the Phone number ID belongs (the ID has no plus sign and is not your number).', 'Pasting the App ID where the App Secret belongs: the ID is digits only, the secret is 32 letters and numbers.', 'Using the 24-hour temporary token and wondering why messages stop tomorrow.', 'Creating the verify token in Naara but typing a different one into Meta.', 'Leaving the app in Development mode and expecting real customers to message the number: only added testers and admins can until the app is Live.'],
            'samples' => ['meta_app_id', 'meta_app_secret', 'meta_config_id', 'verify_token', 'wa_token', 'wa_ids'],
            'verify' => ['Admin > API Keys shows each Meta field with a masked preview.', 'The status table on this page shows SET for each value you saved.', 'php artisan naara:respond:credentials lists them as SET.'],
        ],

        'meta_app' => [
            'title' => '8. Meta developer app (the foundation for WhatsApp, Instagram and Messenger)',
            'minutes' => 30,
            'intro' => 'You already have an app called Naara (mode: In development) and a business portfolio called NAARA. This section finishes it. Do these in order. The ID values for your app are already filled in as defaults.',
            'needs' => ['A Facebook login that is an admin of the NAARA business portfolio', 'Your company details for verification', 'Access to your domain DNS (Cloudflare)'],
            'steps' => [
                ['title' => 'Open your app', 'body' => 'Go to developers.facebook.com > My Apps > Naara. The App ID at the top should be 2195469598059043 (this is already your default).'],
                ['title' => 'Copy the App Secret', 'body' => 'Left menu: App settings > Basic. Next to App secret press Show (Meta asks for your password), copy it, and put it in .env as OMNI_META_APP_SECRET. This proves webhooks really come from Meta. Never show it to anyone.', 'copy' => 'OMNI_META_APP_SECRET=', 'sample' => 'meta_app_secret'],
                ['title' => 'Fill in the basics Meta requires', 'body' => 'Same page: App icon, Privacy Policy URL (https://naara.app/legal/privacy), Terms URL (https://naara.app/legal/terms), App domains (naara.app) and a Category. Press Save changes. An app cannot go Live without these.'],
                ['title' => 'Add the messaging use cases', 'body' => 'In the dashboard choose Add use case (or Add product on older apps) and add: WhatsApp (Connect with customers through WhatsApp), Messenger and Instagram messaging. The Marketing API email you received is about ads and is not needed for Respond.'],
                ['title' => 'Link the app to the NAARA business portfolio', 'body' => 'App settings > Advanced (or the business section) and confirm Business: NAARA (ID 4491570984493160). If it is not linked, WhatsApp setup will not find your business.'],
                ['title' => 'Verify the domain naara.app', 'body' => 'Business settings > Brand safety > Domains > Add > naara.app. Choose DNS TXT record. In Cloudflare DNS add a TXT record with Name @ and the Value Meta shows (it starts with facebook-domain-verification=). Wait a few minutes then press Verify domain. Copy the value exactly: no quotes, no spaces.', 'sample' => 'domain_txt'],
                ['title' => 'Start business verification', 'body' => 'Business settings > Security centre > Start verification. Upload your company registration and proof of address and use the same legal name everywhere. This can take days; start it now. Until it passes, WhatsApp stays on test numbers and low limits.'],
                ['title' => 'Invent a webhook verify token', 'body' => 'Make up a long random text (at least 32 characters, letters and numbers). Put it in .env as OMNI_META_VERIFY_TOKEN. You will paste the same text into Meta in the next sections.', 'copy' => 'OMNI_META_VERIFY_TOKEN=', 'sample' => 'verify_token'],
            ],
            'mistakes' => ['Copying the App ID instead of the App Secret (the ID is public and about 16 digits; the secret is 32 letters and numbers).', 'Adding the TXT record with quotes or a trailing space.', 'Changing the app to Live before the privacy policy and icon are set.', 'Using a personal Facebook account that is not an admin of the business portfolio.', 'Forgetting to run php artisan optimize:clear after editing .env.'],
            'samples' => ['meta_app_id', 'meta_app_secret', 'verify_token', 'domain_txt'],
            'verify' => ['php artisan naara:respond:credentials shows Meta App Secret and Webhook verify token as SET.', 'Business settings > Domains shows naara.app as Verified.'],
        ],

        'whatsapp' => [
            'title' => '9. WhatsApp Business (Cloud API)',
            'minutes' => 30,
            'intro' => 'Two separate uses: (a) Naara\'s own WhatsApp support number, and (b) each business connecting its own number from Business > Channels. Start with Meta\'s free test number so nothing real is at risk.',
            'needs' => ['The Meta app from section 8', 'A phone able to receive a WhatsApp message (for the free test)'],
            'steps' => [
                ['title' => 'Open WhatsApp > API Setup', 'body' => 'In your app: WhatsApp > API Setup. Meta provides a free test number and a Temporary access token that lasts 24 hours.'],
                ['title' => 'Copy the two IDs', 'body' => 'On that page copy the Phone number ID (about 15 digits) and the WhatsApp Business Account ID. They are IDs, not passwords. People often copy the phone number itself by mistake: the Phone number ID is a long number with no plus sign.', 'sample' => 'wa_ids'],
                ['title' => 'Add your own phone as a test recipient', 'body' => 'In the To field choose Manage phone number list and add your phone. Meta sends it a code on WhatsApp. Only added numbers can receive messages until the app is Live.'],
                ['title' => 'Create a permanent token', 'body' => 'The 24-hour token is only for the first test. Business settings > Users > System users > Add (role Admin) > Add assets: assign your app and your WhatsApp account with Full control > Generate new token, choose the app, tick whatsapp_business_messaging and whatsapp_business_management. This token does not expire. Copy it once.', 'sample' => 'wa_token'],
                ['title' => 'Set the webhook (Naara\'s own support number)', 'body' => 'WhatsApp > Configuration > Webhook > Edit. Callback URL below; Verify token is the text you invented (OMNI_META_VERIFY_TOKEN, or WHATSAPP_VERIFY_TOKEN, they must match what the server has). Press Verify and save.', 'url' => 'whatsapp_platform'],
                ['title' => 'Subscribe to messages', 'body' => 'Under Webhook fields press Manage and Subscribe next to messages. Without this nothing arrives, even though verify succeeded.'],
                ['title' => 'Webhook for a business workspace', 'body' => 'When a business connects its own number in Business > Channels, the card shows its own Callback URL and Verify token. They paste those two values into their own Meta app in the same place (WhatsApp > Configuration), and subscribe to messages. Each connection has a different address: never reuse one.', 'url' => 'whatsapp_workspace'],
                ['title' => 'Send the first message', 'body' => 'Send Hello from your phone to the test number, then check Naara. To reply to someone first you need an approved message template; a free-form reply is only allowed within 24 hours of their last message.'],
                ['title' => 'Understand billing before going live', 'body' => 'Meta bills per message sent, by category (marketing, utility, authentication) and country, and customer-started service conversations are cheaper. Check Meta\'s current WhatsApp pricing page for your countries before you promise anything to customers.'],
            ],
            'mistakes' => ['Pasting the phone number where the Phone number ID belongs.', 'Using the 24-hour temporary token in production: it stops working and messages silently fail.', 'Verifying the webhook but forgetting to subscribe to the messages field.', 'A verify token that differs by one character between Meta and .env.', 'Using one callback URL for two connections.', 'Sending to a number that was not added as a test recipient while the app is in development.', 'Replying after 24 hours without a template.'],
            'samples' => ['wa_ids', 'wa_token', 'verify_token'],
            'verify' => ['Meta shows the webhook as Verified with messages subscribed.', 'A message from your test phone appears in Naara.', 'Business > Channels shows no error on the card.'],
        ],

        'instagram_messenger' => [
            'title' => '10. Instagram and Messenger',
            'minutes' => 30,
            'intro' => 'Both use the same Meta app. Each needs an official page or professional account the business controls.',
            'needs' => ['The Meta app from section 8', 'A Facebook Page (Messenger) and an Instagram Professional account linked to it (Instagram)'],
            'steps' => [
                ['title' => 'Make the Instagram account professional', 'body' => 'In Instagram: Settings > Account type and tools > Switch to professional account, then link it to a Facebook Page (Settings > Linked accounts or Page).'],
                ['title' => 'Allow access to messages', 'body' => 'Instagram: Settings > Messages and story replies > Message controls > Connected tools > Allow access to messages. If this is off, messages never reach any app.'],
                ['title' => 'Get the Instagram account ID', 'body' => 'In your Meta app use the Instagram use case setup to select the account; copy the Instagram account ID (a long number). It is not the @username.', 'sample' => 'ig_id'],
                ['title' => 'Create a token', 'body' => 'Generate a token for the Page / Instagram account with the messaging permissions (Instagram: instagram_manage_messages and instagram_basic; Messenger: pages_messaging and pages_manage_metadata). Use the same System user method as WhatsApp so the token does not expire.'],
                ['title' => 'Paste into Business > Channels', 'body' => 'Instagram card: Instagram account ID, Access token and App secret. Naara then shows a Callback URL and Verify token to paste back into Meta (Webhooks > Instagram, or Messenger > Settings), subscribed to messages.', 'url' => 'whatsapp_workspace'],
                ['title' => 'App Review for real customers', 'body' => 'While the app is in development only people with a role on the app can message it. To serve the public, submit the messaging permissions for App Review with a screen recording that shows a customer writing and your team answering. Do this after the test works.'],
            ],
            'mistakes' => ['Using the @username instead of the numeric account ID.', 'Leaving Allow access to messages off.', 'Expecting strangers to reach an app still in development mode.', 'Forgetting the 24-hour reply rule (Instagram and Messenger allow free-form replies only for 24 hours, with limited exceptions Meta documents as the human-agent tag).'],
            'samples' => ['ig_id', 'wa_token'],
            'verify' => ['A DM from a tester account arrives and your reply is delivered.'],
        ],

        'social_login' => [
            'title' => '11. Social login (Google, Facebook, X, Apple, Microsoft, Discord)',
            'minutes' => 20,
            'intro' => 'Social login uses its own credentials, not the WhatsApp ones, and each provider needs its own small setup. A sign-in button appears on Naara only once both the ID and the secret for that provider are saved, so nothing breaks if you skip one. All of them are saved in Admin > API Keys > "Social login". Facebook sign-in is optional and has a catch (see its step), so do Google first.',
            'needs' => ['Your live https address', 'An account with each provider you want to offer'],
            'steps' => [
                ['title' => 'Facebook login: optional, decide first', 'body' => 'Your Naara app is a business-type app using Facebook Login for Business. Meta built that product for business integrations (it signs people in with a Configuration, not with ordinary permissions), and Meta\'s own guidance is to use standard Facebook Login for plain customer sign-in. So "Continue with Facebook" for customers may need the standard Facebook Login product or a separate consumer app, and I could not confirm from here which your app allows. Recommendation: skip Facebook sign-in for launch (Google sign-in covers most people; the Facebook button simply stays hidden while its keys are empty). If you do want it later: in your app Use cases, look for "Authenticate and request data from users with Facebook Login"; if it is offered, add it, then set Valid OAuth Redirect URIs to {APP_URL}/auth/facebook/callback and save the App ID and App Secret in Admin > API Keys > Social login. If it is not offered on this app, create a separate Consumer app for sign-in and use its own ID and secret there. Do not put Facebook Login redirect addresses where the WhatsApp webhook address belongs.'],
                ['title' => 'Google login', 'body' => 'console.cloud.google.com > create or pick a project > APIs & Services > OAuth consent screen (fill app name, support email, authorised domain naara.app) > Credentials > Create credentials > OAuth client ID > Web application. Authorised redirect URI: {APP_URL}/auth/google/callback. Copy the Client ID (ends in .apps.googleusercontent.com) and the Client secret (starts GOCSPX-) into Admin > API Keys > Social login. Publish the consent screen to production or only test users can sign in.'],
                ['title' => 'X (Twitter), Microsoft, Discord, Apple', 'body' => 'Each follows the same pattern: create an app in that provider\'s developer portal, add the redirect URI {APP_URL}/auth/<provider>/callback (twitter, microsoft, discord, apple), and paste the Client ID and Client secret into the matching boxes in Admin > API Keys > Social login. Apple is the fussiest (a Services ID, a key and a team ID); do it last, and only if you need Sign in with Apple for iOS.'],
                ['title' => 'Test each one', 'body' => 'Open your login page in a private window. The button for each provider you configured appears; press it, approve, and you land in your account. A provider with a missing key simply shows no button.'],
            ],
            'mistakes' => ['Redirect URI that does not match exactly, including https, no trailing slash and the exact path.', 'Leaving the Meta app or Google consent screen in testing/Development mode.', 'Putting social login keys in the Meta WhatsApp boxes: they are separate groups in API Keys.', 'Using http:// (Google and Meta both refuse it on a live domain).'],
            'samples' => [],
            'verify' => ['Each configured provider shows its button on the login page.', 'Signing in with it lands you in the right account.'],
        ],

        'go_live' => [
            'title' => '12. Go live checklist',
            'minutes' => 15,
            'intro' => 'Turn things on in this order so a mistake never reaches a customer.',
            'needs' => ['Everything above that you plan to use'],
            'steps' => [
                ['title' => 'Admin > Business Suite settings', 'body' => 'Switch on Business Suite, Shared inbox, NaaraBrain assistant and Naara Respond inbox. Choose who may use the Suite (Naara Pro plans only) and grant features to each plan.'],
                ['title' => 'Check plan margins', 'body' => 'php artisan naara:respond:pricing --assume-granted, then set each plan\'s assistant allowance (Admin > Brand directory > plan capabilities).', 'copy' => 'php artisan naara:respond:pricing --assume-granted'],
                ['title' => 'Test with your own workspace', 'body' => 'Connect Telegram and a web chat, add three FAQs, set the assistant to Suggest, send yourself a question from your phone and press Use this reply.'],
                ['title' => 'Run the checks', 'body' => 'php artisan naara:verify omni and Admin > System > Verify. Everything red must be fixed; amber is explained in plain words.', 'copy' => 'php artisan naara:verify omni'],
                ['title' => 'Only then', 'body' => 'Move the assistant to Auto for one channel, and consider submitting Meta App Review.'],
            ],
            'mistakes' => ['Turning on Auto before reading what Suggest drafts for a week.', 'Granting the assistant to every plan without checking the allowance.', 'Skipping the verify commands.'],
            'samples' => [],
            'verify' => ['You can explain, for each channel, where its messages arrive and who answers them.'],
        ],
    ],

    // What each credential looks like. Every value is FAKE: it only shows length and shape. Never paste a real secret into this file.
    'samples' => [
        'telegram_token' => ['label' => 'Telegram bot token', 'fake' => '123456789:AAH-abcdefghijklmnopqrstuvwxyz01234', 'shape' => 'Digits, a colon, then about 35 letters, numbers, dashes or underscores. Total about 45 characters.', 'env' => 'Pasted in Business > Channels (not .env)'],
        'meta_app_id' => ['label' => 'Meta App ID', 'fake' => '2195469598059043', 'shape' => 'Only digits, 15 or 16 of them. Public, safe to show.', 'env' => 'OMNI_META_APP_ID (already the default)'],
        'meta_app_secret' => ['label' => 'Meta App Secret', 'fake' => 'a1b2c3d4e5f60718293a4b5c6d7e8f90', 'shape' => 'Exactly 32 characters, lowercase letters a to f and digits (hexadecimal). Secret.', 'env' => 'OMNI_META_APP_SECRET'],
        'verify_token' => ['label' => 'Webhook verify token', 'fake' => 'naara-verify-7Hk29Qm4xTb81LzPw05vNc3R', 'shape' => 'Any text you invent, 32 or more characters, letters and digits, no spaces. It must be identical in Meta and in .env.', 'env' => 'OMNI_META_VERIFY_TOKEN'],
        'meta_config_id' => ['label' => 'Embedded Signup Config ID', 'fake' => '1234567890123456', 'shape' => 'A long number (about 15 to 16 digits). Shown on the configuration you created, not the App ID.', 'env' => 'Admin > API Keys > Meta - Embedded Signup Config ID (or OMNI_META_CONFIG_ID)'],
        'wa_ids' => ['label' => 'WhatsApp Phone number ID and Business Account ID', 'fake' => '109876543210987  and  104938271650321', 'shape' => 'Two long numbers (15 to 16 digits), no plus sign, no spaces. They are IDs, not your phone number.', 'env' => 'Pasted in Business > Channels, or WHATSAPP_PHONE_NUMBER_ID / WHATSAPP_WABA_ID for Naara\'s own number'],
        'wa_token' => ['label' => 'Permanent access token', 'fake' => 'EAAJxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'shape' => 'Starts with EAA, then 150 to 250 letters and digits. Very long, no spaces or line breaks. Secret. The temporary token has the same look but stops after 24 hours.', 'env' => 'Pasted in Business > Channels, or WHATSAPP_ACCESS_TOKEN'],
        'ig_id' => ['label' => 'Instagram account ID', 'fake' => '17841400000000000', 'shape' => 'A 17-digit number that usually starts with 17841. Not the @username.', 'env' => 'Pasted in Business > Channels'],
        'domain_txt' => ['label' => 'Domain verification TXT record', 'fake' => 'facebook-domain-verification=8qqa9d8vh4ix5q3fuvbu6q5example', 'shape' => 'Starts with facebook-domain-verification= followed by about 30 letters and digits. Add it as a TXT record named @ in your DNS.', 'env' => 'Cloudflare DNS, not .env'],
        'anthropic_key' => ['label' => 'Anthropic API key', 'fake' => 'sk-ant-api03-AbCdEfGhIjKlMnOpQrStUvWxYz0123456789-example', 'shape' => 'Starts with sk-ant-, then a long mix of letters, digits, dashes and underscores. Secret.', 'env' => 'ANTHROPIC_API_KEY'],
        'webchat_snippet' => ['label' => 'Website chat snippet', 'fake' => '<script async src="https://naara.app/js/omni-webchat.js" data-site="3f2b8c1e-9a47-4d6e-b1c5-7e0a2d4f6b83"></script>', 'shape' => 'One script line; data-site is a UUID (8-4-4-4-12 characters). Paste before the closing body tag.', 'env' => 'Shown on the channel card'],
        'email_inbound' => ['label' => 'Inbound email address and signature', 'fake' => 'https://naara.app/webhooks/omni/email/3f2b8c1e-9a47-4d6e-b1c5-7e0a2d4f6b83   X-Naara-Signature: sha256=9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08', 'shape' => 'An https address ending in a UUID, and a signature header that is sha256= plus 64 hex characters.', 'env' => 'Shown on the channel card'],
    ],
];
