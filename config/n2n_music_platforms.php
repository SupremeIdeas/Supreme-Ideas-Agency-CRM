<?php

/**
 * Music platforms a brand may link (N2N Batch 8, MASTER-ONLY). The URL host MUST equal one of `hosts` or be a subdomain of one of them
 * (`spotify.com.evil.tld` is neither). Link shorteners are not listed on purpose, so they are refused. The developer should re-confirm each
 * domain before launch: platforms change hosts.
 */
return [
    'spotify' => ['label' => 'Spotify', 'hosts' => ['open.spotify.com', 'spotify.com', 'spotify.link']],
    'apple_music' => ['label' => 'Apple Music', 'hosts' => ['music.apple.com', 'itunes.apple.com']],
    'amazon_music' => ['label' => 'Amazon Music', 'hosts' => ['music.amazon.com', 'amazon.com']],
    'youtube_music' => ['label' => 'YouTube Music', 'hosts' => ['music.youtube.com']],
    'audiomack' => ['label' => 'Audiomack', 'hosts' => ['audiomack.com']],
    'boomplay' => ['label' => 'Boomplay', 'hosts' => ['boomplay.com']],
    'deezer' => ['label' => 'Deezer', 'hosts' => ['deezer.com']],
    'tidal' => ['label' => 'Tidal', 'hosts' => ['tidal.com', 'listen.tidal.com']],
    'soundcloud' => ['label' => 'SoundCloud', 'hosts' => ['soundcloud.com']],
    'bandcamp' => ['label' => 'Bandcamp', 'hosts' => ['bandcamp.com']],
    'napster' => ['label' => 'Napster', 'hosts' => ['napster.com', 'play.napster.com']],
    'pandora' => ['label' => 'Pandora', 'hosts' => ['pandora.com']],
    'anghami' => ['label' => 'Anghami', 'hosts' => ['anghami.com', 'play.anghami.com']],
    'qobuz' => ['label' => 'Qobuz', 'hosts' => ['qobuz.com', 'open.qobuz.com', 'play.qobuz.com']],
    'mdundo' => ['label' => 'Mdundo', 'hosts' => ['mdundo.com']],
    'youtube' => ['label' => 'YouTube', 'hosts' => ['youtube.com', 'm.youtube.com', 'youtu.be']],
    'x' => ['label' => 'X', 'hosts' => ['x.com', 'twitter.com']],
    'facebook' => ['label' => 'Facebook', 'hosts' => ['facebook.com']],
    'tiktok' => ['label' => 'TikTok', 'hosts' => ['tiktok.com']],
    'instagram' => ['label' => 'Instagram', 'hosts' => ['instagram.com']],
    'iheartradio' => ['label' => 'iHeartRadio', 'hosts' => ['iheart.com']],
    'jiosaavn' => ['label' => 'JioSaavn', 'hosts' => ['jiosaavn.com']],
];
