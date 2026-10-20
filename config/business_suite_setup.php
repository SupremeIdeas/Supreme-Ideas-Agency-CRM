<?php

/**
 * Setup Wizard guides (NaaraBusiness Suite, MASTER-ONLY). One entry per step: what the step enables and, for steps that need something from a provider,
 * the "Where do I get this?" instructions. Kept in a file of its own so a guide can be reworded when a provider moves its dashboard, without touching code.
 * `link` is the provider's official page; `where` says which menu to open; `steps` are the numbered instructions.
 */
return [
    'profile' => [
        'title' => 'Business profile',
        'enables' => 'Your workspace name and business email. Customers see the name; the email receives workspace notices and builds trust.',
    ],
    'channels' => [
        'title' => 'Connect a channel',
        'enables' => 'Brings WhatsApp, Telegram or Instagram messages into one shared inbox so your team can answer from one place.',
    ],
    'respond' => [
        'title' => 'Teach and automate (Respond)',
        'enables' => 'Teach Naara Respond your business once, then let it suggest or send answers, welcome new customers and follow up for you. Start in Suggest mode: you approve every reply.',
        'steps' => [
            'Open Respond automation and add your FAQs, policies or a page from your website in the Knowledge tab.',
            'In AI assistant, pick Suggest. The assistant drafts a reply and you press Use this reply.',
            'In Flows, press Add starter flows, Test one, then turn it on.',
            'When you trust the answers, switch the assistant to Auto. It always hands over to a person when it does not know.',
        ],
    ],
    'telegram' => [
        'title' => 'Telegram bot',
        'link' => 'https://t.me/BotFather',
        'where' => 'In Telegram, open the chat with BotFather.',
        'steps' => [
            'Open Telegram and search for BotFather (the official bot with the blue tick), or tap the link.',
            'Send /newbot and follow the questions: a display name, then a username that ends in "bot".',
            'BotFather replies with a long token that looks like 123456789:ABC... Copy the whole token.',
            'Paste it below and press Test and connect. We check it with Telegram and register the address that receives your messages.',
        ],
    ],
    'whatsapp' => [
        'title' => 'WhatsApp Business (Cloud API)',
        'link' => 'https://developers.facebook.com/apps/',
        'where' => 'Meta for Developers > your app > WhatsApp > API Setup.',
        'steps' => [
            'Open Meta for Developers and create (or open) an app of type Business with the WhatsApp product added.',
            'Go to WhatsApp > API Setup. Copy the Phone number ID shown under your sending number.',
            'Create a permanent access token for a System User in Meta Business Settings (the temporary token expires in a day).',
            'Open App settings > Basic and copy the App secret.',
            'Paste the three values below and press Test and connect. We then show the callback address and verify token to paste back into Meta (WhatsApp > Configuration).',
        ],
    ],
    'instagram' => [
        'title' => 'Instagram (optional)',
        'link' => 'https://developers.facebook.com/apps/',
        'where' => 'Meta for Developers > your app > Instagram > API setup.',
        'steps' => [
            'Your Instagram account must be a Professional (Business or Creator) account linked to a Facebook Page.',
            'In your Meta app add the Instagram product and note the Instagram account ID.',
            'Generate an access token with messaging permission and copy the App secret from App settings > Basic.',
            'Paste the three values below and press Test and connect.',
        ],
    ],
    'team' => [
        'title' => 'Team',
        'enables' => 'Invite colleagues as admin, agent or viewer so replies, calls and settings are shared safely. You can skip this and invite later.',
    ],
    'call_flows' => [
        'title' => 'Call flows',
        'enables' => 'Menus, ring groups, business hours and voicemail on your business numbers.',
        'steps' => ['Open Call flows and pick one of your business numbers.', 'Build the menu: greeting, options and where each option rings.', 'Save, then call the number to hear it.'],
    ],
    'recording' => [
        'title' => 'Call recording',
        'enables' => 'Record calls with a clear notice for every caller, then review and delete them under your own retention rules.',
        'steps' => ['Open Recordings and switch recording on for the numbers you want.', 'Choose the retention period and the caller notice.'],
    ],
    'ai_voice' => [
        'title' => 'AI voice assistant',
        'enables' => 'An assistant that answers your number, takes callback requests and hands over to a person.',
        'steps' => ['Open the Assistant page and give it a name, a greeting and a language.', 'Choose the business hours and who it hands over to.', 'Switch it on, then call your number to test it.'],
    ],
    'integrations' => [
        'title' => 'Integrations and API',
        'enables' => 'A workspace API key and signed webhooks, so Zapier and your own tools can connect.',
        'steps' => ['Open Integrations and create an API key.', 'Add a webhook address if a tool should be told about new events.'],
    ],
];
