<?php

/**
 * The composer's GIF & sticker library (shared by Naara chat, support chat and the Business inbox). `categories` is the set of shelves that SHIP with the platform
 * (their labels come from lang/*\/gifs.php); the live set of shelves is the `gif_categories` table, which an admin can add to, rename, reorder, hide and limit to
 * certain chats (Admin > GIF library). The shipped seed GIFs live in public/images/gifs/<category>/ and are registered by `php artisan gifs:seed` (also run by the
 * migration). Everything an admin adds later is stored on the public media disk and registered in `gif_library_items`.
 */
return [
    'categories' => ['stickers', 'funny', 'love', 'reactions', 'celebrate', 'thanks', 'greetings', 'business', 'motivation'],

    // The places a composer can sit, so an admin can choose which shelves each one offers. A composer says where it is with its `surface` prop.
    'surfaces' => [
        'naara_chat' => 'Naara chat (Naara to Naara)',
        'support' => 'Support chat (NaaraCare)',
        'inbox' => 'Business shared inbox',
        'messages' => 'Messages and Send message',
        'staff' => 'Staff replies (admin)',
    ],

    'max_categories' => 40,

    // Upload / import limits (admin).
    'max_bytes' => 3 * 1024 * 1024,
    'max_dimension' => 800,
    'max_per_category' => 400,
];
