<?php

/** MASTER-ONLY notification events for the Naara Travel Handbook Store (the file name carries "handbook" so the fork guards refuse it). Same row shape as config/notification_events.php. */
return [
    'events' => [
        'handbook.refund_decided' => ['category' => 'services', 'channels' => ['in_app', 'email'], 'dedupe' => true, 'icon' => 'book-open', 'route' => 'handbooks.library', 'title' => 'Your handbook refund request', 'body' => 'Your request for {title} was {outcome}. {note}'],
        'handbook.purchased' => ['category' => 'services', 'channels' => ['in_app', 'email'], 'dedupe' => true, 'icon' => 'book-open', 'route' => 'handbooks.library', 'title' => 'Your handbook is ready', 'body' => '{title} is in your Library. Last verified {verified}.'],
        'handbook.upgraded' => ['category' => 'services', 'channels' => ['in_app', 'email'], 'dedupe' => true, 'icon' => 'download', 'route' => 'handbooks.library', 'title' => 'Download unlocked', 'body' => 'You can now download {title} as EPUB and PDF.'],
        'handbook.unlocked' => ['category' => 'services', 'channels' => ['in_app', 'push'], 'dedupe' => true, 'icon' => 'book-open', 'route' => 'handbooks.library', 'title' => 'You unlocked {title}', 'body' => 'Read it offline in your Library.'],
        'handbook.new_edition' => ['category' => 'services', 'channels' => ['in_app', 'email'], 'dedupe' => true, 'icon' => 'refresh', 'route' => 'handbooks.library', 'title' => 'A new edition of {title}', 'body' => 'Edition {edition} is out and already in your Library, free.'],
        'handbook.audiobook_purchased' => ['category' => 'services', 'channels' => ['in_app', 'email'], 'dedupe' => true, 'icon' => 'volume-2', 'route' => 'handbooks.library', 'title' => 'Your audiobook is ready', 'body' => 'The audiobook of {title} is in your Library.'],
        'handbook.pick_ready' => ['category' => 'services', 'channels' => ['in_app', 'push'], 'dedupe' => true, 'icon' => 'book-open', 'route' => 'handbooks.library', 'title' => 'Choose your free handbook', 'body' => 'Your eSIM includes one travel handbook. Pick the destination you need most.'],
        'handbook.slots_left' => ['category' => 'services', 'channels' => ['in_app'], 'dedupe' => true, 'icon' => 'book-open', 'route' => 'handbooks.store', 'title' => 'You still have free handbooks', 'body' => 'Naara Pro includes {n} free handbook unlocks. Pick a destination.'],
    ],
];
