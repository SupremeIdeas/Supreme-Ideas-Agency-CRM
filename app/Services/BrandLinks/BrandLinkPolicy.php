<?php

namespace App\Services\BrandLinks;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * One class decides whether a brand's custom link is acceptable (Brand-Growth Phase 2, MASTER-ONLY). Every failure returns a specific human message;
 * every check is recorded so the admin tester can show the whole story. The pipeline, in order: parse and normalise, Public Suffix split and TLD policy,
 * homograph defence, shorteners and aggregators, resemblance to the brand name, subdomain sanity, protected-brand lookalikes, label rules, then a hardened
 * live look (SafeFetcher): reachable, TLS valid, and the FINAL host after redirects must still be the declared domain.
 */
class BrandLinkPolicy
{
    private const TRACKING = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid', 'igshid', 'mc_cid', 'mc_eid', 'ref', 'ref_src'];

    public function __construct(private readonly LinkProbe $probe) {}

    public function evaluate(string $brandName, string $label, string $url): LinkVerdict
    {
        $v = new LinkVerdict('rejected');
        $fail = function (string $key, string $message, bool $stop = true) use ($v) {
            $v->checks[] = ['key' => $key, 'ok' => false, 'message' => $message];
            $v->reason ??= $message;

            return $stop;
        };
        $pass = function (string $key, string $message) use ($v) {
            $v->checks[] = ['key' => $key, 'ok' => true, 'message' => $message];
        };

        // 1) parse and normalise ---------------------------------------------------------------------------------------------------------------------
        $parsed = $this->normalise($url);
        if (is_string($parsed)) {
            $fail('url', $parsed);

            return $v;
        }
        [$normal, $host] = $parsed;
        $v->url = $normal;
        $v->host = $host;
        $pass('url', 'A clean https address.');

        // 2) Public Suffix split + TLD policy --------------------------------------------------------------------------------------------------------
        $asciiHost = $this->toAscii($host);
        $parts = $asciiHost !== null ? PublicSuffix::parse($asciiHost) : null;
        if ($parts === null) {
            $fail('domain', 'That is not a web address we can use. Enter the full address of your own website, for example https://yourbrand.com.');

            return $v;
        }
        $v->registrable = $parts->registrable;
        $v->tld = $parts->tld;
        $v->subdomain = $parts->subdomain;
        if (! $parts->icann && ! $parts->privateSuffix) {
            $fail('tld', 'We do not recognise the ending ".'.$parts->tld.'". Use a standard domain such as .com, .com.ng, .co, .net or .org.');

            return $v;
        }
        $allowed = BrandLinkSettings::list('tld_allow');
        $denied = BrandLinkSettings::list('tld_deny');
        $suffix = $parts->suffix;
        $isAllowed = in_array($suffix, $allowed, true) || in_array($parts->tld, $allowed, true);
        if (! $isAllowed && (in_array($parts->tld, $denied, true) || in_array($suffix, $denied, true))) {
            $fail('tld', 'Links ending in ".'.$parts->tld.'" are not accepted because that ending is often used for scams. Use a standard domain such as .com or .com.ng.');

            return $v;
        }
        $pass('tld', $isAllowed ? 'A recommended ending (.'.$suffix.').' : 'An accepted ending (.'.$suffix.').');

        // 3) IDN / homograph defence -----------------------------------------------------------------------------------------------------------------
        if ($problem = $this->homograph($host, $asciiHost)) {
            $fail('homograph', $problem);

            return $v;
        }

        // 4) shorteners and link-in-bio pages --------------------------------------------------------------------------------------------------------
        foreach (BrandLinkSettings::list('shorteners') as $short) {
            if ($parts->registrable === $short || $parts->host === $short) {
                $fail('shortener', 'Shortened links hide where they lead, so they are not allowed. Paste the full address of your website.');

                return $v;
            }
        }
        foreach (BrandLinkSettings::list('aggregators') as $agg) {
            if ($parts->registrable === $agg || $parts->host === $agg) {
                $fail('aggregator', 'Link-in-bio pages are a list of other links, not a website of your own. Link to your own site instead.');

                return $v;
            }
        }
        $pass('shortener', 'Not a shortener or link-in-bio page.');

        // 5) resemblance to the brand name -----------------------------------------------------------------------------------------------------------
        $stop = BrandLinkSettings::list('stopwords');
        $threshold = BrandLinkSettings::int('resemblance_threshold', 40);
        $reviewUpper = BrandLinkSettings::int('review_band_upper', 60);
        // A shared host (blogspot.com, github.io) is judged on the customer's label; otherwise on the registrable label, helped by subdomain words.
        $score = Resemblance::score($brandName, $parts->label, $stop);
        foreach ($parts->subdomainLabels() as $sl) {
            $score = max($score, Resemblance::score($brandName, $sl, $stop));
        }
        $v->score = $score;
        $matches = $score >= $reviewUpper;

        // 6) subdomain sanity ------------------------------------------------------------------------------------------------------------------------
        $subLabels = $parts->subdomainLabels();
        if (count($subLabels) > BrandLinkSettings::int('max_subdomain_levels', 2)) {
            $fail('subdomain', 'That address has too many parts before the domain. Use a simple address such as portfolio.yourbrand.com.');

            return $v;
        }
        $maxHy = BrandLinkSettings::int('max_hyphens_in_label', 2);
        foreach ([...$subLabels, $parts->label] as $l) {
            if (substr_count($l, '-') > $maxHy) {
                $fail('subdomain', 'Addresses with long chains of hyphens look like scams, so they are not accepted.');

                return $v;
            }
            if (strlen($l) >= 6 && preg_match_all('/\d/', $l) / strlen($l) > BrandLinkSettings::float('max_digit_ratio', 0.30)) {
                $fail('subdomain', 'Addresses made mostly of numbers are not accepted. Use a domain that carries your brand name.');

                return $v;
            }
            if ($this->looksRandom($l)) {
                $fail('subdomain', 'That address looks randomly generated. Use a domain that carries your brand name.');

                return $v;
            }
        }
        if (! $matches) {
            foreach ($subLabels as $l) {
                foreach (BrandLinkSettings::list('trust_bait') as $bait) {
                    if ($l === $bait || str_contains($l, $bait)) {
                        $fail('subdomain', 'Addresses that start with words like "'.$bait.'" are only accepted on a domain that clearly carries your brand name.');

                        return $v;
                    }
                }
            }
        }
        $pass('subdomain', 'The address parts look normal.');

        // 7) protected-brand lookalikes --------------------------------------------------------------------------------------------------------------
        $ownTokens = Resemblance::tokens($brandName, $stop);
        $own = implode('', $ownTokens);
        foreach (BrandLinkSettings::list('protected_brands') as $pb) {
            if ($pb === '' || str_contains($own, $pb)) {
                continue;   // it is the brand's own name
            }
            foreach ([...$subLabels, $parts->label] as $l) {
                $tokens = preg_split('/-+/', $l) ?: [];
                if ((strlen($pb) >= 4 && str_contains(str_replace('-', '', $l), $pb)) || in_array($pb, $tokens, true)) {
                    $fail('lookalike', 'That address uses the name "'.$pb.'", which belongs to a well-known company. Use an address that carries your own brand name.');

                    return $v;
                }
            }
        }
        $pass('lookalike', 'Does not imitate a well-known name.');

        // 8) label rules -----------------------------------------------------------------------------------------------------------------------------
        $label = trim($label);
        if ($label === '' || mb_strlen($label) > BrandLinkSettings::int('label_max', 24)) {
            $fail('label', 'Give the link a short name of up to '.BrandLinkSettings::int('label_max', 24).' characters, such as "Our portfolio".');

            return $v;
        }
        if (preg_match('/\bhttps?\b|\bwww\b|\.[a-z]{2,}(\/|\b)|@/i', $label) === 1) {
            $fail('label', 'The link name should describe the link, not look like another address. Use something like "Our portfolio".');

            return $v;
        }
        foreach (BrandLinkSettings::list('protected_brands') as $pb) {
            if (strlen($pb) >= 4 && str_contains(Resemblance::flat($label), $pb) && ! str_contains($own, $pb)) {
                $fail('label', 'The link name cannot use the name "'.$pb.'", which belongs to another company.');

                return $v;
            }
        }
        $pass('label', 'A clear link name. Visitors always see the real address next to it.');

        // 9) resemblance verdict (after the cheap checks, before the network) ------------------------------------------------------------------------
        if ($score < $threshold) {
            $fail('resemblance', 'This address does not look like your brand name. Use your own domain, or ask us to review it if it really is yours.', false);
            $v->canRequestReview = true;
        } else {
            $pass('resemblance', "The address matches your brand name ($score%).");
        }

        // 10) a hardened live look -------------------------------------------------------------------------------------------------------------------
        $res = $this->probe->probe($normal);
        $v->httpStatus = $res->status;
        $v->finalHost = $res->finalHost();
        if (! $res->reachable) {
            $v->canRetry = in_array($res->error, ['dns', 'timeout', 'http', 'tls'], true);
            $fail('reachable', $this->probeMessage($res->error, $res->status), false);
        } else {
            $pass('reachable', 'The address answers'.($res->upgradedFromHttp ? ' (we use the secure https version).' : '.'));
            $finalParts = $v->finalHost ? PublicSuffix::parse((string) $this->toAscii($v->finalHost)) : null;
            $same = $finalParts !== null && $finalParts->registrable === $parts->registrable;
            if (! $same) {
                $fail('redirect', 'That address sends visitors to a different website ('.($v->finalHost ?? 'unknown').'). Link straight to your own site.', false);
            } else {
                $pass('redirect', 'It stays on your own domain.');
            }
        }

        // 11) optional reputation lookup (only when the operator supplied a key) ---------------------------------------------------------------------
        $v->safeBrowsing = $this->safeBrowsing($normal);
        if ($v->safeBrowsing === 'flagged') {
            $fail('reputation', 'This address is flagged as unsafe by Google Safe Browsing.', false);
        }

        // verdict -----------------------------------------------------------------------------------------------------------------------------------
        $failed = array_filter($v->checks, fn ($c) => ! $c['ok']);
        $v->status = $failed !== [] ? 'rejected' : ($score < $reviewUpper ? 'pending' : 'approved');
        if ($v->status === 'pending') {
            $v->reason = 'The address only partly matches your brand name, so a person will check it before it appears.';
            $v->canRequestReview = false;
        }

        return $v;
    }

