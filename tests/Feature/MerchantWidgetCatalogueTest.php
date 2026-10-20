<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\Setting;
use App\Services\Pricing\PricingEngine;

/** Naara Widgets catalogue + pricing lane (blueprint §5.3, §6, §14): merchant lane, masked payload, never cost or supplier. */
class MerchantWidgetCatalogueTest extends MerchantWidgetTestCase
{
    public function test_the_catalogue_is_priced_in_the_merchant_lane(): void
    {
        $plan = $this->plan();
        ['key' => $key] = $this->key();

        $price = $this->getJson('/api/v1/merchant-widget/catalogue?kind=esim', $this->h($key))->assertOk()->json('data.esim.0.price_usd');

        $this->assertSame(round(app(PricingEngine::class)->merchantEsimPrice($plan, $key->merchant, log: false), 2), (float) $price);
        $this->assertGreaterThan(round(app(PricingEngine::class)->calculateRetail($plan, log: false), 2), (float) $price);
    }

    public function test_the_payload_never_exposes_cost_or_supplier(): void
    {
        $this->plan();
        ['key' => $key] = $this->key();

        $body = $this->getJson('/api/v1/merchant-widget/catalogue', $this->h($key))->assertOk()->getContent();

        foreach (['cost_price', 'wholesale', 'esimgo', 'provider', 'net_price', 'MP1'] as $leak) {
            $this->assertStringNotContainsStringIgnoringCase($leak, $body, "the widget catalogue must not leak [{$leak}]");
        }
    }

    public function test_a_key_only_sees_the_products_in_its_scopes(): void
    {
        $this->plan();
        ['key' => $key] = $this->key(null, ['giftcard']);

        $data = $this->getJson('/api/v1/merchant-widget/catalogue', $this->h($key))->assertOk()->json('data');

        $this->assertArrayNotHasKey('esim', $data);
    }

    public function test_the_gift_card_merchant_lane_never_sells_at_or_below_cost(): void
    {
        $m = Merchant::create(['owner_user_id' => $this->merchant()->owner_user_id, 'business_name' => 'Zero', 'slug' => 'zero', 'status' => Merchant::ACTIVE, 'reseller_margin_pct' => 0]);
        Setting::setValue('pricing.giftcard_markup_pct', 0);
        Setting::setValue('pricing.giftcard_min_profit', 0.25);

        $price = app(PricingEngine::class)->giftCardMerchantPrice(20.0, 'zendit', $m, log: false);

        $this->assertGreaterThanOrEqual(20.25, $price);
    }

    public function test_the_gift_card_merchant_price_is_at_least_retail_when_the_margin_is_positive(): void
    {
        $m = $this->merchant();
        $engine = app(PricingEngine::class);

        $this->assertGreaterThan($engine->giftCardRetail(50.0, 'zendit', false), $engine->giftCardMerchantPrice(50.0, 'zendit', $m, false));
    }

    public function test_gift_card_logos_are_absolute_urls_and_popular_brands_come_first(): void
    {
        $mk = fn (string $name, string $key, string $logo, bool $lead = true) => \App\Models\GiftCardProduct::create([
            'provider' => 'zendit', 'provider_product_id' => 'p'.uniqid(), 'brand_key' => $key, 'brand_name' => $name, 'country' => 'US', 'currency' => 'USD',
            'denomination_type' => 'FIXED', 'fixed_denominations' => [25], 'cost_meta' => ['discountPercentage' => 0],
            'logo_url' => $logo, 'provider_enabled' => true, 'admin_enabled' => true, 'is_primary' => true, 'is_lead' => $lead, 'priceable' => true,
        ]);
        $mk('Aardvark Cafe', 'aardvark-cafe', '/images/giftcards/logos/aardvark.webp');
        $mk('Netflix', 'netflix', '/images/giftcards/logos/netflix.webp');
        $mk('Netflix Spain', 'netflix-es', '/images/giftcards/logos/netflix.webp', false);   // a regional duplicate, not a lead
        $mk('Amazon', 'amazon', 'https://cdn.example.com/amazon.png');
        $mk('Zed Store', 'zed', 'http://old-staging.example:8125/storage/giftcards/logos/zed.webp');   // saved under an old APP_URL: re-rooted on this site
        ['key' => $key] = $this->key(null, ['giftcard']);

        $cards = $this->getJson('/api/v1/merchant-widget/catalogue?kind=giftcard', $this->h($key))->assertOk()->json('data.giftcard');

        $brands = array_column($cards, 'brand');
        $this->assertSame(['Amazon', 'Netflix', 'Aardvark Cafe', 'Zed Store'], $brands, 'popular first, one card per brand');
        foreach ($cards as $c) {
            $this->assertMatchesRegularExpression('#^https?://#', $c['logo_url'], $c['brand'].' needs an absolute logo address');
        }
        $this->assertSame('https://cdn.example.com/amazon.png', $cards[0]['logo_url']);
        $this->assertStringEndsWith('/images/giftcards/logos/netflix.webp', $cards[1]['logo_url']);
        $this->assertSame(url('/storage/giftcards/logos/zed.webp'), $cards[3]['logo_url'], 'our own file is re-rooted on the current host');
    }
}
