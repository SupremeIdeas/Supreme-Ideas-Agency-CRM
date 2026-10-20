<?php

namespace App\Services\BrandLinks;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\RequestOptions;

/**
 * The only thing allowed to fetch a brand-supplied URL (Brand-Growth Phase 2, MASTER-ONLY). Hardened against SSRF:
 *   - https on port 443 only (a plain http link is retried as https; anything else is refused);
 *   - the host is resolved by us, EVERY address must be public (no private, loopback, link-local, CGNAT, multicast or reserved space, IPv4-mapped IPv6 unwrapped);
 *   - the connection is pinned to the address we checked (CURLOPT_RESOLVE), so a DNS answer cannot change between the check and the request (rebinding);
 *   - redirects are followed by hand, at most N, and every hop goes through the same checks;
 *   - 5 s timeout, no cookies, no credentials, a byte cap, a HEAD first (a GET only when HEAD is refused).
 * `resolver` and `transport` are injectable so the rules can be tested without a network.
 */
final class SafeFetcher implements LinkProbe
{
    /** @var callable(string): list<string> */
    private $resolver;

    /** @var callable(string, string, string): array{0: int, 1: ?string} (method, url, ip) => [status, location] */
    private $transport;

    public function __construct(?callable $resolver = null, ?callable $transport = null)
    {
        $this->resolver = $resolver ?? [self::class, 'dnsLookup'];
        $this->transport = $transport ?? [self::class, 'httpRequest'];
    }

    public function probe(string $url): ProbeResult
    {
        $max = (int) config('brand_links.probe.max_redirects', 3);
        $upgraded = false;
        $current = $url;
        for ($hop = 0; $hop <= $max; $hop++) {
            $parts = parse_url($current);
            if (! is_array($parts) || ! isset($parts['host'])) {
                return new ProbeResult(false, null, $current, $hop, 'scheme');
            }
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            if ($scheme === 'http' && $hop === 0) {
                $current = 'https://'.substr($current, 7);   // upgrade; if https does not answer the link is refused below
                $upgraded = true;
                $parts = parse_url($current);
            } elseif ($scheme !== 'https') {
                return new ProbeResult(false, null, $current, $hop, 'scheme', $upgraded);
            }
            if (isset($parts['port']) && (int) $parts['port'] !== 443) {
                return new ProbeResult(false, null, $current, $hop, 'port', $upgraded);
            }
            if (isset($parts['user']) || isset($parts['pass'])) {
                return new ProbeResult(false, null, $current, $hop, 'scheme', $upgraded);
            }
            $host = strtolower((string) $parts['host']);

            if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
                return new ProbeResult(false, null, $current, $hop, 'blocked_ip', $upgraded);   // a literal IP is never a brand link
            }
            $ips = ($this->resolver)($host);
            if ($ips === []) {
                return new ProbeResult(false, null, $current, $hop, 'dns', $upgraded);
            }
            foreach ($ips as $ip) {
                if (! self::isPublicIp($ip)) {
                    return new ProbeResult(false, null, $current, $hop, 'blocked_ip', $upgraded);
                }
            }
            $ip = $ips[0];

            try {
                [$status, $location] = ($this->transport)('HEAD', $current, $ip);
                if (in_array($status, [403, 405, 501], true)) {
                    [$status, $location] = ($this->transport)('GET', $current, $ip);
                }
            } catch (ConnectException $e) {
                $msg = strtolower($e->getMessage());

                return new ProbeResult(false, null, $current, $hop, str_contains($msg, 'ssl') || str_contains($msg, 'certificate') ? 'tls' : (str_contains($msg, 'timed out') ? 'timeout' : 'dns'), $upgraded);
            } catch (\Throwable) {
                return new ProbeResult(false, null, $current, $hop, 'http', $upgraded);
            }

            if ($status >= 300 && $status < 400 && $location) {
                if ($hop === $max) {
                    return new ProbeResult(false, $status, $current, $hop, 'too_many_redirects', $upgraded);
                }
                $current = self::absolute($current, $location);

                continue;
            }

            return new ProbeResult($status >= 200 && $status < 400, $status, $current, $hop, $status >= 400 ? 'http' : null, $upgraded);
        }

