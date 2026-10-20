<?php

namespace App\Support\Business;

/**
 * The Business Suite guide directory (MASTER-ONLY): plain-language, step-by-step guides for people on a Naara Pro business workspace. Each guide is a list of typed blocks
 * rendered by resources/views/livewire/business-guides.blade.php, so adding or editing a guide never needs a view change. Block types:
 *   h (heading) · p (paragraph) · list (bullets) · steps ([title, body, ?code]) · note · warn · table ([headers, rows]) · check (checklist) · cta ([label, route])
 * `audience` is `everyone` or `admin` (workspace owners and admins connect channels and change settings; agents only use them). Nothing here is shown to a
 * person whose workspace cannot reach the page it describes: the page is behind the same Business Suite switch as the rest.
 */
class BusinessGuideLibrary
{
    public const TRACKS = [
        'start' => 'Get started',
        'channels' => 'Connect your channels',
        'daily' => 'Use it every day',
        'scale' => 'Grow with it',
        'help' => 'Get unstuck',
    ];

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [
            'start-here' => [
                'track' => 'start', 'title' => 'Start here: your first hour', 'icon' => 'rocket', 'time' => '10 min read', 'audience' => 'everyone',
                'summary' => 'The whole Business Suite on one page, and the order to set things up in so nothing is left half-done.',
                'blocks' => [
                    ['p' => 'The Business Suite turns one Naara account into a small contact centre: a shared inbox for WhatsApp, Telegram, Instagram and SMS, business phone numbers with menus and ring groups, optional call recording, an AI assistant for calls, and an API so your other tools can talk to it. Everything lives in a workspace that your team shares.'],
                    ['h' => 'The blueprint'],
                    ['table' => [['Layer', 'What it is', 'Who sets it up'], [
                        ['Workspace', 'The shared space for your business: team, numbers, channels, settings.', 'Owner'],
                        ['Team and roles', 'Owner, Admin, Agent, Viewer. Each sees and changes only what their role allows.', 'Owner, Admin'],
                        ['Channels', 'WhatsApp, Telegram, Instagram, SMS. Messages from all of them arrive in one inbox.', 'Owner, Admin'],
                        ['Numbers and call flows', 'A business phone number, a menu ("press 1 for sales"), ring groups, business hours.', 'Owner, Admin'],
                        ['Inbox', 'Where your team answers customers, with files, voice notes, GIFs and scheduled replies.', 'Everyone'],
                        ['Recording and intelligence', 'Optional call recording, transcripts and summaries, switched on deliberately.', 'Owner, Admin'],
                        ['API and webhooks', 'Connect Zapier, your CRM or your own code.', 'Owner, Admin'],
                    ]]],
                    ['h' => 'The order that works'],
                    ['steps' => [
                        ['Create your workspace', 'Open Business from the More menu and create a workspace. Use your company name: your team and customers will see it. Confirm the business email you are sent, so the workspace is verified.'],
                        ['Invite your team', 'Add each person with the right role. Start people as Agent and promote later; it is easier to give access than to take it back.'],
                        ['Connect one channel', 'Telegram takes about five minutes and costs nothing, so it is the best first connection. Then WhatsApp, then Instagram.'],
                        ['Send yourself a test message', 'From your own phone, write to the channel you connected. It should appear in the inbox within seconds. Reply from the inbox and check it reaches your phone.'],
                        ['Add a business number and a call flow', 'Only if you take phone calls. Build a simple menu and a ring group, then call the number yourself.'],
                        ['Decide about recording', 'Leave it off until you have read the recording guide. It is off by default for good reasons.'],
                    ]],
                    ['check' => ['Workspace created and business email confirmed', 'At least one teammate invited (so you are not the only person who can answer)', 'One channel connected and tested both ways', 'Customers know which channel to use (put it on your website and cards)', 'You have read the "Reply within the window" section of the WhatsApp guide']],
                    ['cta' => ['Open your workspace', 'business.workspace']],
                ],
            ],

            'workspace-team' => [
                'track' => 'start', 'title' => 'Your workspace, team and roles', 'icon' => 'users', 'time' => '6 min read', 'audience' => 'everyone',
                'summary' => 'Who can do what, how to invite people safely, and how billing works when several people share one workspace.',
                'blocks' => [
                    ['h' => 'The four roles'],
                    ['table' => [['Role', 'Can', 'Cannot'], [
                        ['Owner', 'Everything: team, numbers, channels, recording, API keys. Their wallet pays for usage.', 'Nothing is off limits, which is why there is only one.'],
                        ['Admin', 'Manage seats and numbers, connect channels, see reports, use the workspace.', 'Change who owns the workspace.'],
                        ['Agent', 'Use the inbox and calls, reply to customers.', 'Connect channels, change numbers, hear recordings.'],
                        ['Viewer', 'Look at the inbox and reports.', 'Reply, spend or change anything.'],
                    ]]],
                    ['note' => 'Give people the smallest role that lets them do their job. Contractors and new hires are Agents; your accountant is a Viewer.'],
                    ['h' => 'Inviting people'],
                    ['steps' => [
                        ['Open the workspace', 'Business > Overview > Invite a seat.'],
                        ['Enter their email', 'They do not need a Naara account yet. If they do not have one, the invitation waits for them and attaches the moment they sign up with that email.'],
                        ['Pick the role', 'You can change it later without re-inviting.'],
                        ['Tell them', 'Invitations are emailed, but a message from you ("check your inbox for the Naara invite") avoids a lot of lost mail.'],
                    ]],
                    ['h' => 'Who pays'],
                    ['p' => 'Usage (calls, recording minutes, AI minutes, SMS) is charged to the workspace owner\'s wallet, never to the people who use it. Top the wallet up before a busy day. A recording or an AI call only starts while the wallet holds enough to pay for it, so you never get a recording you cannot afford.'],
                    ['warn' => 'Removing someone stops their access immediately, including on their phone. Conversations they handled stay in the inbox.'],
                ],
            ],

            'telegram' => [
                'track' => 'channels', 'title' => 'Connect Telegram', 'icon' => 'send', 'time' => '5 min', 'audience' => 'admin',
                'summary' => 'The easiest channel: free, instant, no approval. Make a bot, paste its token, done.',
                'blocks' => [
                    ['p' => 'Telegram customers write to a bot that you own. Messages arrive in your shared inbox; replies go out as the bot. There is no 24-hour limit and no fee.'],
                    ['steps' => [
                        ['Open BotFather', 'In Telegram search for @BotFather (the one with the blue tick) and press Start.'],
                        ['Create the bot', 'Send /newbot. Choose a display name (your business name) and a username that ends in "bot", for example AcmeSupportBot.'],
                        ['Copy the token', 'BotFather replies with a long token that looks like 123456789:AAH.... Copy it. Treat it like a password: anyone with it can speak as your bot.'],
                        ['Paste it into Naara', 'Business > Channels > Telegram > Bot token > Connect Telegram. Naara checks the token and registers the connection for you; you do not set a webhook by hand.'],
                        ['Test it', 'Open your bot in Telegram, press Start, and write "hello". It should appear in the inbox. Reply from the inbox.'],
                        ['Polish the bot (optional)', 'In BotFather use /setuserpic for your logo, /setdescription for the text people see before they press Start, and /setabouttext for your profile line.'],
                    ]],
                    ['h' => 'What you can send'],
                    ['list' => ['Text with formatting (bold, italic, links)', 'Photos, videos, documents, voice notes, GIFs (sent as animations)', 'Locations, contacts and polls (polls are Telegram-only)']],
                    ['h' => 'Share your bot'],
                    ['p' => 'Your link is t.me/YourBotUsername. Put it on your website, email signature and in your Instagram bio. You can also make a QR code of it.'],
                    ['warn' => 'If you ever think the token leaked, open BotFather > /revoke, then paste the new token in Naara (disconnect and reconnect Telegram).'],
                ],
            ],

            'whatsapp' => [
                'track' => 'channels', 'title' => 'Connect WhatsApp Business', 'icon' => 'message-circle', 'time' => '30 to 45 min the first time', 'audience' => 'admin',
                'summary' => 'WhatsApp Cloud API in your own Meta app: where every value comes from, the webhook, the 24-hour rule and message templates.',
                'blocks' => [
                    ['p' => 'You connect your own WhatsApp Business number through Meta (the company behind WhatsApp). That keeps the number, the customer relationship and the Meta billing yours. Naara supplies the inbox, the team features and the sending. Plan for about half an hour the first time; most of it is Meta\'s forms.'],
                    ['note' => 'Fastest route: if your Naara shows "Continue with Meta" on Business > Channels, press it, sign in to Facebook, pick or create your WhatsApp Business account and number, and finish. Naara switches the number on and sets up the webhook for you, so you can skip Parts 2 and 3 below. The manual steps remain for people who bring their own Meta app.'],
                    ['h' => 'Before you start'],
                    ['check' => ['A Facebook account that can administer your business', 'A Meta Business portfolio (business.facebook.com). Create one if you do not have it', 'A phone number that is NOT already registered in the normal WhatsApp or WhatsApp Business app (or you are ready to move it; see the note below)', 'Your business details ready for Meta verification (legal name, address, website)']],
                    ['warn' => 'A number can live in the WhatsApp phone app OR in the Cloud API, not both. If your number is on the app today, either use a new number or accept that the app stops working for it. Many businesses buy a new number for the Cloud API.'],
                    ['h' => 'Part 1: make the Meta app'],
                    ['steps' => [
                        ['Create the app', 'Go to developers.facebook.com > My Apps > Create app. Choose the "Other"/"Business" type and attach it to your Meta Business portfolio.'],
                        ['Add WhatsApp', 'In the app dashboard find the WhatsApp product and press Set up. Meta gives you a free test number you can use to try everything below.'],
                        ['Open "API setup"', 'Left menu: WhatsApp > API setup. This page shows your Phone number ID. Copy it: you paste it into Naara as "Phone number ID".'],
                        ['Add your real number', 'On the same page press Add phone number. Enter the number, display name and category, then verify it with the code Meta sends (SMS or call).'],
                    ]],
                    ['h' => 'Part 2: a token that does not expire'],
                    ['p' => 'The temporary token on the API setup page lasts about 24 hours: good for a test, wrong for a business. Make a permanent one:'],
                    ['steps' => [
                        ['Create a system user', 'business.facebook.com > Business settings > Users > System users > Add. Name it "Naara inbox", role Admin.'],
                        ['Give it the app and the WhatsApp account', 'Select the system user > Add assets. Add your app (full control) and your WhatsApp account (full control).'],
                        ['Generate the token', 'Press Generate new token, pick your app, choose "never" for expiry, and tick whatsapp_business_messaging and whatsapp_business_management. Copy the token now: Meta shows it once. This is the "Access token" for Naara.'],
                        ['Find the app secret', 'In your app: App settings > Basic > App secret > Show. This is the "App secret" for Naara. It lets Naara prove that incoming messages really come from Meta.'],
                    ]],
                    ['h' => 'Part 3: connect in Naara'],
                    ['steps' => [
                        ['Paste the three values', 'Business > Channels > WhatsApp Business: Phone number ID, Access token, App secret > Connect WhatsApp. Naara checks them with Meta; a wrong value is rejected here with a plain message.'],
                        ['Copy the webhook details', 'After connecting, the "Connected" card shows a Callback URL and a Verify token. Keep this page open.'],
                        ['Paste them into Meta', 'In your Meta app: WhatsApp > Configuration > Webhook > Edit. Paste the Callback URL and Verify token, then Verify and save.'],
                        ['Subscribe to messages', 'Under Webhook fields press Manage and subscribe to "messages". Without this step nothing arrives.'],
                        ['Test both ways', 'Send a WhatsApp message from your phone to the business number. It should appear in the inbox. Reply from Naara and watch it arrive on your phone.'],
                    ]],
                    ['h' => 'The 24-hour rule (read this one)'],
                    ['p' => 'WhatsApp only lets you send free-form messages for 24 hours after the customer last wrote to you. After that you may only send a pre-approved message template. The inbox tells you when the window has closed. To keep conversations alive: answer quickly, and when you need to start a conversation, use a template.'],
                    ['h' => 'Message templates'],
                    ['list' => ['Write and manage them in Naara: Respond > Templates. Naara sends them to Meta for review and shows each one as Approved, Pending or Rejected with Meta\'s reason and quality rating. Paste your WhatsApp Business Account ID there once (it is on the same API setup page as the Phone number ID).', 'When a customer has been silent for 24 hours, the inbox shows an "approved template" picker instead of the reply box. Fill in the values and press Send template.', 'Approval usually takes minutes to a day.', 'Use the "Utility" category for order updates, appointments and receipts; "Marketing" is stricter and costs more; "Authentication" is for codes.', 'Keep them short, personal and useful. Templates that look like spam are rejected, and customers who report you lower your quality rating.']],
                    ['h' => 'Costs'],
                    ['p' => 'Meta bills you directly for WhatsApp conversations, per conversation category and country. Naara does not add a margin to Meta\'s charges. Check Meta\'s current price list for your country before you launch a campaign.'],
                    ['h' => 'What you can send from the inbox'],
                    ['list' => ['Text with *bold*, _italic_ and links', 'Photos, videos (MP4), documents, voice notes', 'GIFs (WhatsApp shows them as a looping video when the server can convert them, otherwise as a file)', 'Locations and contacts. Polls are not available on WhatsApp.']],
                    ['note' => 'Quality rating: Meta watches how customers react. Reply fast, do not message people who have not asked to hear from you, and always honour "STOP". Naara automatically stops messaging anyone who sends STOP.'],
                    ['cta' => ['Open channels', 'business.channels']],
                ],
            ],

            'website-chat' => [
                'track' => 'channels', 'title' => 'Add chat to your website (including WordPress)', 'icon' => 'message-circle', 'time' => '5 min', 'audience' => 'admin',
                'summary' => 'A chat bubble on your site that lands in the same inbox: paste one line, or install the WordPress plugin that is already set up for you.',
                'blocks' => [
                    ['p' => 'Visitors chat from any page. Their messages arrive in the Respond inbox next to WhatsApp, Instagram, Telegram, SMS and email, and your team (or the AI assistant) answers there. Nothing to pay Meta or anyone else for.'],
                    ['steps' => [
                        ['Create the chat', 'Business > Channels > Website chat. Add the address of every site that will show it, for example https://yourshop.com (no page path). Pick a greeting and a colour, then Create.'],
                        ['Option A: any website', 'Copy the one-line snippet shown on the card and paste it just before the closing body tag of your site.'],
                        ['Option B: WordPress', 'On the same card press "Download the WordPress plugin". It arrives already set up. In WordPress open Plugins > Add New > Upload Plugin, choose the zip, then Activate. Settings > Naara Chat lets you switch it off or hide it while you are logged in as an administrator.'],
                        ['Test it', 'Open your site in a private window, press the bubble and write "hello". It appears in your inbox within a second or two. Reply from Naara and watch it arrive.'],
                    ]],
                    ['warn' => 'The chat only works on websites listed as allowed on the card. If the bubble does not appear or cannot send, check that the exact site address (https, with or without www) is in the list.'],
                    ['cta' => ['Open channels', 'business.channels']],
                ],
            ],

            'instagram' => [
                'track' => 'channels', 'title' => 'Connect Instagram', 'icon' => 'camera', 'time' => '20 to 30 min', 'audience' => 'admin',
                'summary' => 'Answer Instagram direct messages from the shared inbox: account requirements, the Meta app, token, webhook and the rules to know.',
                'blocks' => [
                    ['h' => 'Before you start'],
                    ['check' => ['An Instagram Professional account (Business or Creator). In Instagram: Settings > Account type and tools > Switch to professional account', 'The Instagram account is connected to a Facebook Page (Settings > Accounts Center, or the Page\'s settings)', 'In Instagram: Settings > Messages and story replies > Message controls, allow access to messages from connected tools', 'A Meta app on your Meta Business portfolio. You can use the same app as your WhatsApp connection']],
                    ['steps' => [
                        ['Add Instagram to your Meta app', 'developers.facebook.com > your app > Add product > Instagram (messaging). Add your Instagram professional account to the app\'s roles or test users if you are still in development mode.'],
                        ['Find the Instagram account ID', 'In the Instagram product setup, the connected account is listed with its numeric ID. Copy it: this is the "Instagram account ID" for Naara.'],
                        ['Generate an access token', 'Generate a token for the connected account with the instagram_basic and instagram_manage_messages permissions (through the Page that is linked to the account). Use a system user token as in the WhatsApp guide so it does not expire.'],
                        ['Find the app secret', 'App settings > Basic > App secret > Show.'],
                        ['Connect in Naara', 'Business > Channels > Instagram: Instagram account ID, Access token, App secret > Connect Instagram.'],
                        ['Add the webhook', 'Copy the Callback URL and Verify token from the Connected card into your Meta app (Instagram > Webhooks), and subscribe to "messages".'],
                        ['Test', 'From another Instagram account send a DM to your business account. It should appear in the inbox. Reply from Naara.'],
                    ]],
                    ['warn' => 'Meta only lets live (non-test) people message you through the API after your app passes App Review for the messaging permissions. While the app is in development mode only people with a role on the app can write to you. Submit for review (with a short screen recording of the inbox) before you promote the account.'],
                    ['h' => 'The rules'],
                    ['list' => ['You can reply for 24 hours after the person\'s last message.', 'Instagram carries text, images, MP4 video and audio. It does not carry documents; Naara refuses a document before anything is sent and tells you.', 'Voice notes recorded in a browser need the media converter (ffmpeg) on your server; if it is missing the inbox says so in plain words. On a Naara-hosted workspace this is already set up.', 'Story replies and mentions arrive as messages.']],
                    ['note' => 'Instagram is the strictest of the three channels. If something does not arrive, check three things in order: the account is Professional, the webhook subscribes to messages, and the app is out of development mode.'],
                ],
            ],

            'sms-numbers' => [
                'track' => 'channels', 'title' => 'SMS and your business phone numbers', 'icon' => 'phone', 'time' => '8 min read', 'audience' => 'admin',
                'summary' => 'Give your business a number, assign it to the workspace, and decide how calls and texts reach your team.',
                'blocks' => [
                    ['steps' => [
                        ['Get a number', 'Numbers > choose a permanent number in the country your customers call from. Pick the type that fits (local for a local feel, toll-free where offered).'],
                        ['Assign it to the workspace', 'Switch to your business workspace first, then add the number. A number belongs to one workspace; its calls and texts are then shared with your team instead of one person.'],
                        ['Decide what happens to calls', 'With no call flow the number rings the way you set it before. With a call flow, callers hear your menu and are routed. See the call flows guide.'],
                        ['Texts', 'Business > Channels > "SMS on your workspace number" > Use for Respond. From then on texts to the number arrive in the Respond inbox next to your other channels, labelled SMS, and your team replies from it. Each reply is charged to the number owner\'s wallet at the normal SMS price, the same as sending from Messages, and a failed text is refunded automatically.'],
                    ]],
                    ['note' => 'Business hours, ring groups and menus all sit on top of a number. Get the number first, then build the flow.'],
                ],
            ],

            'inbox' => [
                'track' => 'daily', 'title' => 'Work the shared inbox', 'icon' => 'inbox', 'time' => '8 min read', 'audience' => 'everyone',
                'summary' => 'Replying well and fast: channels, files, voice notes, GIFs, snippets, scheduling and what each channel allows.',
                'blocks' => [
                    ['p' => 'The inbox shows one conversation per customer per channel. The coloured band at the top of a conversation tells you the channel: green for WhatsApp, blue for Telegram, sunset for Instagram.'],
                    ['h' => 'The composer'],
                    ['table' => [['Tool', 'Use it for'], [
                        ['Text styles', 'Bold, italic, lists and links. Naara converts them to what each channel understands.'],
                        ['Emoji and GIF', 'Warmth. The GIF panel has shelves; you can also make your own GIFs from a short video.'],
                        ['Attach', 'Photos, video, documents, audio files, location and contact. A voice note button records straight from the composer.'],
                        ['Hints and snippets', 'Saved replies: type the shortcut and send the whole answer. Write snippets for your ten most common questions.'],
                        ['Schedule', 'Write now, send later (for example 9:00 tomorrow). Cancel a scheduled message any time before it goes out.'],
                    ]]],
                    ['h' => 'What each channel allows'],
                    ['table' => [['', 'WhatsApp', 'Telegram', 'Instagram', 'SMS'], [
                        ['Text', 'Yes', 'Yes', 'Yes', 'Yes'],
                        ['Photos and video', 'Yes', 'Yes', 'Yes (MP4)', 'No'],
                        ['Documents', 'Yes', 'Yes', 'No', 'No'],
                        ['Voice notes', 'Yes', 'Yes', 'Yes', 'No'],
                        ['GIFs', 'As video or file', 'Animation', 'Image', 'No'],
                        ['Reply window', '24 hours', 'None', '24 hours', 'None'],
                    ]]],
                    ['p' => 'The composer hides what a channel cannot send, and if something still cannot go it is refused before sending with a plain sentence, so nothing is half-sent.'],
                    ['h' => 'Habits that win customers'],
                    ['list' => ['Answer the first message within five minutes in working hours. Speed matters more than polish.', 'Say who you are in the first line ("Hi, this is Amaka from Acme").', 'One question per message. Short beats long.', 'Close the loop: confirm the outcome, then thank them.', 'Use scheduled messages for reminders and follow-ups rather than leaving them to memory.']],
                    ['cta' => ['Open the inbox', 'numbers.messages']],
                ],
            ],

            'call-flows' => [
                'track' => 'daily', 'title' => 'Call flows: menus, ring groups and business hours', 'icon' => 'phone-forwarded', 'time' => '12 min read', 'audience' => 'admin',
                'summary' => 'Build "press 1 for sales" and route calls to the right people, with different behaviour in and out of hours.',
                'blocks' => [
                    ['h' => 'The building blocks'],
                    ['table' => [['Block', 'What it does'], [
                        ['Menu', 'Plays a greeting and lets the caller press a digit to choose.'],
                        ['Ring group', 'Rings several people. Simultaneous rings everyone at once; sequential tries them one after another.'],
                        ['Forward', 'Sends the call to one number.'],
                        ['Voicemail', 'Takes a message when nobody answers.'],
                        ['AI assistant', 'Answers and handles common requests, and can hand over to a person (where your plan includes it).'],
                        ['Business hours', 'A weekly schedule. Use it to send calls to your team in the day and to voicemail or the assistant at night.'],
                    ]]],
                    ['h' => 'Build your first flow'],
                    ['steps' => [
                        ['Create a ring group', 'Business > Call flows > Ring groups. Add the people (and, if they want, their own mobile numbers) who answer sales or support.'],
                        ['Set business hours', 'Pick your time zone and the days and hours you answer. Add holidays as exceptions.'],
                        ['Create the flow', 'Call flows > New flow. Choose the number it belongs to. Start with a Menu: "Press 1 for sales, 2 for support."'],
                        ['Connect each digit', 'Digit 1 goes to the Sales ring group; digit 2 to Support. For each, set what happens if nobody answers (voicemail).'],
                        ['Set the closed behaviour', 'Outside hours, send callers to voicemail or a message that says when you open.'],
                        ['Activate and call it', 'Switch the flow to Active, then call your number from your own phone and try every digit.'],
                    ]],
                    ['warn' => 'A number with no active flow behaves exactly as before. Pausing a flow is instant and safe; calls fall back to the number\'s normal behaviour.'],
                    ['h' => 'Tips'],
                    ['list' => ['Keep menus to four options or fewer. People hang up on long menus.', 'Record a clear greeting with your business name and the options in the first ten seconds.', 'Always give callers a way to reach a human or leave a message.', 'Ring in a small group (3 to 5). Large groups cause everybody to assume someone else will answer.']],
                ],
            ],

            'recording' => [
                'track' => 'daily', 'title' => 'Call recording and consent', 'icon' => 'mic', 'time' => '7 min read', 'audience' => 'admin',
                'summary' => 'Recording is off until you switch it on, and every recorded call is announced. What that means for you and your callers.',
                'blocks' => [
                    ['p' => 'Recording calls is regulated differently in different countries. Naara is built so that you cannot record by accident: it is off by default, every recorded call is announced, and the stricter of the two countries involved always wins.'],
                    ['h' => 'How consent works'],
                    ['list' => ['Callers hear a short notice before recording starts.', 'In stricter countries the caller must press 1 to agree. If they do not, the call carries on, unrecorded.', 'Consent evidence (who, when, which notice) is stored with the recording.']],
                    ['h' => 'Switching it on'],
                    ['steps' => [
                        ['Check with your own advisers', 'You are responsible for lawful recording in the places you operate. If in doubt, ask your lawyer.'],
                        ['Open the workspace recording settings', 'Business > Recordings. Turn recording on for the workspace, then for the numbers where you want it.'],
                        ['Choose retention', 'Recordings are deleted automatically after the number of days you choose (the default is 30). Shorter is safer.'],
                        ['Test it', 'Call your number from a second phone, press 1, talk for ten seconds, hang up, and listen in Business > Recordings.'],
                    ]],
                    ['h' => 'Who can listen'],
                    ['p' => 'Only the workspace owner and admins can play recordings, and every playback is logged. Transcripts and summaries are a separate opt-in because analysing a call is a different thing from recording it.'],
                    ['warn' => 'Never share recordings outside your workspace except where the law requires. A person can ask for their recording to be deleted; use the legal hold only when a dispute requires it.'],
                ],
            ],

            'ai-assistant' => [
                'track' => 'daily', 'title' => 'The AI phone assistant', 'icon' => 'sparkles', 'time' => '6 min read', 'audience' => 'admin',
                'summary' => 'Let an assistant answer calls, handle the common questions and hand over to your team when it should.',
                'blocks' => [
                    ['p' => 'The assistant is one block in your call flow. It greets callers in your business\'s voice, answers from the information you give it, can book a callback, and transfers to your ring group when the caller asks for a person or it is not sure.'],
                    ['steps' => [
                        ['Open the assistant page', 'Business > AI assistant. Write what the assistant should know: opening hours, prices, address, policies, common questions.'],
                        ['Set the hand-over', 'Choose the ring group that takes over. Callers can always ask for a person.'],
                        ['Add it to a flow', 'In the call flow builder add an AI assistant block, usually after hours or when everyone is busy.'],
                        ['Listen to real calls', 'Review the transcripts for the first week and tighten what it knows. Add answers to the questions it missed.'],
                    ]],
                    ['note' => 'AI calls are billed per minute from the owner\'s wallet. Keep a balance for it. Availability depends on your plan and your region.'],
                    ['warn' => 'The assistant is good at routine questions and weak at anything that needs judgement. Keep humans in the loop for complaints, refunds and anything legal or medical.'],
                ],
            ],

            'api-integrations' => [
                'track' => 'scale', 'title' => 'API keys, webhooks and Zapier', 'icon' => 'plug', 'time' => '10 min read', 'audience' => 'admin',
                'summary' => 'Send new conversations to your CRM, create tickets, or build automations without writing code.',
                'blocks' => [
                    ['p' => 'Each workspace can have its own API keys. A key only ever sees its own workspace and only the scopes you give it, so a leaked key cannot reach anything else.'],
                    ['h' => 'Scopes'],
                    ['table' => [['Scope', 'Lets the key'], [
                        ['contacts:read', 'List and read your contacts.'],
                        ['calls:read', 'List and read your call log.'],
                        ['messages:read', 'Read inbox messages.'],
                        ['webhook:subscribe', 'Register webhook addresses that Naara calls when something happens.'],
                    ]]],
                    ['h' => 'Webhook events'],
                    ['list' => ['message.received: a customer wrote to you.', 'call.completed: a call ended.', 'call.recording_ready: a recording finished processing.']],
                    ['steps' => [
                        ['Create a key', 'Developer portal > choose the workspace > create key > tick only the scopes you need. Copy it now; it is shown once.'],
                        ['Add a webhook', 'Give the https address that should receive events. Naara signs every call so you can prove it is genuine; check the signature before trusting the body.'],
                        ['Or use Zapier', 'Where the Naara app is enabled for your account, use it in Zapier: a "New message" trigger plus any of Zapier\'s thousands of actions (add a row to a sheet, create a card, send a Slack alert). Otherwise use a webhook with Zapier\'s "Catch Hook" trigger.'],
                    ]],
                    ['warn' => 'Webhook addresses must be public https addresses. Naara refuses addresses on private networks. If your receiver is down, Naara retries with a delay and, after repeated failures, pauses the webhook and tells you.'],
                ],
            ],

            'scale' => [
                'track' => 'scale', 'title' => 'Scale without breaking: the playbook', 'icon' => 'trending-up', 'time' => '12 min read', 'audience' => 'everyone',
                'summary' => 'What to change at 10, 50 and 500 conversations a day, so response times stay fast as you grow.',
                'blocks' => [
                    ['h' => 'Stage 1: you and one or two helpers (up to about 30 conversations a day)'],
                    ['list' => ['One channel first, then add. Do not open three channels you cannot answer.', 'Write your ten snippets.', 'Agree one rule: every message gets a first reply within 15 minutes in working hours.', 'Make yourself and one other person Admins so the business never stops when you are away.']],
                    ['h' => 'Stage 2: a small team (30 to 150 a day)'],
                    ['list' => ['Add a call flow with a menu and a ring group so calls reach the right person.', 'Use business hours: a clear "we open at 8" message beats silence.', 'Schedule follow-ups and reminders instead of relying on memory.', 'Use roles: agents reply; admins connect; one Viewer for the owner\'s dashboard.', 'Hold a ten-minute weekly review: slowest replies, most-asked question, one thing to fix.']],
                    ['h' => 'Stage 3: a real operation (150+ a day)'],
                    ['list' => ['Put the API to work: send every new conversation to your CRM, post urgent ones to a Slack channel.', 'Turn on call recording where you are allowed to, and use transcripts and summaries to coach.', 'Use the AI assistant for after-hours and routine questions so your people handle the conversations that need them.', 'Write a short internal guide: tone of voice, refund rules, escalation path. Share it with every new hire on day one.', 'Keep the wallet funded and review usage monthly.']],
                    ['h' => 'Numbers worth watching'],
                    ['table' => [['Measure', 'Healthy', 'If it slips'], [
                        ['First reply time', 'Under 5 minutes in hours', 'Add a person, or a snippet for the common question'],
                        ['Missed calls', 'Under 10%', 'Add people to the ring group, or send overflow to voicemail or the assistant'],
                        ['WhatsApp window misses', 'Almost none', 'Reply sooner, and use templates to restart conversations'],
                        ['Repeat questions', 'Falling', 'Answer it on your website and in a snippet'],
                    ]]],
                ],
            ],

            'troubleshooting' => [
                'track' => 'help', 'title' => 'Troubleshooting: when something does not work', 'icon' => 'life-buoy', 'time' => '5 min read', 'audience' => 'everyone',
                'summary' => 'The symptom you are seeing, why it happens, and the fix.',
                'blocks' => [
                    ['table' => [['You see', 'Why', 'Do this'], [
                        ['A customer\'s message does not appear', 'The webhook is not subscribed to messages, or the callback URL or verify token was mistyped.', 'Open Business > Channels, re-copy the Callback URL and Verify token into Meta, and subscribe to "messages". Send a new test.'],
                        ['"Connect" says the token is wrong', 'A temporary or wrongly scoped token, or the wrong ID.', 'Make a permanent system-user token with the messaging permissions and re-copy the Phone number ID or Instagram account ID.'],
                        ['A WhatsApp reply is refused', 'The 24-hour window has closed.', 'Send an approved template to restart the conversation.'],
                        ['A message was sent as a file, not inline', 'The channel cannot show that format inline (for example a GIF on WhatsApp).', 'This is expected; it still opens and animates. Send a short MP4 for an inline look.'],
                        ['Instagram refuses a document', 'Instagram does not carry documents.', 'Send a link or an image of the document instead.'],
                        ['A voice note is refused on Instagram', 'It needs the media converter on the server.', 'Ask your administrator to open Business Suite > Setup and health and install ffmpeg.'],
                        ['Scheduled message did not go', 'The channel rejected it at send time (for example the window closed).', 'The scheduled item shows the reason; send a template or a fresh message.'],
                        ['Calls do not reach the team', 'The flow is paused, or the ring group is empty or outside hours.', 'Check the flow is Active, the group has members, and the business hours match your time zone.'],
                        ['No recording appeared', 'Recording is off for the workspace or number, the caller did not press 1, or the wallet was too low.', 'Check the recording settings and the owner\'s wallet balance.'],
                        ['A teammate cannot see Channels', 'Only owners and admins can connect and change channels.', 'Change their role if they need it.'],
                    ]]],
                    ['note' => 'Still stuck? Open Support from the More menu and tell us the channel, what you did, and what you saw. A screenshot of the message helps.'],
                ],
            ],

            'takeaways' => [
                'track' => 'help', 'title' => 'The one-page cheat sheet', 'icon' => 'clipboard-check', 'time' => '2 min read', 'audience' => 'everyone',
                'summary' => 'Everything that matters on one page. Print it or pin it for your team.',
                'blocks' => [
                    ['h' => 'Setup, in order'],
                    ['check' => ['Workspace created, business email confirmed', 'Team invited with the smallest sensible roles', 'Telegram connected and tested (free and fast)', 'WhatsApp connected: permanent token, webhook subscribed to messages', 'Instagram connected: Professional account, app out of development mode', 'Business number added; call flow built, active and tested by calling it', 'Recording decision made on purpose (off unless advised)', 'Wallet funded for the month']],
                    ['h' => 'Golden rules'],
                    ['list' => ['Reply within five minutes in working hours.', 'WhatsApp and Instagram: 24 hours to answer freely; after that, templates (WhatsApp).', 'Never share tokens or app secrets. If one leaks, revoke it and reconnect.', 'Smallest role that works. Admins connect; agents reply.', 'Test every change by using it yourself as a customer.', 'Announce and obey recording rules. Off by default for a reason.', 'Write snippets for repeated answers, schedule follow-ups, review weekly.']],
                    ['h' => 'Where things live'],
                    ['table' => [['I want to...', 'Go to'], [
                        ['Answer customers', 'More > Messages (inbox)'],
                        ['Connect or pause a channel', 'Business > Channels'],
                        ['Build a phone menu', 'Business > Call flows'],
                        ['Listen to a call', 'Business > Recordings'],
                        ['Set up the AI assistant', 'Business > AI assistant'],
                        ['Add a teammate', 'Business > Overview > Invite a seat'],
                        ['Create an API key or webhook', 'Developer portal'],
                    ]]],
                ],
            ],
        ];
    }

    public static function find(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }
}