    /** @return array{0: string, 1: string}|string [normalised url, host] or the owner-facing reason it cannot be used */
    private function normalise(string $url): array|string
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 500) {
            return 'Enter the full address of your website, for example https://yourbrand.com.';
        }
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url) === 1 && preg_match('#^https?://#i', $url) !== 1) {
            return 'Only web addresses starting with https:// are allowed.';
        }
        if (preg_match('#^https?://#i', $url) !== 1) {
            $url = 'https://'.$url;   // "yourbrand.com" is fine: we add https
        }
        $p = parse_url($url);
        if (! is_array($p) || empty($p['host'])) {
            return 'That does not look like a web address.';
        }
        if (isset($p['user']) || isset($p['pass'])) {
            return 'Addresses with a username or password in them are not allowed.';
        }
        if (isset($p['port']) && (int) $p['port'] !== 443) {
            return 'Addresses with a custom port are not allowed.';
        }
        $host = strtolower(rtrim((string) $p['host'], '.'));
        if (filter_var($host, FILTER_VALIDATE_IP) !== false || preg_match('/^\[.*\]$/', $host) === 1) {
            return 'Use a domain name, not a numeric address.';
        }
        if ($host === 'localhost' || preg_match('/\.(local|localhost|internal|lan|home|corp|test|invalid|example)$/', $host) === 1) {
            return 'That address is not on the public internet.';
        }
        parse_str((string) ($p['query'] ?? ''), $q);
        $q = array_diff_key($q, array_flip(self::TRACKING));
        $path = $p['path'] ?? '';
        $normal = 'https://'.$host.($path === '/' ? '' : $path).($q ? '?'.http_build_query($q) : '');

        return [$normal, $host];
    }

    private function toAscii(string $host): ?string
    {
        if (preg_match('/[^\x00-\x7F]/', $host) !== 1) {
            return $host;
        }
        $a = function_exists('idn_to_ascii') ? idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) : false;

        return is_string($a) ? $a : null;
    }

    /** Mixed scripts in one label (Latin plus Cyrillic, say) or a punycode label whose decoded text is not single-script are lookalike attacks. */
    private function homograph(string $host, ?string $ascii): ?string
    {
        $unicode = $host;
        if ($ascii !== null && str_contains($ascii, 'xn--') && function_exists('idn_to_utf8')) {
            $unicode = idn_to_utf8($ascii, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $host;
        }
        if (preg_match('/[^\x00-\x7F]/', $unicode) !== 1) {
            return null;
        }
        foreach (explode('.', $unicode) as $label) {
            $scripts = 0;
            foreach (['Latin', 'Cyrillic', 'Greek', 'Han', 'Arabic', 'Hebrew', 'Devanagari', 'Thai', 'Hangul', 'Hiragana', 'Katakana'] as $s) {
                if (preg_match('/\p{'.$s.'}/u', $label) === 1) {
                    $scripts++;
                }
            }
            if ($scripts > 1) {
                return 'That address mixes alphabets in one word, a trick used to imitate other websites. Use a plain address.';
            }
        }

        return null;   // a single-script internationalised name (all Cyrillic, all Han...) is a legitimate domain; it still has to pass every other check
    }

    private function looksRandom(string $label): bool
    {
        $l = preg_replace('/[^a-z]/', '', $label) ?? '';
        if (strlen($l) < 9) {
            return false;
        }
        $vowels = preg_match_all('/[aeiou]/', $l);

        return ($vowels / strlen($l)) < 0.18 || preg_match('/[bcdfghjklmnpqrstvwxz]{6,}/', $l) === 1;
    }

    private function probeMessage(?string $error, ?int $status): string
    {
        return match ($error) {
            'dns' => 'We could not find that website. Check the spelling, or try again once it is live.',
            'tls' => 'That website does not have a valid secure (https) certificate yet.',
            'timeout' => 'That website took too long to answer. Try again in a moment.',
            'blocked_ip', 'scheme', 'port' => 'That address cannot be used.',
            'too_many_redirects' => 'That address redirects too many times.',
            'http' => 'That website answered with an error'.($status ? " ($status)" : '').'. It needs to open normally.',
            default => 'We could not reach that website.',
        };
    }

    private function safeBrowsing(string $url): ?string
    {
        $key = (string) config('services.safebrowsing.key', '');
        if ($key === '') {
            return 'skipped';
        }
        try {
            $r = Http::timeout(5)->post((string) config('brand_links.safe_browsing.endpoint').'?key='.$key, [
                'client' => ['clientId' => 'naarasim', 'clientVersion' => '1.0'],
                'threatInfo' => ['threatTypes' => ['MALWARE', 'SOCIAL_ENGINEERING', 'UNWANTED_SOFTWARE'], 'platformTypes' => ['ANY_PLATFORM'], 'threatEntryTypes' => ['URL'], 'threatEntries' => [['url' => $url]]],
            ]);

            return $r->successful() ? (empty($r->json('matches')) ? 'clean' : 'flagged') : 'skipped';
        } catch (\Throwable) {
            return 'skipped';   // optional signal: an outage must never block or approve a link
        }
    }
}
