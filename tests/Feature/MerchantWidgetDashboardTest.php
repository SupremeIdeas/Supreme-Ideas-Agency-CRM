<?php

namespace Tests\Feature;

use App\Livewire\MerchantWidgets;
use App\Models\MerchantWidgetEvent;
use App\Models\MerchantWidgetKey;
use App\Models\MerchantWidgetOrder;
use App\Models\Setting;
use App\Support\MerchantWidgetSdk;
use App\Support\MerchantWidgetSettings;
use Livewire\Livewire;

/** Merchant dashboard "Widgets" page, snippet, SRI helper and WordPress plugin download (MASTER-ONLY). */
class MerchantWidgetDashboardTest extends MerchantWidgetTestCase
{
    public function test_a_merchant_creates_a_key_sees_the_public_snippet_and_never_a_secret(): void
    {
        $m = $this->merchant();
        Livewire::actingAs($m->owner)->test(MerchantWidgets::class)
            ->set('label', 'My shop')->set('domains', "https://MyStore.com/, www.mystore.com")
            ->call('create')->assertSet('error', null)
            ->assertSee('data-naara-key=&quot;wk_live_', false)->assertDontSee('wks_');

        $key = MerchantWidgetKey::first();
        $this->assertSame(['mystore.com', 'www.mystore.com'], $key->allowedDomains());
        $this->assertSame($m->id, $key->merchant_id);
    }

    public function test_a_merchant_can_only_act_on_their_own_keys(): void
    {
        $mine = $this->merchant('mine');
        ['key' => $theirs] = $this->key($this->merchant('theirs'));

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::actingAs($mine->owner)->test(MerchantWidgets::class)->call('revoke', $theirs->id);
    }

    public function test_revoke_and_rotate_work_and_revoked_keys_get_no_snippet(): void
    {
        $m = $this->merchant();
        ['key' => $key] = $this->key($m);
        $lw = Livewire::actingAs($m->owner)->test(MerchantWidgets::class)->call('rotate', $key->id);

        $this->assertSame('revoked', $key->fresh()->status);
        $this->assertSame(1, MerchantWidgetKey::where('status', 'active')->count());

        $new = MerchantWidgetKey::where('status', 'active')->first();
        $lw->call('revoke', $new->id)->call('select', $new->id)->assertDontSee('data-naara-key');
        $this->assertSame('revoked', $new->fresh()->status);
    }

    public function test_the_page_shows_this_keys_funnel_and_conversion(): void
    {
        $m = $this->merchant();
        ['key' => $key] = $this->key($m);
        foreach (['impression', 'impression', 'impression', 'checkout_opened', 'checkout_opened'] as $t) {
            MerchantWidgetEvent::create(['merchant_widget_key_id' => $key->id, 'event_type' => $t]);
        }
        MerchantWidgetOrder::create([
            'merchant_widget_key_id' => $key->id, 'merchant_id' => $m->id, 'kind' => 'esim', 'product_id' => 1, 'reference' => 'r1',
            'status' => MerchantWidgetOrder::COMPLETED, 'retail_price_usd' => 14, 'merchant_price_usd' => 12.5,
        ]);

        Livewire::actingAs($m->owner)->test(MerchantWidgets::class)->assertSee('50%')->assertSee('$12.50');
    }

    public function test_the_page_404s_when_widgets_are_off_or_for_a_non_merchant(): void
    {
        $m = $this->merchant();
        $this->actingAs($m->owner)->get('/merchant/widgets')->assertOk();

        Setting::setValue(MerchantWidgetSettings::FLAG, false);
        $this->actingAs($m->owner)->get('/merchant/widgets')->assertNotFound();
        Setting::setValue(MerchantWidgetSettings::FLAG, true);

        $this->actingAs(\App\Models\User::factory()->create(['is_active' => true]))->get('/merchant/widgets')->assertNotFound();
    }

