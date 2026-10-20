<?php

namespace Tests\Feature;

use App\Models\EsimPlan;
use App\Models\Merchant;
use App\Models\MerchantWidgetKey;
use App\Models\Setting;
use App\Models\User;
use App\Services\MerchantWidgets\MerchantWidgetKeyService;
use App\Support\MerchantSettings;
use App\Support\MerchantWidgetSettings;
use Database\Seeders\PricingSettingsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Shared fixtures for the Naara Widgets suite (MASTER-ONLY). */
abstract class MerchantWidgetTestCase extends TestCase
{
    use RefreshDatabase;

    protected const ORIGIN = 'https://mystore.com';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.fivesim.api_key' => 'test-key']);   // a global number provider is keyed: the whole catalogue is offerable
        $this->seed(RoleSeeder::class);
        $this->seed(PricingSettingsSeeder::class);
        Setting::setValue(MerchantSettings::MARGIN, 10.0, 'merchants');
        Setting::setValue(MerchantWidgetSettings::FLAG, true);
    }

    protected function merchant(string $slug = 'sahara'): Merchant
    {
        return Merchant::create([
            'owner_user_id' => User::factory()->create(['is_active' => true])->id,
            'business_name' => 'Sahara Connect', 'slug' => $slug, 'status' => Merchant::ACTIVE,
        ]);
    }

    /** @return array{key: MerchantWidgetKey, secret: string} */
    protected function key(?Merchant $m = null, array $scopes = ['esim', 'giftcard'], array $domains = ['mystore.com']): array
    {
        return app(MerchantWidgetKeyService::class)->create($m ?? $this->merchant(), 'My site', $domains, $scopes);
    }

    protected function plan(): EsimPlan
    {
        return EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'MP1',
            'name' => 'Roam 5GB', 'cost_price_usd' => 10.0, 'computed_retail_usd' => 13.00, 'is_active' => true,
        ])->fresh();
    }

    protected function h(MerchantWidgetKey $key, ?string $origin = self::ORIGIN): array
    {
        return array_filter(['X-Naara-Widget-Key' => $key->public_key, 'Origin' => $origin, 'Accept' => 'application/json']);
    }
}
