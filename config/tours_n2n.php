<?php

/**
 * MASTER-ONLY guided tour: Naara Chat. Lives in a file named for N2N so the fork guards refuse it. Steps that need an open conversation (composer, call,
 * profile) are `optional`: a person with no conversation open simply does not see them, and the closing step points at the Guided tours page.
 * Every anchor is a real `data-tour` on the chat page or in the shared composer (resources/js/composer/composer.js).
 */
return [
    'tours' => [
        'chat.gestures' => [
            'section' => 'Naara Chat', 'title' => 'Chat gestures', 'summary' => 'Swipe, double-tap and press and hold, and how to change them.', 'icon' => 'chat',
            'entry_route' => 'n2n.gestures', 'audience' => 'all', 'sort' => 31,
            'steps' => [
                ['anchor' => 'chat.gestures', 'title' => 'Make gestures yours', 'body' => 'Choose what a swipe or a double-tap does, how long a press takes, or switch gestures off. Try them on the sample message below.', 'quick' => true],
            ],
        ],
        'chat.main' => [
            'section' => 'Naara Chat', 'title' => 'Naara Chat', 'summary' => 'Message and call people on Naara.', 'icon' => 'chat',
            'entry_route' => 'n2n.chat', 'audience' => 'all', 'sort' => 30,
            'steps' => [
                ['anchor' => 'chat.list', 'title' => 'Your conversations', 'body' => 'Every chat is here, newest first. Tap one to open it.', 'quick' => true],
                ['anchor' => 'chat.filters', 'title' => 'Find a chat fast', 'body' => 'Sort and filter your chats, or tap Select to pin, mute or archive several at once.'],
                ['anchor' => 'chat.invite', 'title' => 'Bring someone in', 'body' => 'Share your invite link, or find people already on Naara by their Naara ID, phone or email.', 'quick' => true],
                ['anchor' => 'composer.input', 'title' => 'Write a message', 'body' => 'Type here. Press Enter to send; your message can be undone for a few seconds.', 'requires' => 'optional', 'quick' => true],
                ['anchor' => 'composer.text-style', 'title' => 'Text styles', 'body' => 'Tap to make text bold, italic and more.', 'requires' => 'optional'],
                ['anchor' => 'composer.emoji', 'title' => 'Emoji', 'body' => 'Add an emoji, or search for one.', 'requires' => 'optional'],
                ['anchor' => 'composer.attach', 'title' => 'Attach', 'body' => 'Share photos, videos, files, a contact, your location or a poll.', 'requires' => 'optional'],
                ['anchor' => 'composer.mic', 'title' => 'Voice note', 'body' => 'Record a voice message instead of typing.', 'requires' => 'optional'],
                ['anchor' => 'chat.call', 'title' => 'Call', 'body' => 'Call this person from inside Naara.', 'requires' => 'optional', 'quick' => true],
                ['anchor' => 'chat.profile', 'title' => 'Profile and privacy', 'body' => 'Tap their name or photo to see their profile and manage the chat.', 'requires' => 'optional'],
            ],
        ],
        'chat.people' => [
            'section' => 'Naara Chat', 'title' => 'People and brands', 'summary' => 'Find people, and connect with brands in one tap.', 'icon' => 'chat',
            'entry_route' => 'n2n.people', 'audience' => 'all', 'sort' => 32,
            'steps' => [
                ['anchor' => 'people.tabs', 'title' => 'People or brands', 'body' => 'Switch between the people you know and the brands you can connect with.', 'quick' => true],
                ['anchor' => 'people.add', 'title' => 'Add someone', 'body' => 'Type a Naara ID, username, phone or email. If they are not on Naara yet you can invite them with a link.', 'requires' => 'optional', 'quick' => true],
                ['anchor' => 'people.brand-search', 'title' => 'Find a brand', 'body' => 'Search by name or topic, or pick a category. This is the fastest way to meet the brands behind the music, food and travel you like.', 'before_click' => 'people.tab-brands', 'requires' => 'optional', 'quick' => true],
                ['anchor' => 'people.quick-connect', 'title' => 'Quick connect', 'body' => 'Popular brands you have not connected with yet. Tap Connect and you are in.', 'before_click' => 'people.tab-brands', 'requires' => 'optional', 'quick' => true],
                ['anchor' => 'people.brand-list', 'title' => 'Connect and message', 'body' => 'Connect to follow a brand, or Connect and message to say hello straight away. Follow their pages afterwards to earn NaaraCredits.', 'before_click' => 'people.tab-brands', 'requires' => 'optional'],
            ],
        ],
    ],
];
