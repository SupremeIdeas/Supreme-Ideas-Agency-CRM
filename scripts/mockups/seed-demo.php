<?php
/**
 * Demo data for the marketing mockup screenshots. Run against a THROWAWAY database only (never production):
 *   DB_CONNECTION=sqlite DB_DATABASE=/tmp/mockdemo.sqlite php artisan migrate --force && php artisan db:seed --force
 *   DB_CONNECTION=sqlite DB_DATABASE=/tmp/mockdemo.sqlite php scripts/mockups/seed-demo.php
 * It invents one member ("Emily Carter", US), a wallet balance, a believable eSIM catalogue and one installed eSIM so the dashboard
 * screens that go into the marketing mockups look lived-in. Nothing here is a real person, price list or provider cost.
 */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\User;
use App\Models\UserWallet;
use Illuminate\Support\Facades\Hash;

if (! str_contains((string) config('database.connections.sqlite.database'), 'sqlite') && config('database.default') !== 'sqlite') {
    fwrite(STDERR, "Refusing: not a sqlite demo database.\n");
    exit(1);
}

$user = User::firstOrNew(['email' => 'emily@naara.test']);
$user->fill(['name' => 'Emily Carter', 'country_code' => 'US', 'display_currency' => 'USD'])->forceFill([
    'password' => Hash::make('password'), 'email_verified_at' => now(), 'is_active' => true,
])->save();
$wallet = UserWallet::firstOrCreate(['user_id' => $user->id]);
$wallet->forceFill(['usd_balance' => 48.20, 'ngn_balance' => 0, 'total_deposits' => 120, 'total_spent' => 71.80])->save();

$countries = [
    'US' => 'United States', 'GB' => 'United Kingdom', 'FR' => 'France', 'DE' => 'Germany', 'ES' => 'Spain', 'IT' => 'Italy',
    'TR' => 'Turkey', 'AE' => 'United Arab Emirates', 'ZA' => 'South Africa', 'KE' => 'Kenya', 'GH' => 'Ghana', 'EG' => 'Egypt',
    'MA' => 'Morocco', 'TH' => 'Thailand', 'JP' => 'Japan', 'CA' => 'Canada', 'NG' => 'Nigeria', 'SN' => 'Senegal',
];
$sizes = [[1024, 7, 3.90], [3072, 15, 8.40], [5120, 30, 12.90], [10240, 30, 21.50], [20480, 30, 34.00]];
foreach ($countries as $iso => $name) {
    foreach ($sizes as $i => [$mb, $days, $price]) {
        $plan = EsimPlan::updateOrCreate(['provider' => 'esimgo', 'provider_plan_id' => "demo-$iso-$mb"], [
            'name' => $name.' '.($mb / 1024).' GB '.$days.' Days', 'type' => 'data', 'coverage_type' => 'local', 'data_mb' => $mb,
            'validity_days' => $days, 'countries' => [$iso], 'cost_price_usd' => round($price * 0.6, 4), 'computed_retail_usd' => $price,
            'is_active' => true, 'is_featured' => $i === 2 && in_array($iso, ['US', 'GB', 'FR', 'TR', 'AE', 'ZA', 'JP', 'TH'], true), 'synced_at' => now(),
        ]);
    }
}
$plan = EsimPlan::where('provider_plan_id', 'demo-FR-5120')->first();
EsimOrder::updateOrCreate(['provider_order_ref' => 'demo-order-1'], [
    'user_id' => $user->id, 'plan_id' => $plan->id, 'provider' => 'esimgo', 'iccid' => '8944500102198304826', 'bundle_name' => $plan->name,
    'qr_code_url' => null, 'lpa_string' => 'LPA:1$rsp.naara.example$DEMO-4F7A-92C1', 'status' => 'active', 'activated_at' => now()->subDays(3),
    'expires_at' => now()->addDays(27), 'data_remaining_mb' => 3890, 'price_charged' => $plan->computed_retail_usd, 'wholesale_cost' => 7.74, 'currency' => 'USD',
]);
echo "Demo seeded: emily@naara.test / password\n";
