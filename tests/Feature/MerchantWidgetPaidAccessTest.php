<?php

namespace Tests\Feature;

use App\Livewire\Admin\MerchantWidgetControls;
use App\Livewire\MerchantWidgets;
use App\Models\MerchantWidgetAccessPurchase;
use App\Models\MerchantWidgetEvent;
use App\Models\MerchantWidgetKey;
use App\Models\MerchantWidgetOrder;
use App\Models\Setting;
use App\Models\User;
use App\Services\MerchantWidgets\MerchantWidgetAccessService;
use App\Services\MerchantWidgets\MerchantWidgetAnalytics;
use App\Services\Wallet\WalletService;
use App\Support\MerchantWidgetSettings;
use Livewire\Livewire;

/** Paid / free Naara Widgets access and the redesigned analytics (MASTER-ONLY). */
class MerchantWidgetPaidAccessTest extends MerchantWidgetTestCase
{
    private function paid(float $price = 25): void
    {
        Setting::setValue(MerchantWidgetSettings::PAID_ACCESS, true);
        Setting::setValue(MerchantWidgetSettings::ACCESS_PRICE_USD, $price);
    }

    private function fund(User $u, float $usd): void
    {
        app(WalletService::class)->credit($u, $usd, 'USD', ['reference' => 'seed:'.$u->id, 'description' => 'seed', 'notify' => false]);
    }

    public function test_free_by_default_so_everyone_can_create_a_key(): void
    {
        $m = $this->merchant();
        $this->assertFalse(MerchantWidgetSettings::isPaid());
        $this->assertTrue(app(MerchantWidgetAccessService::class)->hasAccess($m));
    }

    public function test_paid_mode_blocks_key_creation_until_bought(): void
    {
        $this->paid();
        $m = $this->merchant();
        Livewire::actingAs($m->owner)->test(MerchantWidgets::class)
            ->assertSee('Unlock Naara Widgets')
            ->set('label', 'Shop')->set('domains', 'mystore.com')->call('create')
            ->assertSet('error', 'Unlock Widgets first to create a key.');
        $this->assertSame(0, MerchantWidgetKey::count());
    }

    public function test_buying_debits_the_wallet_once_and_unlocks(): void
    {
        $this->paid(25);
        $m = $this->merchant();
        $this->fund($m->owner, 40);

        $lw = Livewire::actingAs($m->owner)->test(MerchantWidgets::class)->call('buyAccess')->call('buyAccess');

        $lw->assertSet('error', null)->assertDontSee('Unlock Naara Widgets');
        $this->assertSame(1, MerchantWidgetAccessPurchase::count());
        $this->assertEqualsWithDelta(15.0, app(WalletService::class)->spendableUsd($m->owner->fresh()), 0.01);

        $lw->set('label', 'Shop')->set('domains', 'mystore.com')->call('create')->assertSet('error', null);
        $this->assertSame(1, MerchantWidgetKey::count());
    }

    public function test_an_underfunded_wallet_is_not_charged_and_stays_locked(): void
    {
        $this->paid(25);
        $m = $this->merchant();
        $this->fund($m->owner, 5);

        Livewire::actingAs($m->owner)->test(MerchantWidgets::class)->call('buyAccess');

        $this->assertSame(0, MerchantWidgetAccessPurchase::count());
        $this->assertEqualsWithDelta(5.0, app(WalletService::class)->spendableUsd($m->owner->fresh()), 0.01);
    }

    public function test_the_plugin_download_is_refused_until_access_exists(): void
    {
        $this->paid();
        $m = $this->merchant();
        ['key' => $key] = $this->key($m);

        $this->actingAs($m->owner)->get(route('merchant.widgets.plugin', ['key' => $key->id]))->assertForbidden();

        app(MerchantWidgetAccessService::class)->grant($m, User::factory()->create());
        $this->actingAs($m->owner)->get(route('merchant.widgets.plugin', ['key' => $key->id]))->assertOk();
    }

