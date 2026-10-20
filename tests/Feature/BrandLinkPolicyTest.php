<?php

namespace Tests\Feature;

use App\Services\BrandLinks\BrandLinkPolicy;
use App\Services\BrandLinks\LinkProbe;
use App\Services\BrandLinks\ProbeResult;
use App\Services\BrandLinks\PublicSuffix;
use App\Services\BrandLinks\Resemblance;
use App\Services\BrandLinks\SafeFetcher;
use Tests\TestCase;

/** Business Links policy (Brand-Growth Phase 2, MASTER-ONLY): the fixture table, the Public Suffix split, resemblance scoring and the SSRF rules. */
class BrandLinkPolicyTest extends TestCase
{
    /** A probe that answers from a table instead of the network: host => [reachable, final url|null, error]. */
    private function policy(array $answers = []): BrandLinkPolicy
    {
        return new BrandLinkPolicy(new class($answers) implements LinkProbe {
            public function __construct(private array $a) {}

            public function probe(string $url): ProbeResult
            {
                $host = parse_url($url, PHP_URL_HOST);
                [$ok, $final, $err] = $this->a[$host] ?? [true, $url, null];

                return new ProbeResult($ok, $ok ? 200 : null, $final ?? $url, 0, $err);
            }
        });
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string}> name => [brand, label, url, expected status, expected check that fails ("" = none)] */
    public static function fixtures(): array
    {
        $b = 'Supreme Ideas Agency';

        return [
            // ---- good ----
            'exact name .com' => [$b, 'Our site', 'https://supremeideasagency.com', 'approved', ''],
            'name with hyphens' => [$b, 'Our site', 'https://supreme-ideas-agency.com', 'approved', ''],
            'shorter form inside' => [$b, 'Portfolio', 'https://supremeideas.com', 'approved', ''],
            'multi-label suffix .com.ng' => ['Ada Bakes Ltd', 'Shop', 'https://adabakes.com.ng', 'approved', ''],
            'multi-label suffix .co.uk' => ['Ada Bakes Ltd', 'Shop', 'https://adabakes.co.uk', 'approved', ''],
            'priority tld .co' => ['Ada Bakes', 'Shop', 'https://adabakes.co', 'approved', ''],
            'priority tld .biz' => ['Ada Bakes', 'Shop', 'https://adabakes.biz', 'approved', ''],
            'priority tld .pro' => ['Ada Bakes', 'Shop', 'https://adabakes.pro', 'approved', ''],
            'priority tld .us' => ['Ada Bakes', 'Shop', 'https://adabakes.us', 'approved', ''],
            'priority tld .ca' => ['Ada Bakes', 'Shop', 'https://adabakes.ca', 'approved', ''],
            'priority tld .org' => ['Ada Bakes', 'Shop', 'https://adabakes.org', 'approved', ''],
            'new gtld .store' => ['Ada Bakes', 'Shop', 'https://adabakes.store', 'approved', ''],
            'brand subdomain portfolio' => [$b, 'Portfolio', 'https://portfolio.supremeideasagency.com', 'approved', ''],
            'www is fine' => [$b, 'Site', 'https://www.supremeideasagency.com', 'approved', ''],
            'no scheme typed' => [$b, 'Site', 'supremeideasagency.com', 'approved', ''],
            'tracking params stripped' => [$b, 'Site', 'https://supremeideasagency.com/?utm_source=ig&fbclid=abc', 'approved', ''],
            'path kept' => [$b, 'Menu', 'https://supremeideasagency.com/menu', 'approved', ''],
            'shared host judged on customer label' => ['Ada Bakes', 'Blog', 'https://adabakes.blogspot.com', 'approved', ''],
            'github pages customer label' => ['Ada Bakes', 'Docs', 'https://adabakes.github.io', 'approved', ''],
            'initialism domain' => [$b, 'Site', 'https://sia.com', 'pending', ''],
            'initialism with word' => [$b, 'Site', 'https://sia-agency.com', 'approved', ''],
            // ---- scheme / structure ----
            'javascript scheme' => [$b, 'x', 'javascript:alert(1)', 'rejected', 'url'],
            'data scheme' => [$b, 'x', 'data:text/html;base64,AAAA', 'rejected', 'url'],
            'ftp scheme' => [$b, 'x', 'ftp://supremeideasagency.com', 'rejected', 'url'],
            'userinfo' => [$b, 'x', 'https://user:pass@supremeideasagency.com', 'rejected', 'url'],
            'custom port' => [$b, 'x', 'https://supremeideasagency.com:8443', 'rejected', 'url'],
            'ip host' => [$b, 'x', 'https://203.0.113.9/', 'rejected', 'url'],
            'ipv6 host' => [$b, 'x', 'https://[2606:4700::1111]/', 'rejected', 'url'],
            'localhost' => [$b, 'x', 'https://localhost', 'rejected', 'url'],
            '.internal' => [$b, 'x', 'https://supremeideas.internal', 'rejected', 'url'],
            '.local' => [$b, 'x', 'https://supremeideas.local', 'rejected', 'url'],
            'empty' => [$b, 'x', '', 'rejected', 'url'],
            'just a word' => [$b, 'x', 'hello', 'rejected', 'domain'],
            'a bare suffix' => [$b, 'x', 'https://com.ng', 'rejected', 'domain'],
            'unknown ending' => [$b, 'x', 'https://supremeideasagency.notatld', 'rejected', 'tld'],
            // ---- tld policy ----
            'denied .tk' => [$b, 'x', 'https://supremeideasagency.tk', 'rejected', 'tld'],
            'denied .top' => [$b, 'x', 'https://supremeideasagency.top', 'rejected', 'tld'],
            'denied .xyz' => [$b, 'x', 'https://supremeideasagency.xyz', 'rejected', 'tld'],
            'denied .zip' => [$b, 'x', 'https://supremeideasagency.zip', 'rejected', 'tld'],
            // ---- shorteners and aggregators ----
            'bit.ly' => [$b, 'x', 'https://bit.ly/3abcde', 'rejected', 'shortener'],
            'tinyurl' => [$b, 'x', 'https://tinyurl.com/yabc', 'rejected', 'shortener'],
            't.co' => [$b, 'x', 'https://t.co/AbC123', 'rejected', 'shortener'],
            'rb.gy' => [$b, 'x', 'https://rb.gy/abcd', 'rejected', 'shortener'],
            'linktree' => [$b, 'x', 'https://linktr.ee/supremeideas', 'rejected', 'aggregator'],
            'beacons' => [$b, 'x', 'https://beacons.ai/supremeideas', 'rejected', 'aggregator'],
            // ---- homographs ----
            'cyrillic a in latin word' => [$b, 'x', "https://supremeideаsagency.com", 'rejected', 'homograph'],
            'punycode mixed script' => [$b, 'x', 'https://xn--supremeideasagency-fvd.com', 'rejected', ''],
            // ---- resemblance ----
            'unrelated domain' => [$b, 'x', 'https://totallydifferentshop.com', 'rejected', 'resemblance'],
            'random unrelated' => ['Ada Bakes', 'x', 'https://zenithcarrentals.com', 'rejected', 'resemblance'],
            // ---- subdomain tricks ----
            'trust bait subdomain' => ['Ada Bakes', 'x', 'https://login.totallydifferentshop.com', 'rejected', 'subdomain'],
            'too many levels' => [$b, 'x', 'https://a.b.c.supremeideasagency.com', 'rejected', 'subdomain'],
            'hyphen chain' => [$b, 'x', 'https://supreme-ideas-agency-secure-login-now.com', 'rejected', 'subdomain'],
            'digit heavy label' => [$b, 'x', 'https://supreme8472639201.com', 'rejected', 'subdomain'],
            'random looking' => [$b, 'x', 'https://xkqzvbnmtrw.supremeideasagency.com', 'rejected', 'subdomain'],
            // ---- protected brands ----
            'paypal lookalike' => ['Ada Bakes', 'x', 'https://paypal-adabakes.com', 'rejected', 'lookalike'],
            'google in label' => ['Ada Bakes', 'x', 'https://google.adabakes.com', 'rejected', 'lookalike'],
            'bank token' => ['Ada Bakes', 'x', 'https://opay-adabakes.com', 'rejected', 'lookalike'],
            // ---- labels ----
            'label looks like a url' => [$b, 'supremeideasagency.com', 'https://supremeideasagency.com', 'rejected', 'label'],
            'label with http' => [$b, 'http link', 'https://supremeideasagency.com', 'rejected', 'label'],
            'label with a protected brand' => [$b, 'Official PayPal page', 'https://supremeideasagency.com', 'rejected', 'label'],
            'label empty' => [$b, '', 'https://supremeideasagency.com', 'rejected', 'label'],
            'label too long' => [$b, str_repeat('a', 25), 'https://supremeideasagency.com', 'rejected', 'label'],
            // ---- reachability and masking (answers come from the fake probe table in the test) ----
            'unreachable dns' => [$b, 'x', 'https://supremeideasagency.net', 'rejected', 'reachable'],
            'redirects to another site' => [$b, 'x', 'https://supremeideasagency.org', 'rejected', 'redirect'],
        ];
    }

