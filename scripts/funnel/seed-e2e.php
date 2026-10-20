<?php
// Seeds a scratch DB for scripts/funnel/e2e.mjs: roles, plans, flags, rates, receiving accounts, a buyer and an admin. Run: php scripts/funnel/seed-e2e.php (with the scratch DB env vars set).
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach ([\Database\Seeders\RoleSeeder::class, \Database\Seeders\WhiteLabelLicensePlanSeeder::class] as $s) { (new $s)->setContainer($app)->run(); }
\App\Support\FeatureFlags::setEnabled('wl_funnel', true);
\App\Support\FeatureFlags::setEnabled('wl_manual_payments', true);
\App\Models\Setting::setValue('wl.rates.use_grey_live', false);
\App\Models\ManualPaymentRate::updateOrCreate(['from_currency' => 'GBP', 'to_currency' => 'USD'], ['rate' => 1.3036, 'source' => \App\Models\ManualPaymentRate::SOURCE_MANUAL, 'as_of' => now()]);
if (! \App\Models\ManualPaymentAccount::where('currency', 'USD')->exists()) {
    foreach (['USD', 'GBP'] as $c) {
        \App\Models\ManualPaymentAccount::create(['currency' => $c, 'kind' => 'bank', 'network' => null, 'label' => "$c bank", 'fields' => [['label' => 'Account', 'value' => '1234567890'], ['label' => 'Bank', 'value' => 'Test Bank']], 'enabled' => true, 'sort' => 1]);
    }
}
$mk = function (string $email, string $role = null) {
    $u = \App\Models\User::firstOrCreate(['email' => $email], ['name' => 'E2E '.strtok($email, '@'), 'password' => bcrypt('Passw0rd!x'), 'email_verified_at' => now(), 'is_active' => true]);
    if ($role) { $u->assignRole($role); }
    return $u;
};
$buyer = $mk('wlbuyer@example.com'); \App\Models\WlFunnelInstance::where('user_id', $buyer->id)->get()->each(fn ($i) => $i->delete());   // every run starts a fresh purchase
 $mk('wladmin@example.com', 'super_admin');
echo "seeded\n";