    public function test_flipping_to_free_opens_everything_and_a_buyer_keeps_access_when_paid_again(): void
    {
        $this->paid();
        $m = $this->merchant();
        $other = $this->merchant('other');
        app(MerchantWidgetAccessService::class)->grant($m, User::factory()->create());

        Setting::setValue(MerchantWidgetSettings::PAID_ACCESS, false);
        $this->assertTrue(app(MerchantWidgetAccessService::class)->hasAccess($other));

        Setting::setValue(MerchantWidgetSettings::PAID_ACCESS, true);
        $this->assertTrue(app(MerchantWidgetAccessService::class)->hasAccess($m));
        $this->assertFalse(app(MerchantWidgetAccessService::class)->hasAccess($other));
    }

    public function test_a_zero_price_is_the_same_as_free(): void
    {
        $this->paid(0);
        $this->assertFalse(MerchantWidgetSettings::isPaid());
    }

    public function test_an_admin_sets_the_price_and_the_free_switch_and_it_is_audited(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        Livewire::actingAs($admin)->test(MerchantWidgetControls::class)
            ->set('paidAccess', true)->set('accessPrice', 49.5)->call('save')->assertHasNoErrors();

        $this->assertTrue(MerchantWidgetSettings::isPaid());
        $this->assertSame(49.5, MerchantWidgetSettings::accessPriceUsd());
        $this->assertDatabaseHas('audit_logs', ['action' => 'merchant_widget.access_pricing_changed']);

        Livewire::actingAs($admin)->test(MerchantWidgetControls::class)->set('paidAccess', false)->call('save');
        $this->assertFalse(MerchantWidgetSettings::isPaid());
    }

    public function test_live_keys_keep_serving_shoppers_when_a_paywall_is_switched_on(): void
    {
        $m = $this->merchant();
        ['key' => $key] = $this->key($m);
        $this->plan();
        $this->paid();

        $this->getJson('/api/v1/merchant-widget/catalogue', $this->h($key))->assertOk();
    }

    public function test_analytics_report_funnel_series_status_and_mask_emails(): void
    {
        $m = $this->merchant();
        ['key' => $key] = $this->key($m);
        foreach (['impression', 'impression', 'impression', 'impression', 'catalogue_view', 'catalogue_view', 'checkout_opened', 'checkout_opened'] as $t) {
            MerchantWidgetEvent::create(['merchant_widget_key_id' => $key->id, 'event_type' => $t]);
        }
        foreach ([[MerchantWidgetOrder::COMPLETED, 12.5], [MerchantWidgetOrder::FAILED, 8]] as $i => [$st, $v]) {
            MerchantWidgetOrder::create([
                'merchant_widget_key_id' => $key->id, 'merchant_id' => $m->id, 'kind' => 'esim', 'product_id' => 1, 'reference' => 'r'.$i,
                'status' => $st, 'retail_price_usd' => $v, 'merchant_price_usd' => $v, 'buyer_email' => 'jane.doe@gmail.com', 'origin_domain' => 'mystore.com',
            ]);
        }

        $r = app(MerchantWidgetAnalytics::class)->forKey($key, 7);

        $this->assertSame(7, count($r['series']));
        $this->assertSame(4, $r['totals']['views']);
        $this->assertSame(1, $r['totals']['orders']);
        $this->assertSame(12.5, $r['totals']['revenue']);
        $this->assertSame(50.0, $r['totals']['conversion']);
        $this->assertSame(['completed' => 1, 'failed' => 1], $r['by_status']);
        $this->assertSame(['mystore.com' => 2], $r['origins']);
        $this->assertSame(100, $r['funnel'][0]['pct']);
        $this->assertSame(50.0, $r['funnel'][3]['pct'] * 2);   // 1 of 4 views = 25
        $this->assertStringNotContainsString('jane.doe', json_encode($r['recent']));
        $this->assertTrue($r['has_data']);
    }

    public function test_the_dashboard_renders_the_analytics_and_an_empty_key_does_not_break(): void
    {
        $m = $this->merchant();
        ['key' => $key] = $this->key($m);

        Livewire::actingAs($m->owner)->test(MerchantWidgets::class)
            ->assertSee('Nothing to chart yet')->call('setRange', 7)->assertSet('range', 7)->call('setRange', 5)->assertSet('range', 30);

        MerchantWidgetEvent::create(['merchant_widget_key_id' => $key->id, 'event_type' => 'impression']);
        Livewire::actingAs($m->owner)->test(MerchantWidgets::class)->assertSee('Where shoppers drop off')->assertSee('Latest orders');
    }
}