    /** @dataProvider fixtureProvider */
    #[\PHPUnit\Framework\Attributes\DataProvider('fixtureProvider')]
    public function test_the_fixture_table(string $brand, string $label, string $url, string $status, string $failedCheck): void
    {
        $p = $this->policy([
            'supremeideasagency.net' => [false, null, 'dns'],
            'supremeideasagency.org' => [true, 'https://some-other-site.example.com/', null],
        ]);
        $v = $p->evaluate($brand, $label, $url);
        $this->assertSame($status, $v->status, $url.' => '.json_encode($v->checks));
        if ($failedCheck !== '') {
            $this->assertContains($failedCheck, array_column(array_filter($v->checks, fn ($c) => ! $c['ok']), 'key'), $url);
            $this->assertNotNull($v->reason);
        }
    }

    public static function fixtureProvider(): array
    {
        return self::fixtures();
    }

    public function test_the_fixture_table_is_big_enough(): void
    {
        $this->assertGreaterThanOrEqual(60, count(self::fixtures()));
    }

    public function test_the_public_suffix_split_handles_multi_label_wildcard_exception_and_private_suffixes(): void
    {
        $p = PublicSuffix::parse('shop.adabakes.com.ng');
        $this->assertSame(['com.ng', 'adabakes.com.ng', 'adabakes', 'shop'], [$p->suffix, $p->registrable, $p->label, $p->subdomain]);
        $this->assertSame('adabakes.co.uk', PublicSuffix::parse('www.adabakes.co.uk')->registrable);
        $b = PublicSuffix::parse('adabakes.blogspot.com');
        $this->assertTrue($b->privateSuffix);
        $this->assertSame('adabakes.blogspot.com', $b->registrable);
        $this->assertSame('github.io', PublicSuffix::parse('x.github.io')->suffix);
        // wildcard rule *.ck: "a.b.ck" has the suffix "b.ck"; the exception !www.ck makes "www.ck" registrable under "ck"
        $this->assertSame('b.ck', PublicSuffix::parse('a.b.ck')->suffix);
        $this->assertSame('www.ck', PublicSuffix::parse('www.ck')->registrable);
        $this->assertNull(PublicSuffix::parse('com.ng'), 'a public suffix itself cannot be owned');
        $this->assertNull(PublicSuffix::parse('localhost'));
        $this->assertTrue(PublicSuffix::isKnownTld('ng'));
        $this->assertFalse(PublicSuffix::isKnownTld('notatld'));
    }

