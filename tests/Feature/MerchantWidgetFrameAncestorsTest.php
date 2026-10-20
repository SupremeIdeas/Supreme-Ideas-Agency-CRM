<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\MerchantWidgets\MerchantWidgetCheckoutService;
use App\Support\MerchantWidgetSettings;
use App\Support\SecuritySettings;

/**
 * Blueprint §5.4 / §14 — the test that must never be skipped. The hosted checkout is the ONLY page that may be framed, and only by the
 * requesting session's own merchant domains; every other route keeps the global `frame-ancestors 'self'` + X-Frame-Options.
 */
class MerchantWidgetFrameAncestorsTest extends MerchantWidgetTestCase
{
    private function token(array $domains = ['mystore.com'], string $slug = 'sahara', string $idem = 'frame-test-1'): string
    {
        $plan = $this->plan();
        ['key' => $key] = $this->key($this->merchant($slug), ['esim'], $domains);

        return app(MerchantWidgetCheckoutService::class)->createSession($key, 'esim', $plan->id, null, $idem, null)['token'];
    }

    private function csp($response): string
    {
        return (string) $response->headers->get('Content-Security-Policy');
    }

    public function test_the_hosted_checkout_allows_framing_only_by_its_own_merchants_domains(): void
    {
        $token = $this->token(['mystore.com', 'www.mystore.com']);

        $res = $this->get('/widget/checkout/'.$token)->assertOk();
        $csp = $this->csp($res);

        $this->assertMatchesRegularExpression("#frame-ancestors 'self' https://mystore\.com https://www\.mystore\.com(;|$)#", $csp);
        $this->assertStringNotContainsString('frame-ancestors *', $csp);
        $this->assertSame(1, substr_count($csp, 'frame-ancestors'), 'exactly one frame-ancestors directive');
        $this->assertNull($res->headers->get('X-Frame-Options'), 'XFO SAMEORIGIN would defeat the allowlist');
        $this->assertSame('no-store, private', $res->headers->get('Cache-Control'));
    }

    public function test_one_merchants_session_never_leaks_another_merchants_domain(): void
    {
        $a = $this->token(['alpha-shop.com'], 'alpha', 'frame-a-0001');
        $this->key($this->merchant('beta'), ['esim'], ['beta-shop.com']);

        $csp = $this->csp($this->get('/widget/checkout/'.$a));

        $this->assertStringContainsString('https://alpha-shop.com', $csp);
        $this->assertStringNotContainsString('beta-shop.com', $csp);
    }

    public function test_the_rest_of_the_policy_is_kept_and_only_framing_is_replaced(): void
    {
        $token = $this->token();
        $csp = $this->csp($this->get('/widget/checkout/'.$token));

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString('script-src', $csp);
    }

    public function test_every_other_route_still_gets_the_global_self_only_policy(): void
    {
        $this->token();   // a live widget session exists; it must not loosen anything else

        $res = $this->get('/login');

        $this->assertStringContainsString("frame-ancestors 'self'", $this->csp($res));
        $this->assertStringNotContainsString('mystore.com', $this->csp($res));
        $this->assertSame('SAMEORIGIN', $res->headers->get('X-Frame-Options'));
    }

    public function test_a_stale_reused_or_unknown_token_is_a_plain_404_with_no_widget_framing(): void
    {
        $token = $this->token();
        $this->get('/widget/checkout/not-a-real-token')->assertNotFound();

        $this->travel(MerchantWidgetSettings::sessionTtlMinutes() + 1)->minutes();
        $res = $this->get('/widget/checkout/'.$token)->assertNotFound();

        $this->assertStringNotContainsString('mystore.com', $this->csp($res), 'an expired session must not hand out a framing allowance');
    }

    public function test_a_revoked_key_stops_the_page_and_its_framing(): void
    {
        $token = $this->token();
        \App\Models\MerchantWidgetKey::query()->update(['status' => 'revoked']);

        $res = $this->get('/widget/checkout/'.$token)->assertNotFound();

        $this->assertStringNotContainsString('mystore.com', $this->csp($res));
    }

    public function test_the_platform_kill_switch_404s_the_hosted_page_too(): void
    {
        $token = $this->token();
        Setting::setValue(MerchantWidgetSettings::FLAG, false);

        $this->get('/widget/checkout/'.$token)->assertNotFound();
        $this->postJson('/widget/checkout/'.$token.'/pay', [])->assertNotFound();
    }

    public function test_the_page_is_cobranded_and_carries_the_powered_by_mark(): void
    {
        $token = $this->token();

        $this->get('/widget/checkout/'.$token)->assertOk()->assertSee('Sahara Connect')->assertSee('Powered by')->assertSee('Naara');
    }

    public function test_the_pay_post_is_csrf_exempt_because_the_token_is_the_credential(): void
    {
        $token = $this->token();

        // no session cookie / CSRF token (a framed cross-site page has neither): validation, not a 419
        $this->postJson('/widget/checkout/'.$token.'/pay', [])->assertStatus(422);
    }
}