    public function test_the_snippet_pins_integrity_only_when_asked_and_escapes_everything(): void
    {
        $plain = MerchantWidgetSdk::snippet('wk_live_abc', 'esim');
        $this->assertStringContainsString('data-product-type="esim"', $plain);
        $this->assertStringNotContainsString('integrity=', $plain);
        $this->assertMatchesRegularExpression('/integrity="sha384-[A-Za-z0-9+\/=]+"/', MerchantWidgetSdk::snippet('wk_live_abc', '', 'modal', true));
        $this->assertStringNotContainsString('<img', MerchantWidgetSdk::snippet('"><img src=x>'));
    }

    public function test_the_dashboard_offers_numbers_and_explains_what_shoppers_get_and_the_snippet_can_target_numbers(): void
    {
        $m = $this->merchant();
        $this->key($m);
        Livewire::actingAs($m->owner)->test(MerchantWidgets::class)
            ->assertSee('Verification numbers')
            ->assertSee('What your customers get')
            ->assertSee('data-start="account"', false);

        $this->assertStringContainsString('data-product-type="number"', MerchantWidgetSdk::snippet('wk_live_abc', 'number'));
    }

    public function test_the_sdk_is_pure_ascii_so_a_host_that_serves_it_without_a_charset_cannot_garble_it(): void
    {
        $this->assertSame(0, preg_match('/[^\x00-\x7F]/', (string) file_get_contents(public_path(MerchantWidgetSdk::PATH))));
    }

    public function test_the_wordpress_plugin_ships_numbers_and_the_my_orders_page_without_a_secret(): void
    {
        $root = base_path(\App\Services\MerchantWidgets\MerchantWidgetPluginPackager::SOURCE);
        $this->assertSame('1.2.0', \App\Services\MerchantWidgets\MerchantWidgetPluginPackager::version());
        $render = (string) file_get_contents($root.'/includes/render.php');
        $this->assertStringContainsString("'number'", $render);
        $this->assertStringContainsString("add_shortcode('naara_account'", $render);
        $this->assertStringContainsString('data-start', $render);
        $this->assertStringContainsString('Stable tag: 1.2.0', (string) file_get_contents($root.'/readme.txt'));
        $this->assertStringContainsString('naara_widgets[account]', (string) file_get_contents($root.'/includes/settings.php'));
        // The loader still holds no secret or pricing: only the public key and the host.
        $this->assertDoesNotMatchRegularExpression('/wk_secret|Authorization/i', $render.(string) file_get_contents($root.'/includes/settings.php'));
    }

    public function test_the_plugin_zip_is_prefilled_with_the_hosts_address_and_only_the_merchants_own_public_key(): void
    {
        $m = $this->merchant();
        ['key' => $key, 'secret' => $secret] = $this->key($m);
        ['key' => $other] = $this->key($this->merchant('other'));

        $resp = $this->actingAs($m->owner)->get(route('merchant.widgets.plugin', ['key' => $key->id]));
        $resp->assertOk()->assertDownload();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($resp->baseResponse->getFile()->getPathname()) === true);
        $defaults = $zip->getFromName('naara-widgets/defaults.php');
        $this->assertStringContainsString($key->public_key, $defaults);
        $this->assertStringNotContainsString($secret, $defaults);
        $this->assertNotFalse($zip->getFromName('naara-widgets/naara-widgets.php'));
        $this->assertNotFalse($zip->getFromName('naara-widgets/block/block.js'));

        $this->actingAs($m->owner)->get(route('merchant.widgets.plugin', ['key' => $other->id]))->assertNotFound();
        auth()->logout();
        $this->get(route('merchant.widgets.plugin'))->assertRedirect();
    }

    public function test_the_plugin_version_endpoint_is_public_and_tiny(): void
    {
        $this->getJson('/widget/plugin/version')->assertOk()->assertExactJson(['version' => \App\Services\MerchantWidgets\MerchantWidgetPluginPackager::version()]);
    }
}