        return new ProbeResult(false, null, $current, $max, 'too_many_redirects', $upgraded);
    }

    /** Public means: routable on the open internet. Everything else (private, loopback, link-local, CGNAT, multicast, reserved, ULA, mapped) is refused. */
    public static function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        if (str_contains($ip, ':')) {
            $packed = inet_pton($ip);
            if ($packed === false) {
                return false;
            }
            // ::ffff:a.b.c.d (IPv4-mapped): judge the embedded IPv4 address
            if (substr($packed, 0, 12) === "\0\0\0\0\0\0\0\0\0\0\xff\xff") {
                return self::isPublicIp(inet_ntop(substr($packed, 12)) ?: '0.0.0.0');
            }
            $first = ord($packed[0]);
            if ($packed === str_repeat("\0", 16) || $packed === str_repeat("\0", 15)."\1") {
                return false;   // :: and ::1
            }
            if (($first & 0xFE) === 0xFC || $first === 0xFF || ($first === 0xFE && (ord($packed[1]) & 0xC0) === 0x80)) {
                return false;   // fc00::/7 unique local, ff00::/8 multicast, fe80::/10 link-local
            }
            if (substr($packed, 0, 4) === "\x20\x01\x0d\xb8" || substr($packed, 0, 2) === "\x20\x02" /* 6to4 */ || substr($packed, 0, 4) === "\x00\x64\xff\x9b") {
                return false;   // documentation, 6to4 and NAT64 ranges can embed anything
            }

            return true;
        }
        $n = ip2long($ip);
        if ($n === false) {
            return false;
        }
        foreach ([['0.0.0.0', 8], ['10.0.0.0', 8], ['100.64.0.0', 10], ['127.0.0.0', 8], ['169.254.0.0', 16], ['172.16.0.0', 12], ['192.0.0.0', 24], ['192.0.2.0', 24], ['192.168.0.0', 16], ['198.18.0.0', 15], ['198.51.100.0', 24], ['203.0.113.0', 24], ['224.0.0.0', 4], ['240.0.0.0', 4]] as [$base, $bits]) {
            $mask = $bits === 0 ? 0 : (~0 << (32 - $bits)) & 0xFFFFFFFF;
            if (($n & $mask) === (ip2long($base) & $mask)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    public static function dnsLookup(string $host): array
    {
        $ips = [];
        foreach (@dns_get_record($host, DNS_A + DNS_AAAA) ?: [] as $r) {
            if (! empty($r['ip'])) {
                $ips[] = $r['ip'];
            }
            if (! empty($r['ipv6'])) {
                $ips[] = $r['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }

    /** @return array{0: int, 1: ?string} */
    public static function httpRequest(string $method, string $url, string $ip): array
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $client = new Client([
            RequestOptions::ALLOW_REDIRECTS => false, RequestOptions::TIMEOUT => (float) config('brand_links.probe.timeout', 5), RequestOptions::CONNECT_TIMEOUT => (float) config('brand_links.probe.timeout', 5),
            RequestOptions::HTTP_ERRORS => false, RequestOptions::VERIFY => true, RequestOptions::COOKIES => false,
            RequestOptions::HEADERS => ['User-Agent' => (string) config('brand_links.probe.user_agent'), 'Accept' => 'text/html,*/*;q=0.5', 'Range' => 'bytes=0-'.(int) config('brand_links.probe.max_bytes', 65536)],
            // pin the connection to the address we validated: DNS cannot change its answer between our check and the request
            'curl' => [CURLOPT_RESOLVE => ["$host:443:".(str_contains($ip, ':') ? "[$ip]" : $ip)]],
        ]);
        $res = $client->request($method, $url, [RequestOptions::STREAM => $method === 'GET']);
        if ($method === 'GET') {
            $res->getBody()->read((int) config('brand_links.probe.max_bytes', 65536));   // read at most the cap, then drop the connection
            $res->getBody()->close();
        }

        return [$res->getStatusCode(), $res->hasHeader('Location') ? $res->getHeaderLine('Location') : null];
    }

    private static function absolute(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }
        $p = parse_url($base);
        $origin = ($p['scheme'] ?? 'https').'://'.($p['host'] ?? '');
        if (str_starts_with($location, '//')) {
            return ($p['scheme'] ?? 'https').':'.$location;
        }
        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }
        $dir = rtrim(dirname($p['path'] ?? '/'), '/');

        return $origin.$dir.'/'.$location;
    }
}
