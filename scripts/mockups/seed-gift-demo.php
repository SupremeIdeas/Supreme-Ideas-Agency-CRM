<?php
/**
 * Naara Gift demo catalogue for the marketing screenshots (MASTER marketing tooling). Throwaway sqlite database only, never a real one.
 * Fills the storefront with real, well-known gift card brands (name, brand colour, category, denominations; logos are the committed tiles under
 * public/images/marketing/gift/brands) so a screenshot shows a believable store, not "Demo Card". Run the demo server with the sandbox flag and any
 * placeholder key so the page flips from Coming Soon to live:
 *   RELOADLY_CLIENT_ID=sandbox RELOADLY_CLIENT_SECRET=sandbox RELOADLY_SANDBOX=true DB_CONNECTION=sqlite DB_DATABASE=/tmp/mockdemo.sqlite php scripts/mockups/seed-gift-demo.php
 * Nothing here contacts a provider, and no order or code is created.
 */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\GiftCardProduct;
use App\Support\FeatureFlags;

if (config('database.default') !== 'sqlite') { fwrite(STDERR, "Refusing: not a sqlite demo database.\n"); exit(1); }

$names = ['google-play' => 'Google Play', 'disney-plus' => 'Disney+', 'doordash' => 'DoorDash', 'playstation' => 'PlayStation', 'ikea' => 'IKEA', 'airbnb' => 'Airbnb', 'nike' => 'Nike', 'adidas' => 'adidas'];
$brands = json_decode(file_get_contents(__DIR__.'/data/gift-brands.json'), true);
foreach ($brands as $i => $b) {
    GiftCardProduct::updateOrCreate(['provider' => 'reloadly', 'provider_product_id' => 'demo-'.$b['key']], [
        'brand_key' => $b['key'], 'brand_name' => $names[$b['key']] ?? ucfirst($b['key']), 'country' => 'US', 'currency' => 'USD',
        'denomination_type' => 'FIXED', 'fixed_denominations' => $b['denoms'], 'min_amount' => null, 'max_amount' => null,
        'logo_url' => '/images/marketing/gift/brands/'.$b['key'].'.webp', 'brand_color' => $b['color'], 'category' => $b['category'],
        'required_fields' => [], 'redeem_instruction' => 'Redeem on the brand\'s own website or app.',
        'cost_meta' => ['discountPercentage' => 3, 'senderCurrencyCode' => 'USD', 'senderFee' => 0],
        'provider_enabled' => true, 'admin_enabled' => true, 'is_primary' => true, 'featured' => $i < 8, 'priceable' => true,
    ]);
}
FeatureFlags::setEnabled('naara_gift', true);
echo 'Gift demo catalogue: '.GiftCardProduct::count()." brands\n";
