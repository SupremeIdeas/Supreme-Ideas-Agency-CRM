<?php

namespace Tests\Feature;

use App\Livewire\Admin\MerchantWidgetControls;
use App\Models\MerchantWidgetOrder;
use App\Models\Setting;
use App\Models\User;
use App\Support\MerchantWidgetFraud;
use App\Support\MerchantWidgetSettings;
use Livewire\Livewire;

/** Admin Widgets page + the fraud guard thresholds (MASTER-ONLY). */
class MerchantWidgetAdminTest extends MerchantWidgetTestCase
{
    private function admin(): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole('admin');

        return $u;
    }

    public function test_only_admins_can_open_the_page(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]))->get('/adminmaster/merchant-widgets')->assertNotFound();
        $this->actingAs($this->admin())->get(route('admin.merchant-widgets'))->assertOk()->assertSee('Naara Widgets');
    }

    public function test_every_limit_is_a_validated_setting_and_the_change_is_audited(): void
    {
        Livewire::actingAs($this->admin())->test(MerchantWidgetControls::class)
            ->set('enabled', false)->set('maxKeys', 2)->set('guestCap', 40)->set('velocity', 9)->set('pluginUrl', 'https://naara.test/p.zip')
            ->call('save')->assertHasNoErrors();

        $this->assertFalse(MerchantWidgetSettings::enabled());
        $this->assertSame(2, MerchantWidgetSettings::maxKeys());
        $this->assertSame(40.0, MerchantWidgetSettings::firstTimeGuestCapUsd());
        $this->assertSame(9, MerchantWidgetSettings::velocityPerKeyHour());
        $this->assertSame('https://naara.test/p.zip', MerchantWidgetSettings::wpPluginUrl());
        $this->assertDatabaseHas('audit_logs', ['action' => 'merchant_widget.settings_updated']);

        Livewire::actingAs($this->admin())->test(MerchantWidgetControls::class)
            ->set('maxKeys', 0)->set('pluginUrl', 'http://insecure.test/p.zip')->call('save')->assertHasErrors(['maxKeys', 'pluginUrl']);
    }

    public function test_admin_revoke_is_live_and_the_key_stops_working_at_once(): void
    {
        ['key' => $key] = $this->key();
        $this->getJson('/api/v1/merchant-widget/catalogue', $this->h($key))->assertOk();

        Livewire::actingAs($this->admin())->test(MerchantWidgetControls::class)->call('revoke', $key->id)->assertSee('Key revoked');

        $this->getJson('/api/v1/merchant-widget/catalogue', $this->h($key))->assertForbidden();
    }

    public function test_held_orders_are_listed_with_their_reason(): void
    {
        $m = $this->merchant();
        MerchantWidgetOrder::create([
            'merchant_id' => $m->id, 'kind' => 'giftcard', 'product_id' => 1, 'reference' => 'h1', 'status' => MerchantWidgetOrder::HELD,
            'failure_reason' => 'manual_review', 'retail_price_usd' => 30, 'merchant_price_usd' => 28,
        ]);

        Livewire::actingAs($this->admin())->test(MerchantWidgetControls::class)->assertSee('manual review')->assertSee('Sahara Connect');
    }

    public function test_velocity_and_first_time_guest_cap_stop_abuse_and_read_their_thresholds_from_settings(): void
    {
        ['key' => $key] = $this->key();
        Setting::setValue(MerchantWidgetSettings::VELOCITY_PER_KEY_HOUR, 2);
        foreach ([1, 2] as $i) {
            MerchantWidgetOrder::create(['merchant_widget_key_id' => $key->id, 'merchant_id' => $key->merchant_id, 'kind' => 'esim', 'product_id' => 1, 'reference' => "v{$i}", 'retail_price_usd' => 5, 'merchant_price_usd' => 5]);
        }
        $this->expectExceptionMessage('Too many checkouts');
        MerchantWidgetFraud::assertSessionAllowed($key);
    }

    public function test_a_first_time_buyer_over_the_cap_is_refused_but_a_returning_buyer_is_not(): void
    {
        $m = $this->merchant();
        $buyer = User::factory()->create();
        Setting::setValue(MerchantWidgetSettings::FIRST_TIME_GUEST_CAP_USD, 50);
        $big = new MerchantWidgetOrder(['merchant_price_usd' => 80]);

        try {
            MerchantWidgetFraud::assertGuestWithinCap($buyer, $big);
            $this->fail('first-time cap not enforced');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('above the limit', $e->getMessage());
        }

        MerchantWidgetOrder::create(['merchant_id' => $m->id, 'buyer_user_id' => $buyer->id, 'kind' => 'esim', 'product_id' => 1, 'reference' => 'done', 'status' => MerchantWidgetOrder::COMPLETED, 'retail_price_usd' => 5, 'merchant_price_usd' => 5]);
        MerchantWidgetFraud::assertGuestWithinCap($buyer, $big);
        $this->addToAssertionCount(1);
    }

    public function test_numbers_and_the_shopper_area_are_admin_switches_and_deletion_requests_are_closed_by_an_admin(): void
    {
        $this->assertFalse(MerchantWidgetSettings::numbersEnabled(), 'numbers move provider money: off until the admin says so');
        $this->assertTrue(MerchantWidgetSettings::accountEnabled());

        Livewire::actingAs($this->admin())->test(MerchantWidgetControls::class)
            ->set('numbersEnabled', true)->set('numberServices', 'whatsapp, Telegram , google')->set('accountEnabled', false)->set('shopperDays', 14)
            ->call('save')->assertHasNoErrors();

        $this->assertTrue(MerchantWidgetSettings::numbersEnabled());
        $this->assertSame(['whatsapp', 'telegram', 'google'], MerchantWidgetSettings::numberServices());
        $this->assertFalse(MerchantWidgetSettings::accountEnabled());
        $this->assertSame(14, MerchantWidgetSettings::shopperSessionDays());

        Livewire::actingAs($this->admin())->test(MerchantWidgetControls::class)->set('numberServices', 'bad;drop')->call('save')->assertHasErrors('numberServices');

        $m = $this->merchant('req');
        $r = \App\Models\MerchantWidgetDataRequest::create(['merchant_id' => $m->id, 'email' => 'x@y.com', 'kind' => 'delete', 'status' => 'open']);
        Livewire::actingAs($this->admin())->test(MerchantWidgetControls::class)->assertSee('x@y.com')->call('closeRequest', $r->id)->assertDontSee('x@y.com');
        $this->assertSame('done', $r->fresh()->status);
    }
}
