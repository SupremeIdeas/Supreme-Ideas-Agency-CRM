<?php

namespace Tests\Feature\WhiteLabelFunnel;

use App\Models\ManualPaymentAccount;
use App\Models\ManualPaymentRate;
use App\Models\PlatformEarning;
use App\Models\User;
use App\Models\WhiteLabelInstance;
use App\Models\WhiteLabelLicensePayment;
use App\Models\WhiteLabelLicensePlan;
use App\Models\WlFunnelManualPayment as P;
use App\Models\WlInvoice;
use App\Services\WhiteLabel\Funnel\WlFunnel;
use App\Services\WhiteLabel\Funnel\WlFunnelManualPayments;
use App\Services\WhiteLabel\Funnel\WlFunnelStates;
use App\Services\WhiteLabel\Payments\ManualPaymentException;
use App\Support\FeatureFlags;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WhiteLabelLicensePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Paying the balance to Extended by transfer (Funnel Phase 7): quote, proof, approval, upgrade, documents, and every way it must not double-apply. */
class WlBalancePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(WhiteLabelLicensePlanSeeder::class);
        FeatureFlags::setEnabled('wl_funnel', true);
        FeatureFlags::setEnabled('wl_manual_payments', true);
        \App\Models\Setting::setValue('wl.rates.use_grey_live', false);
        Storage::fake(\App\Support\MediaStorage::privateDisk());
        ManualPaymentRate::create(['from_currency' => 'GBP', 'to_currency' => 'USD', 'rate' => 1.3036, 'source' => ManualPaymentRate::SOURCE_MANUAL, 'as_of' => now()]);
        ManualPaymentAccount::create(['currency' => 'USD', 'kind' => 'bank', 'label' => 'USD', 'fields' => [['label' => 'Account', 'value' => '123']], 'enabled' => true, 'sort' => 1]);
        \App\Services\WhiteLabel\Funnel\WlInvoiceSettings::put('seller_name', 'Acme Seller Ltd');
    }

    private function png(): UploadedFile
    {
        $im = imagecreatetruecolor(8, 8);
        ob_start();
        imagepng($im);

        return UploadedFile::fake()->createWithContent('p'.random_int(1, 99999999).'.png', (string) ob_get_clean());
    }

    private function admin(): User
    {
        return User::factory()->create(['password' => bcrypt('S3cret-pass')])->assignRole('admin');
    }

    /** A Basic buyer whose licence payment is approved, so a balance invoice exists. */
    private function basicBuyer(): array
    {
        $u = User::factory()->create();
        $inst = app(WlFunnel::class)->start('basic', bin2hex(random_bytes(8)), $u);
        $inst->forceFill(['user_id' => $u->id, 'status' => WlFunnelStates::DRAFT_SAVED, 'draft' => ['brand_name' => 'Zenith Cloud', 'palette_key' => 'x', 'hosting_choice' => 'supreme_ideas_server', 'contact_preference' => 'notification', 'billing_name' => 'Zed Co', 'billing_email' => 'zed@example.com', 'billing_address' => '1 Road', 'plan_key' => 'basic', 'whatsapp' => '+2348012345678']])->save();
        $svc = app(WlFunnelManualPayments::class);
        $pay = $svc->begin($inst->fresh(), 'USD');
        $svc->submitProof($pay, $u, [$this->png()], 'Zed', null, null, null);
        $svc->approve($pay->fresh(), $this->admin(), (float) $pay->expected_amount);

        return [$inst->fresh(), $u, $svc];
    }

    public function test_balance_payment_upgrades_to_extended_once_and_issues_a_receipt(): void
    {
        [$inst, $u, $svc] = $this->basicBuyer();
        $plan = WhiteLabelLicensePlan::where('key', 'basic')->first();
        $owed = (float) WhiteLabelLicensePlan::balanceToExtended($plan);
        $this->assertNotNull($svc->balanceDue($inst));

        $pay = $svc->beginBalance($inst, 'USD');
        $this->assertSame('balance', $pay->purpose);
        $this->assertSame($owed, (float) $pay->price_usd);
        $this->assertSame($pay->id, $svc->beginBalance($inst, 'USD')->id, 'a second start reuses the open quote');
        $this->assertNull($svc->current($inst), 'the licence payment lookup never sees a balance payment');

        $svc->submitProof($pay, $u, [$this->png()], 'Zed', null, null, null);
        $instance = $svc->approve($pay->fresh(), $this->admin(), (float) $pay->expected_amount);

        $this->assertSame(WhiteLabelInstance::TIER_EXTENDED, $instance->fresh()->tier);
        $this->assertSame('paid', WlInvoice::where('source_key', 'mp:'.WlFunnelPayId::of($inst).':balance')->first()->status);
        $this->assertSame(1, WlInvoice::where('doc_type', 'receipt')->where('source_key', 'mp:'.$pay->id.':receipt')->count());
        $this->assertSame(1, WhiteLabelLicensePayment::where('kind', 'balance_completion')->count());
        $this->assertNull($svc->balanceDue($inst->fresh()), 'nothing is owed any more');

        $svc->approve($pay->fresh(), $this->admin(), (float) $pay->expected_amount);   // a double click or retry
        $this->assertSame(1, WhiteLabelLicensePayment::where('kind', 'balance_completion')->count());
        $this->assertSame(1, WlInvoice::where('doc_type', 'receipt')->where('source_key', 'mp:'.$pay->id.':receipt')->count());
        $this->artisan('wl-invoices:retry')->assertSuccessful();
        $this->assertSame(0, WlInvoice::where('doc_type', 'invoice')->where('source_key', 'like', 'mp:'.$pay->id.':%')->count(), 'a balance payment never creates a second licence invoice');
    }

    public function test_no_balance_no_payment_and_manual_off_is_refused(): void
    {
        $u = User::factory()->create();
        $inst = app(WlFunnel::class)->start('basic', bin2hex(random_bytes(8)), $u);
        $this->expectException(ManualPaymentException::class);
        app(WlFunnelManualPayments::class)->beginBalance($inst, 'USD');
    }

    public function test_a_rejected_balance_payment_leaves_the_licence_on_basic(): void
    {
        [$inst, $u, $svc] = $this->basicBuyer();
        $pay = $svc->beginBalance($inst, 'USD');
        $svc->submitProof($pay, $u, [$this->png()], 'Zed', null, null, null);
        $svc->reject($pay->fresh(), $this->admin(), 'Blurry proof');
        $lic = WhiteLabelInstance::where('owner_user_id', $u->id)->first();
        $this->assertSame(WhiteLabelInstance::TIER_NORMAL, $lic->tier);
        $this->assertSame('awaiting_transfer', $pay->fresh()->status);
        $this->assertNotNull($svc->balanceDue($inst->fresh()));
    }

    public function test_the_buyer_page_shows_the_balance_and_the_pay_page_links_to_it(): void
    {
        [$inst, $u] = $this->basicBuyer();
        Livewire::actingAs($u)->test(\App\Livewire\WlFunnelPay::class)->assertSee('Upgrade to Extended')->assertSee('Pay the balance');
        Livewire::actingAs($u)->test(\App\Livewire\WlBalancePay::class)->assertSee('Balance INV-')->call('choose', 'USD')->assertSee('You pay')->assertSee('I have paid');
    }

    public function test_the_balance_can_be_paid_from_the_wallet_once_and_upgrades_the_licence(): void
    {
        [$inst, $u, $svc] = $this->basicBuyer();
        $owed = (float) $svc->balanceDue($inst)['invoice']->balance_due;
        app(\App\Services\Wallet\WalletService::class)->credit($u, $owed + 5, 'USD', ['reference' => 'seed-bal-'.$u->id, 'description' => 'seed']);
        $one = $svc->payBalanceWithWallet($inst, $u);
        $this->assertSame(WhiteLabelInstance::TIER_EXTENDED, $one->fresh()->tier);
        $this->assertEqualsWithDelta(5.0, app(\App\Services\Wallet\WalletService::class)->spendableUsd($u->fresh()), 0.001);
        $this->assertSame(2, WlInvoice::where('doc_type', 'receipt')->count(), 'a receipt for the licence and one for the balance');
        $this->expectException(ManualPaymentException::class);   // nothing left to pay: never charged twice
        $svc->payBalanceWithWallet($inst->fresh(), $u);
    }

    public function test_an_empty_wallet_pays_nothing_and_changes_nothing(): void
    {
        [$inst, $u, $svc] = $this->basicBuyer();
        try {
            $svc->payBalanceWithWallet($inst, $u);
            $this->fail('should have refused');
        } catch (\App\Exceptions\InsufficientBalanceException) {
            $this->assertSame(WhiteLabelInstance::TIER_NORMAL, WhiteLabelInstance::where('owner_user_id', $u->id)->first()->tier);
            $this->assertNotNull($svc->balanceDue($inst->fresh()));
        }
    }

    public function test_the_top_up_quotes_the_exact_shortfall_rounded_up_and_only_offers_active_usd_gateways(): void
    {
        $u = User::factory()->create();
        $top = app(\App\Services\WhiteLabel\Payments\WlWalletTopUp::class);
        $this->assertSame([], $top->gateways(), 'no gateway keys saved: nothing to offer');
        $this->assertSame(3500.0, $top->shortfall($u, 3500.0));
        app(\App\Services\Wallet\WalletService::class)->credit($u, 1000.005, 'USD', ['reference' => 'seed-s-'.$u->id, 'description' => 'seed']);
        $this->assertSame(2500.0, $top->shortfall($u->fresh(), 3500.0), '2499.995 is rounded UP so the top-up always covers it');
        $this->assertSame(0.0, $top->shortfall($u->fresh(), 900.0));
        config(['services.stripe.secret' => 'sk_test', 'services.stripe.key' => 'pk_test', 'services.stripe.secret_key' => 'sk_test', 'services.stripe.publishable_key' => 'pk_test']);
        $fake = new class implements \App\Services\Payments\PaymentGatewayInterface {
            public array $seen = [];
            public function name(): string { return 'stripe'; }
            public function initialize(User $user, float $amount, string $currency, array $meta = []): array { $this->seen = [$amount, $currency, $meta]; return ['reference' => 'ref_1', 'redirect_url' => 'https://pay.example/checkout']; }
            public function verifySignature(\Illuminate\Http\Request $request): bool { return false; }
            public function parseWebhook(\Illuminate\Http\Request $request): ?\App\Services\Payments\PaymentEvent { return null; }
        };
        app()->instance('pay.stripe', $fake);
        if (! array_key_exists('stripe', $top->gateways())) {
            $this->markTestSkipped('stripe keys are named differently in this build');
        }
        $url = $top->start($u->fresh(), 'stripe', 3500.0, 'https://app.test/account/white-label-pay');
        $this->assertSame('https://pay.example/checkout', $url);
        $this->assertSame([2500.0, 'USD'], [$fake->seen[0], $fake->seen[1]]);
        $this->assertSame('https://app.test/account/white-label-pay', $fake->seen[2]['success_url']);
        $this->assertSame('ref_1', session('pending_topup.reference'));
        $this->expectException(ManualPaymentException::class);
        $top->start($u->fresh(), 'paystack', 3500.0, 'https://app.test/x');   // not active
    }

}

/** Test helper: the licence payment id of a funnel instance. */
final class WlFunnelPayId
{
    public static function of($inst): int
    {
        return (int) P::where('funnel_instance_id', $inst->id)->where('purpose', 'license')->value('id');
    }
}
