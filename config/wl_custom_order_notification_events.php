<?php

/** MASTER-ONLY notification events for White Label custom orders (the file name carries "wl_custom_order" so the fork guards refuse it). Same row shape as config/notification_events.php. */
return [
    'events' => [
        'wl_custom_order.quoted' => ['category' => 'services', 'channels' => ['in_app', 'email'], 'dedupe' => true, 'icon' => 'file-text', 'route' => 'wl-custom-orders', 'title' => 'Your custom quote is ready', 'body' => 'We priced your custom White Label ({reference}) at ${total}. It is valid until {until}.'],
        'wl_custom_order.message' => ['category' => 'services', 'channels' => ['in_app', 'email'], 'dedupe' => false, 'icon' => 'message-circle', 'route' => 'wl-custom-orders', 'title' => 'New message on {reference}', 'body' => '{preview}'],
        'wl_custom_order.paid' => ['category' => 'services', 'channels' => ['in_app', 'email'], 'dedupe' => true, 'icon' => 'check', 'route' => 'wl-custom-orders', 'title' => 'Payment received for {reference}', 'body' => 'Thank you. We are starting your custom build and will message you here.'],
        'wl_custom_order.declined' => ['category' => 'services', 'channels' => ['in_app', 'email'], 'dedupe' => true, 'icon' => 'x', 'route' => 'wl-custom-orders', 'title' => 'Update on {reference}', 'body' => 'We cannot take this request as described. {reason}'],
    ],
];