    public function test_resemblance_signals(): void
    {
        $stop = ['ltd', 'limited', 'the', 'and'];
        $this->assertGreaterThanOrEqual(95, Resemblance::score('Supreme Ideas Agency', 'supremeideasagency', $stop));
        $this->assertGreaterThanOrEqual(60, Resemblance::score('Supreme Ideas Agency', 'supremeideas', $stop));    // contained
        $this->assertSame(55, Resemblance::score('Supreme Ideas Agency', 'sia', $stop));                             // initialism: ambiguous, so it only reaches the review band
        $this->assertGreaterThanOrEqual(90, Resemblance::score('Ada Bakes Ltd', 'adabakes', $stop));                // stopword removed
        $this->assertLessThan(40, Resemblance::score('Ada Bakes', 'zenithcarrentals', $stop));
        $this->assertGreaterThanOrEqual(90, Resemblance::score('Café Ñandú', 'cafenandu', $stop));                  // accents
        $this->assertSame(0, Resemblance::score('', 'x', $stop));
    }

    public function test_ssrf_the_ip_rules(): void
    {
        foreach (['127.0.0.1', '10.0.0.5', '172.16.9.9', '192.168.1.1', '169.254.169.254', '100.64.0.1', '0.0.0.0', '224.0.0.1', '240.0.0.1', '198.18.0.1', '::1', '::', 'fe80::1', 'fd00::1', 'ff02::1', '::ffff:127.0.0.1', '::ffff:10.1.2.3', '2001:db8::1', 'not-an-ip', ''] as $ip) {
            $this->assertFalse(SafeFetcher::isPublicIp($ip), "$ip must be refused");
        }
        foreach (['8.8.8.8', '1.1.1.1', '93.184.216.34', '2606:4700:4700::1111', '::ffff:8.8.8.8'] as $ip) {
            $this->assertTrue(SafeFetcher::isPublicIp($ip), "$ip must be allowed");
        }
    }

