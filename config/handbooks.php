<?php

/* Naara Travel Handbook Store (MASTER-ONLY). Admin can change the per-title prices; the rules below are the product rules from the blueprint. */
return [
    'upgrade_fraction' => 0.5,           // download upgrade = 50% of list price, rounded UP to the cent
    'pro_slots' => 3,                    // unlock slots per active Naara Pro term
    'revalidate_days' => 30,             // offline copies must be re-checked online at least this often
    'max_epub_mb' => 80,
    'max_pdf_mb' => 120,
    'max_cover_mb' => 8,
    'cover_min_width' => 800,            // store art is shown at card and book-page sizes; 896x1200 (3:4) is the house artwork size
    'cover_min_height' => 1100,
    'cover_ratios' => [2 / 3, 3 / 4],    // accepted portrait shapes (width / height), each within 4 percent
    'refund_window_days' => 7,           // a buyer may ask for a refund within this many days of paying; a person decides
    'anomaly_ips_per_day' => 3,          // watch: one buyer's downloads from this many different networks in 24 hours
    'anomaly_downloads_per_day' => 12,   // watch: this many downloads by one buyer in 24 hours
    'download_per_hour' => 6,            // per person
    'url_ttl_minutes' => 10,             // signed file links live this long
    'imprint' => ['Naara.app', 'Naara Connect Technologies Limited'],
    'moods' => ['Quick-start', 'Deep dive', 'Toolkit', 'Inspire', 'Companion'],
    'disclaimer' => 'These handbooks are practical guidance, not legal, immigration or medical advice. Rules change: always check the official source for your situation. Each book shows the date it was last verified.',
];
