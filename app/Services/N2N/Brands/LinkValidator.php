<?php

namespace App\Services\N2N\Brands;

/**
 * Host-allow-list validation for music / social / album links (N2N Batch 8, MASTER-ONLY). The URL must be https, carry no credentials and a
 * host that EQUALS an allowed host or is a true subdomain of one (so `spotify.com.evil.tld` and `evilspotify.com` are both refused). Link
 * shorteners are not on any list, so they never pass. Returns a normalised URL: userinfo/fragment stripped, host lower-cased.
 */
class LinkValidator
{
    /** @return array<string, array{label: string, hosts: list<string>}> */
    public static function platforms(): array
    {
        return (array) config('n2n_music_platforms');
    }

    /** @throws BrandLimitException */
    public static function validate(string $platform, string $url): string
    {
        $cfg = self::platforms()[$platform] ?? null;
        if ($cfg === null) {
            throw new BrandLimitException('platform', 'That platform is not supported.');
        }
        $url = trim($url);
        if (strlen($url) > 300 || preg_match('/[\x00-\x20\x7f<>"\'\\\\]/', $url) === 1) {
            throw new BrandLimitException('url', 'That link is not valid.');
        }
        $p = parse_url($url);
        if ($p === false || strtolower((string) ($p['scheme'] ?? '')) !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass'])) {
            throw new BrandLimitException('url', 'Use a full https:// link without a username or password.');
        }
        $host = strtolower(rtrim((string) $p['host'], '.'));
        if (! self::hostAllowed($host, $cfg['hosts']) || isset($p['port']) && (int) $p['port'] !== 443) {
            throw new BrandLimitException('host', 'That link does not belong to '.$cfg['label'].'.');
        }

        return 'https://'.$host.($p['path'] ?? '/').(isset($p['query']) ? '?'.$p['query'] : '');
    }

    /**
     * A distributor smart link (Ditto, DistroKid, TuneCore, ...): https, no credentials, and a host that is the chosen distributor's own or one of the
     * neutral smart-link services. Anything else is refused, so a look-alike or an unrelated site never becomes the "listen everywhere" button.
     *
     * @throws BrandLimitException
     */
    public static function validateSmartLink(?string $distributor, string $url): string
    {
        $url = trim($url);
        if (strlen($url) > 300 || preg_match('/[\x00-\x20\x7f<>"\'\\\\]/', $url) === 1) {
            throw new BrandLimitException('url', 'That smart link is not valid.');
        }
        $p = parse_url($url);
        if ($p === false || strtolower((string) ($p['scheme'] ?? '')) !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass']) || (isset($p['port']) && (int) $p['port'] !== 443)) {
            throw new BrandLimitException('url', 'Use a full https:// smart link without a username or password.');
        }
        $host = strtolower(rtrim((string) $p['host'], '.'));
        $own = (array) ((config('n2n_music_distributors.distributors')[$distributor] ?? [])['hosts'] ?? []);
        $allowed = array_merge($own, (array) config('n2n_music_distributors.smart_link_hosts'));
        if (! self::hostAllowed($host, $allowed)) {
            $name = $distributor && isset(config('n2n_music_distributors.distributors')[$distributor]) && $own !== [] ? config('n2n_music_distributors.distributors')[$distributor]['label'] : 'a distributor';
            throw new BrandLimitException('host', 'That link is not a smart link from '.$name.' or a supported smart-link service (Songwhip, Linkfire, Feature.fm and similar).');
        }

        return 'https://'.$host.($p['path'] ?? '/').(isset($p['query']) ? '?'.$p['query'] : '');
    }

    /** @param list<string> $allowed */
    public static function hostAllowed(string $host, array $allowed): bool
    {
        foreach ($allowed as $a) {
            if ($host === $a || str_ends_with($host, '.'.$a)) {
                return true;
            }
        }

        return false;
    }
}
