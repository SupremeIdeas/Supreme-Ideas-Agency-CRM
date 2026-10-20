<?php

namespace App\Services\Omni\Kb;

use Illuminate\Support\Facades\Http;

/**
 * Fetches a public web page for the knowledge base (MASTER-ONLY) without ever reaching the platform's own network (SSRF guard, blueprint section 14).
 * Only http/https on the default ports; the host must resolve, and EVERY resolved address must be public (no loopback, private, link-local, metadata or
 * reserved ranges, v4 or v6). Redirects are followed by hand, at most 3, and each hop is checked again. The body is capped and must be text.
 */
class SafeFetcher
{
    public const MAX_BYTES = 1_000_000;

    public const MAX_REDIRECTS = 3;

    /** @throws \RuntimeException with a message that is safe to show */
    public function get(string $url): string
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $this->assertPublic($url);
            $r = Http::timeout(8)->withOptions(['allow_redirects' => false, 'stream' => false])->withHeaders(['User-Agent' => 'NaaraRespondBot/1.0', 'Accept' => 'text/html,text/plain'])->get($url);
            if ($r->redirect()) {
                $next = (string) $r->header('Location');
                if ($next === '') {
                    throw new \RuntimeException('The page redirected without saying where.');
                }
                $url = $this->absolute($url, $next);

                continue;
            }
            if (! $r->successful()) {
                throw new \RuntimeException('The page answered with an error ('.$r->status().').');
            }
            $type = strtolower((string) $r->header('Content-Type'));
            if ($type !== '' && ! str_contains($type, 'text/') && ! str_contains($type, 'xml')) {
                throw new \RuntimeException('That address is not a text page.');
            }
            $body = $r->body();
            if (strlen($body) > self::MAX_BYTES) {
                throw new \RuntimeException('That page is too large to read (over 1 MB).');
            }

            return $body;
        }
        throw new \RuntimeException('The page redirected too many times.');
    }

    /** @throws \RuntimeException */
    public function assertPublic(string $url): void
    {
        $p = parse_url($url);
        if (! is_array($p) || ! isset($p['scheme'], $p['host']) || ! in_array(strtolower($p['scheme']), ['http', 'https'], true) || isset($p['user']) || isset($p['pass'])) {
            throw new \RuntimeException('Use a normal web address that starts with https://.');
        }
        if (isset($p['port']) && ! in_array((int) $p['port'], [80, 443], true)) {
            throw new \RuntimeException('Only the standard web ports are allowed.');
        }
        $host = trim(strtolower($p['host']), '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);
        if ($ips === []) {
            throw new \RuntimeException('That address could not be found.');
        }
        foreach ($ips as $ip) {
            if (! $this->isPublic($ip)) {
                throw new \RuntimeException('That address points at a private network and cannot be read.');
            }
        }
    }

    /** @return list<string> */
    protected function resolve(string $host): array
    {
        $ips = [];
        foreach ((array) @dns_get_record($host, DNS_A | DNS_AAAA) as $rec) {
            if (isset($rec['ip'])) {
                $ips[] = $rec['ip'];
            }
            if (isset($rec['ipv6'])) {
                $ips[] = $rec['ipv6'];
            }
        }

        return $ips;
    }

    public function isPublic(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        // An IPv4 hidden inside an IPv6 literal (::ffff:127.0.0.1) is judged as the IPv4 it really is.
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $m)) {
            return $this->isPublic($m[1]);
        }
        // 169.254.0.0/16 (cloud metadata) and CGNAT 100.64.0.0/10 are not covered by the PHP flags on every build.
        if (str_contains($ip, '.')) {
            $n = ip2long($ip);

            return ! ($n >= ip2long('169.254.0.0') && $n <= ip2long('169.254.255.255')) && ! ($n >= ip2long('100.64.0.0') && $n <= ip2long('100.127.255.255'));
        }

        return ! str_starts_with(strtolower($ip), 'fe80') && ! str_starts_with(strtolower($ip), 'fc') && ! str_starts_with(strtolower($ip), 'fd');
    }

    private function absolute(string $base, string $next): string
    {
        if (preg_match('#^https?://#i', $next)) {
            return $next;
        }
        $p = parse_url($base);
        $root = $p['scheme'].'://'.$p['host'];
        if (str_starts_with($next, '/')) {
            return $root.$next;
        }

        return $root.rtrim(dirname($p['path'] ?? '/'), '/').'/'.$next;
    }
}