    public function test_ssrf_a_host_that_resolves_to_a_private_address_is_refused_even_if_one_answer_is_public(): void
    {
        $called = false;
        $f = new SafeFetcher(fn () => ['93.184.216.34', '10.0.0.7'], function () use (&$called) { $called = true; return [200, null]; });
        $r = $f->probe('https://rebind.example.com/');
        $this->assertFalse($r->reachable);
        $this->assertSame('blocked_ip', $r->error);
        $this->assertFalse($called, 'no request may leave when any address is private');
    }

    public function test_ssrf_a_redirect_into_internal_space_is_refused_at_the_hop(): void
    {
        $dns = fn (string $host) => $host === 'good.example.com' ? ['93.184.216.34'] : ['169.254.169.254'];
        $f = new SafeFetcher($dns, fn (string $m, string $url) => str_contains($url, 'good.example.com') ? [302, 'https://metadata.internal.example.net/latest'] : [200, null]);
        $r = $f->probe('https://good.example.com/');
        $this->assertFalse($r->reachable);
        $this->assertSame('blocked_ip', $r->error);
    }

    public function test_ssrf_redirect_cap_ports_literal_ips_and_plain_http(): void
    {
        $ok = fn () => ['93.184.216.34'];
        $loop = new SafeFetcher($ok, fn () => [301, 'https://again.example.com/']);
        $this->assertSame('too_many_redirects', $loop->probe('https://start.example.com/')->error);

        $this->assertSame('port', (new SafeFetcher($ok, fn () => [200, null]))->probe('https://example.com:8443/')->error);
        $this->assertSame('blocked_ip', (new SafeFetcher($ok, fn () => [200, null]))->probe('https://93.184.216.34/')->error);

        $seen = [];
        $up = (new SafeFetcher($ok, function (string $m, string $url) use (&$seen) { $seen[] = $url; return [200, null]; }))->probe('http://example.com/');
        $this->assertTrue($up->reachable);
        $this->assertTrue($up->upgradedFromHttp);
        $this->assertStringStartsWith('https://', $seen[0]);
    }

    public function test_head_refused_falls_back_to_get(): void
    {
        $methods = [];
        $f = new SafeFetcher(fn () => ['93.184.216.34'], function (string $m) use (&$methods) { $methods[] = $m; return $m === 'HEAD' ? [405, null] : [200, null]; });
        $this->assertTrue($f->probe('https://example.com/')->reachable);
        $this->assertSame(['HEAD', 'GET'], $methods);
    }
}
