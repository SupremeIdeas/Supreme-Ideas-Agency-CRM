<?php

/**
 * MASTER-ONLY guided tour: the Business Suite workspace. Lives in a file named for the Business Suite so the fork guards refuse it. Only things this
 * install really shows: every card is `optional`, because a plan or an admin switch may hide it (the tour never describes a feature the person cannot see).
 */
return [
    'tours' => [
        'business.main' => [
            'section' => 'Business Suite', 'title' => 'Business workspace', 'summary' => 'Lines, team, call flows and your shared inbox.', 'icon' => 'briefcase',
            'entry_route' => 'business.workspace', 'audience' => 'all', 'sort' => 40,
            'steps' => [
                ['anchor' => 'business.setup', 'title' => 'Setup guide', 'body' => 'Press this any time. It walks you through the profile, channels, your AI helper and team, one small step at a time, and remembers where you stopped.', 'requires' => 'optional', 'quick' => true],
                ['anchor' => 'business.create', 'title' => 'Create your workspace', 'body' => 'Name your business to open a workspace for your team, lines and inbox.', 'requires' => 'optional', 'quick' => true],
                ['anchor' => 'business.stats', 'title' => 'At a glance', 'body' => 'Unread messages, business lines and team seats in one row.', 'requires' => 'optional', 'quick' => true],
                ['anchor' => 'business.call-flows', 'title' => 'Call flows', 'body' => 'Build what callers hear and where each call goes.', 'requires' => 'optional', 'quick' => true],
                ['anchor' => 'business.assistant', 'title' => 'Assistant', 'body' => 'Let an AI voice answer calls for you.', 'requires' => 'optional'],
                ['anchor' => 'business.channels', 'title' => 'Channels', 'body' => 'Bring WhatsApp, email and other channels into one inbox.', 'requires' => 'optional'],
                ['anchor' => 'business.recordings', 'title' => 'Recordings', 'body' => 'Listen back to recorded calls.', 'requires' => 'optional'],
                ['anchor' => 'business.seats', 'title' => 'Your team', 'body' => 'Invite teammates and see who has a seat.', 'requires' => 'optional'],
            ],
        ],
        'business.respond' => [
            'section' => 'Business Suite', 'title' => 'Respond inbox', 'summary' => 'Answer every customer from one place.', 'icon' => 'message-circle',
            'entry_route' => 'business.respond', 'audience' => 'all', 'sort' => 41,
            'steps' => [
                ['anchor' => 'respond.filters', 'title' => 'Find what needs you', 'body' => 'Open means waiting for an answer. Waiting means you are checking something. Done is finished. Use Mine and Unassigned to split the work.', 'requires' => 'optional', 'quick' => true],
                ['anchor' => 'respond.list', 'title' => 'Your conversations', 'body' => 'One list for Telegram, web chat and email. A yellow Overdue tag means a customer has waited more than 15 minutes. Tick several to finish or take them in one go.', 'requires' => 'optional', 'quick' => true],
                ['anchor' => 'respond.composer', 'title' => 'Reply', 'body' => 'Type and press Enter. On WhatsApp-style channels you can only reply freely within 24 hours of their last message, and Respond tells you when that window has closed.', 'requires' => 'optional', 'quick' => true],
                ['anchor' => 'respond.notes', 'title' => 'Private notes and saved replies', 'body' => 'Leave a note only your team sees, or save a reply you send often and insert it with one tap.', 'requires' => 'optional'],
                ['anchor' => 'respond.consent', 'title' => 'Broadcast permission', 'body' => 'Switch this on only when the customer has agreed to hear from you. Broadcasts go to nobody else, and a customer who writes STOP is removed automatically.', 'requires' => 'optional'],
                ['anchor' => 'respond.automation', 'title' => 'Teach it, automate it', 'body' => 'Open Automation to teach Respond your business, let an assistant suggest or send answers, and run follow-ups for you.', 'requires' => 'optional', 'quick' => true],
            ],
        ],
        'business.automation' => [
            'section' => 'Business Suite', 'title' => 'Respond automation', 'summary' => 'Knowledge, AI assistant, flows and broadcasts.', 'icon' => 'sparkles',
            'entry_route' => 'business.respond.automation', 'audience' => 'all', 'sort' => 42,
            'steps' => [
                ['anchor' => 'automation.knowledge', 'title' => 'Teach it your business', 'body' => 'Add your FAQs, policies or a page from your website. The assistant answers only from this, so it never makes things up.', 'requires' => 'optional', 'quick' => true],
                ['anchor' => 'automation.tab-agent', 'title' => 'AI assistant', 'body' => 'Choose Off, Suggest (it drafts, you approve) or Auto (it answers by itself). Start with Suggest. It hands over to your team when it does not know or the customer asks for a person.', 'requires' => 'optional', 'quick' => true],
                ['anchor' => 'automation.tab-flows', 'title' => 'Flows', 'body' => 'A flow reacts to a trigger, such as a first message or a keyword, and runs steps for you. Add the starter flows, press Test, then turn one on.', 'requires' => 'optional', 'quick' => true],
                ['anchor' => 'automation.tab-broadcasts', 'title' => 'Broadcasts', 'body' => 'Send one message to people who agreed to hear from you. Preview who gets it first, and schedule it for later.', 'requires' => 'optional'],
                ['anchor' => 'automation.tab-integrations', 'title' => 'Webhooks', 'body' => 'Tell Zapier, Make or your own server when something happens. Every call is signed so you can trust it.', 'requires' => 'optional'],
                ['anchor' => 'automation.tab-insights', 'title' => 'Insights and health', 'body' => 'See how fast you answer, what the AI handled, and whether each channel is healthy.', 'requires' => 'optional', 'quick' => true],
            ],
        ],
    ],
];
