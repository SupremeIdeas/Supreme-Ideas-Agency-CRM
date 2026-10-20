<?php

/**
 * Business Links policy defaults (Brand-Growth Phase 2, MASTER-ONLY). Everything here is a DEFAULT: the admin screen (Admin > Business links) stores overrides
 * as settings, so nothing needs a deploy. Lists are pinned data, never fetched at request time.
 */
return [
    // The Public Suffix List is the TLD source of truth: "any ICANN-delegated TLD, minus the deny-list, plus the explicit allow-list".
    // UNVERIFIED: whether Namecheap / Hostinger / Cloudflare support exactly this set (their public TLD pages differ at the edges). Record gaps in docs when found.
    'psl' => ['file' => 'resources/data/brand-links/public_suffix_list.dat', 'source_url' => 'https://publicsuffix.org/list/public_suffix_list.dat', 'verified_on' => '2026-10-12'],

    /** Abuse-prone or irrelevant TLDs refused outright (admin-editable). */
    'tld_deny' => ['zip', 'mov', 'tk', 'ml', 'ga', 'cf', 'gq', 'cam', 'click', 'country', 'download', 'gdn', 'icu', 'kim', 'loan', 'men', 'party', 'review', 'science', 'stream', 'top', 'trade', 'webcam', 'win', 'work', 'xyz', 'rest', 'bid', 'accountant', 'date', 'faith', 'racing', 'cricket'],
    /** Priority TLDs shown as "recommended" and never denied (admin-editable). The owner wrote ".buz": almost certainly ".biz". */
    'tld_allow' => ['com', 'com.ng', 'co', 'cc', 'net', 'org', 'biz', 'pro', 'brand', 'co.uk', 'us', 'ca', 'ng', 'africa', 'store', 'shop', 'app', 'io', 'me', 'info'],

    'shorteners' => ['bit.ly', 'tinyurl.com', 't.co', 'goo.gl', 'ow.ly', 'rb.gy', 'is.gd', 'cutt.ly', 'buff.ly', 'bl.ink', 'shorturl.at', 'tiny.cc', 'lnkd.in', 'rebrand.ly', 'v.gd', 'x.co', 'amzn.to', 'fb.me', 'wa.me', 'tr.im', 'soo.gd', 'clck.ru', 'mcaf.ee', 'youtu.be'],
    /** Link-in-bio aggregators: a page of other people's links, so not a destination of its own. */
    'aggregators' => ['linktr.ee', 'beacons.ai', 'bio.link', 'carrd.co', 'lnk.bio', 'campsite.bio', 'taplink.cc', 'allmylinks.com', 'solo.to', 'linkin.bio', 'milkshake.app'],
    /** Words that are not part of a brand's identity when comparing names. */
    'stopwords' => ['ltd', 'limited', 'inc', 'incorporated', 'llc', 'plc', 'co', 'company', 'corp', 'corporation', 'the', 'and', 'of', 'nig', 'nigeria', 'group', 'enterprises', 'enterprise', 'global'],
    /** Trust-bait words that make a subdomain look like a phishing page unless the registrable domain itself matches the brand. */
    'trust_bait' => ['login', 'signin', 'secure', 'security', 'verify', 'verification', 'account', 'accounts', 'wallet', 'bank', 'banking', 'support', 'update', 'free', 'bonus', 'giveaway', 'claim', 'reward', 'promo', 'paypal', 'confirm'],
    /** Well-known names a label may not imitate (admin-editable). */
    'protected_brands' => ['google', 'apple', 'microsoft', 'amazon', 'facebook', 'instagram', 'whatsapp', 'paypal', 'binance', 'coinbase', 'netflix', 'twitter', 'tiktok', 'youtube', 'visa', 'mastercard', 'stripe', 'paystack', 'flutterwave', 'mtn', 'airtel', 'glo', 'gtbank', 'zenithbank', 'accessbank', 'firstbank', 'uba', 'opay', 'palmpay', 'moniepoint', 'naarasim', 'naara'],
    /** Hosts where every customer gets a subdomain: judged on the customer's label. */
    'resemblance_threshold' => 40,       // below this: rejected (the owner may ask for a review)
    'review_band_upper' => 60,           // from the threshold up to here: pending an admin's approval
    'max_subdomain_levels' => 2,
    'max_hyphens_in_label' => 2,
    'max_digit_ratio' => 0.30,
    'links_per_brand' => 5,
    'label_max' => 24,

    'probe' => ['timeout' => 5, 'max_redirects' => 3, 'max_bytes' => 65536, 'user_agent' => 'NaaraLinkCheck/1.0 (+https://naarasim.com)'],
    'recheck_days' => 7,
    'safe_browsing' => ['endpoint' => 'https://safebrowsing.googleapis.com/v4/threatMatches:find'],   // optional; needs SAFE_BROWSING_API_KEY. UNVERIFIED: terms, quotas and availability. Read them before enabling.
];
