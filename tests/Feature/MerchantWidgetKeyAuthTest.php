<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Merchant;
use App\Models\MerchantWidgetKey;
use App\Models\Setting;
use App\Services\MerchantWidgets\MerchantWidgetKeyService;
use App\Support\MerchantWidgetSettings;

/** Naara Widgets security layer (blueprint §5, §14): key, origin allowlist, CORS, scopes, kill switches. */
class MerchantWidgetKeyAuthTest extends MerchantWidgetTestCase
{
    public function test_the_platform_kill_switch_hides_the_whole_surface(): void
    {
        ['key' => $key] = $this->key();
        Setting::setValue(MerchantWidgetSettings::FLAG, false);

        $this->getJson('/api/v1/merchant-widget/catalogue', $this->h($key))->assertNotFound();
    }

    public function test_a_missing_unknown_or_revoked_key_is_a_403_and_audited(): void
    {
        ['key' => $key] = $this->key();

        $this->getJson('/api/v1/merchant-widget/catalogue', ['Origin' => self::ORIGIN])->assertForbidden();
        $this->getJson('/api/v1/merchant-widget/catalogue', ['X-Naara-Widget-Key' => 'wk_live_nope', 'Origin' => self::ORIGIN])->assertForbidden();

        app(MerchantWidgetKeyService::class)->revoke($key, 'test');
        $this->getJson('/api/v1/merchant-widget/catalogue', $this->h($key))->assertForbidden();   // live read, no cache lag

        $this->assertGreaterThanOrEqual(3, AuditLog::where('action', 'merchant_widget.key_rejected')->count());
    }

    public function test_a_suspended_merchant_disables_their_keys(): void
    {
        ['key' => $key] = $this->key();
        $key->merchant->forceFill(['status' => Merchant::SUSPENDED])->save();

        $this->getJson('/api/v1/merchant-widget/catalogue', $this->h($key))->assertForbidden();
    }

    public function test_an_origin_outside_the_allowlist_is_rejected_even_for_reads(): void
    {
        ['key' => $key] = $this->key();

        $this->getJson('/api/v1/merchant-widget/catalogue', $this->h($key, 'https://evil.example'))->assertForbidden();
        $this->getJson('/api/v1/merchant-widget/catalogue', $this->h($key, null))->assertForbidden();   // no Origin/Referer at all
        $this->assertSame(1, AuditLog::where('action', 'merchant_widget.origin_rejected')->count() >= 1 ? 1 : 0);
    }

    public function test_cors_echoes_only_the_matched_origin_never_a_wildcard(): void
    {
        ['key' => $key] = $this->key();

        $res = $this->getJson('/api/v1/merchant-widget/catalogue', $this->h($key));

        $res->assertOk()->assertHeader('Access-Control-Allow-Origin', self::ORIGIN);
        $this->assertNotSame('*', $res->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('Origin', $res->headers->get('Vary'));
    }

    public function test_the_referer_is_a_fallback_when_there_is_no_origin_header(): void
    {
        ['key' => $key] = $this->key();

        $this->getJson('/api/v1/merchant-widget/catalogue', ['X-Naara-Widget-Key' => $key->public_key, 'Referer' => 'https://mystore.com/shop?x=1'])->assertOk();
    }

    public function test_a_preflight_is_answered_only_for_an_origin_some_active_key_allows(): void
    {
        $this->key();

        $this->call('OPTIONS', '/api/v1/merchant-widget/catalogue', [], [], [], ['HTTP_ORIGIN' => self::ORIGIN])
            ->assertNoContent()->assertHeader('Access-Control-Allow-Origin', self::ORIGIN);
        $this->call('OPTIONS', '/api/v1/merchant-widget/catalogue', [], [], [], ['HTTP_ORIGIN' => 'https://evil.example'])->assertForbidden();
    }

    public function test_the_key_is_never_accepted_from_the_query_string(): void
    {
        ['key' => $key] = $this->key();

        $this->getJson('/api/v1/merchant-widget/catalogue?key='.$key->public_key, ['Origin' => self::ORIGIN])->assertForbidden();
    }

    public function test_a_product_scope_is_enforced_server_side(): void
    {
        ['key' => $key] = $this->key(null, ['esim']);

        $this->postJson('/api/v1/merchant-widget/giftcards/1/quote', ['face_value' => 10], $this->h($key))->assertForbidden();
    }

    public function test_domains_are_exact_hosts_and_wildcards_are_refused(): void
    {
        $svc = app(MerchantWidgetKeyService::class);

        $this->assertSame(['mystore.com', 'www.mystore.com'], $svc->normaliseDomains(['https://MyStore.com/shop', 'www.mystore.com', '*.mystore.com', 'bad host', '']));
    }

    public function test_the_secret_is_shown_once_and_only_its_hash_is_stored(): void
    {
        ['key' => $key, 'secret' => $secret] = $this->key();

        $this->assertStringStartsWith('wks_', $secret);
        $this->assertNotSame($secret, $key->secret_hash);
        $this->assertTrue(app(MerchantWidgetKeyService::class)->verifySecret($key, $secret));
        $this->assertArrayNotHasKey('secret_hash', $key->toArray());
    }

    public function test_rotating_replaces_the_key_and_revokes_the_old_one(): void
    {
        ['key' => $old] = $this->key();

        ['key' => $new] = app(MerchantWidgetKeyService::class)->rotate($old);

        $this->assertNotSame($old->public_key, $new->public_key);
        $this->assertSame(MerchantWidgetKey::REVOKED, $old->fresh()->status);
        $this->getJson('/api/v1/merchant-widget/catalogue', $this->h($new))->assertOk();
    }

    public function test_the_per_merchant_key_limit_is_an_admin_setting(): void
    {
        Setting::setValue(MerchantWidgetSettings::MAX_KEYS, 1);
        $m = $this->merchant();
        $this->key($m);

        $this->expectException(\InvalidArgumentException::class);
        $this->key($m);
    }
}
