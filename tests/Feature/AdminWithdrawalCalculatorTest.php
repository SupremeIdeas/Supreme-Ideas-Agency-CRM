<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\MerchantEarning;
use App\Models\OrderLog;
use App\Models\Partner;
use App\Models\PartnerEarning;
use App\Models\PayoutAccount;
use App\Models\ReferralEarning;
use App\Models\SmsOrder;
use App\Models\StaffEarning;
use App\Models\User;
use App\Models\WhiteLabelInstance;
use App\Services\Platform\AdminWithdrawalCalculator;
use App\Services\Platform\PlatformWithdrawalService;
use App\Services\Updater\WhiteLabelLicenseService;
use App\Services\Wallet\WalletService;
use App\Support\PayoutSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Oversight/Withdrawal blueprint Phase B — the deliverable this blueprint
 * explicitly demands: the computed withdrawable-balance formula checked
 * against real, hand-summed numbers, not just "the screen renders
 * something plausible." Every figure below is chosen so the expected
 * total can be verified with a calculator, independent of the code.
 */
class AdminWithdrawalCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function orderLog(float $profit): void
    {
        OrderLog::create([
            'user_id' => User::factory()->create()->id,
            'provider' => 'esimgo',
            'provider_cost' => 10.00,
            'charged_to_user' => 10.00 + $profit,
            'profit' => $profit,
            'profit_pct' => 0,
            'result' => 'success',
        ]);
    }

    private function smsOrder(float $profit): void
    {
        SmsOrder::create([
            'user_id' => User::factory()->create()->id,
            'provider' => 'getatext',
            'service_id' => 'whatsapp',
            'service_name' => 'WhatsApp',
            'country' => 'US',
            'type' => 'otp',
            'status' => 'completed',
            'provider_cost' => 1.00,
            'charged_to_user' => 1.00 + $profit,
            'profit' => $profit,
        ]);
    }

    private function staffAccrual(float $amount): void
    {
        $staff = User::factory()->create();
        StaffEarning::create([
            'user_id' => $staff->id, 'type' => StaffEarning::ACCRUAL,
            'amount' => $amount, 'balance_after' => $amount, 'currency' => 'USD',
            'reference' => 'test-staff-'.uniqid(),
        ]);
    }

    private function partnerAccrual(float $amount): void
    {
        $partner = Partner::create(['owner_user_id' => User::factory()->create()->id, 'status' => Partner::ACTIVE, 'profit_share_pct' => 5]);
        PartnerEarning::create([
            'partner_id' => $partner->id, 'type' => PartnerEarning::ACCRUAL,
            'amount' => $amount, 'balance_after' => $amount, 'currency' => 'USD',
            'reference' => 'test-partner-'.uniqid(),
        ]);
    }

    private function merchantAccrual(float $amount): void
    {
        $merchant = Merchant::create([
            'owner_user_id' => User::factory()->create()->id, 'business_name' => 'M '.uniqid(),
            'slug' => 'm-'.uniqid(), 'status' => Merchant::ACTIVE, 'tier' => Merchant::TIER_STANDARD, 'reseller_margin_pct' => 10,
        ]);
        MerchantEarning::create([
            'merchant_id' => $merchant->id, 'type' => MerchantEarning::ACCRUAL,
            'amount' => $amount, 'balance_after' => $amount, 'currency' => 'USD',
            'reference' => 'test-merchant-'.uniqid(),
        ]);
    }

    private function referralAccrual(float $amount): void
    {
        $referrer = User::factory()->create();
        ReferralEarning::create([
            'user_id' => $referrer->id, 'type' => ReferralEarning::ACCRUAL,
            'amount' => $amount, 'balance_after' => $amount, 'currency' => 'USD',
            'reference' => 'test-referral-'.uniqid(),
        ]);
    }

    /** Accrue real White-Label earnings the same way a license sale would. */
    private function seedWhiteLabelSale(float $amount): void
    {
        $payer = User::factory()->create();
        app(WalletService::class)->credit($payer, $amount, 'USD');
        $instance = app(WhiteLabelLicenseService::class)->register(['brand_name' => 'X', 'contact_email' => 'x@x.test', 'owner_user_id' => $payer->id]);
        $instance->forceFill(['price_usd' => $amount, 'requested_tier' => WhiteLabelInstance::TIER_NORMAL])->save();
        app(WhiteLabelLicenseService::class)->approveAndActivate($instance);
    }

    public function test_the_formula_matches_a_hand_summed_scenario(): void
    {
        // Raw core margin: $1,000 (eSIM) + $200 (Numbers) = $1,200
        $this->orderLog(700.00);
        $this->orderLog(300.00);
        $this->smsOrder(200.00);

        // Four outstanding shares: $150 staff + $80 partner + $120 merchant + $50 referral = $400
        $this->staffAccrual(150.00);
        $this->partnerAccrual(80.00);
        $this->merchantAccrual(120.00);
        $this->referralAccrual(50.00);

        // White-Label revenue: $600, in full, on top.
        $this->seedWhiteLabelSale(600.00);

        // Expected: (1200 - 400) + 600 = 1400
        $calculator = app(AdminWithdrawalCalculator::class);
        $breakdown = $calculator->breakdown();

        $this->assertSame(1200.0, $breakdown['raw_core_margin']);
        $this->assertSame(150.0, $breakdown['staff_outstanding']);
        $this->assertSame(80.0, $breakdown['partner_outstanding']);
        $this->assertSame(120.0, $breakdown['merchant_outstanding']);
        $this->assertSame(50.0, $breakdown['referral_outstanding']);
        $this->assertSame(0.0, $breakdown['already_withdrawn_core']);
        $this->assertSame(800.0, $breakdown['core_margin_pool']); // 1200 - 400
        $this->assertSame(600.0, $breakdown['white_label_balance']);
        $this->assertSame(1400.0, $breakdown['total_withdrawable']); // 800 + 600

        $this->assertSame(1400.0, $calculator->withdrawableBalance());
    }

    public function test_a_paid_out_share_permanently_reduces_the_pool_even_after_payout(): void
    {
        $this->orderLog(1000.00);
        $this->staffAccrual(300.00);

        $calculator = app(AdminWithdrawalCalculator::class);
        $this->assertSame(700.0, $calculator->coreMarginPool()); // 1000 - 300

        // Staff getting PAID (their own ledger balance dropping to 0 via a
        // hold) must NOT hand that $300 back to admin — it already left the
        // building to staff's own account. Using ACCRUAL (not live balance)
        // for the subtraction is exactly what keeps this correct.
        $staff = StaffEarning::first()->user;
        app(\App\Services\Staff\StaffEarningsService::class)->hold($staff, 300.00, 'test-staff-payout');
        $this->assertSame(0.0, app(\App\Services\Staff\StaffEarningsService::class)->balance($staff));

        $this->assertSame(700.0, $calculator->coreMarginPool(), 'admin pool must not grow just because staff got paid');
    }

    public function test_a_withdrawal_larger_than_white_label_alone_splits_across_both_pools(): void
    {
        \App\Models\Setting::setValue(PayoutSettings::FLAG, true, 'payouts');

        $this->orderLog(1000.00); // core pool = 1000, no shares owed
        $this->seedWhiteLabelSale(200.00); // WL balance = 200

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $account = PayoutAccount::create([
            'user_id' => $admin->id, 'type' => 'bank', 'country' => 'US', 'currency' => 'USD',
            'bank_code' => '001', 'account_number' => '0123456789', 'account_name' => 'Supreme Ideas',
            'provider' => 'paystack', 'is_verified' => true, 'is_default' => true,
        ]);

        $this->assertSame(1200.0, app(PlatformWithdrawalService::class)->availableUsd());

        // Withdraw 500: 200 from WL (all of it) + 300 from core margin.
        app(PlatformWithdrawalService::class)->request($admin, $account, 500.0);

        $this->assertSame(0.0, app(\App\Services\Platform\PlatformEarningsService::class)->balance());
        $this->assertSame(700.0, app(AdminWithdrawalCalculator::class)->coreMarginPool()); // 1000 - 300 already withdrawn
        $this->assertSame(700.0, app(PlatformWithdrawalService::class)->availableUsd()); // 700 core + 0 WL left

        $requests = \App\Models\PayoutRequest::where('payee_type', 'admin')->orderBy('id')->get();
        $this->assertCount(2, $requests);
        $this->assertSame('platform_earnings', $requests[0]->source_bucket);
        $this->assertEquals(200.0, (float) $requests[0]->credit_amount);
        $this->assertSame(AdminWithdrawalCalculator::CORE_MARGIN_BUCKET, $requests[1]->source_bucket);
        $this->assertEquals(300.0, (float) $requests[1]->credit_amount);
    }

    public function test_withdrawing_more_than_the_composed_total_is_still_rejected(): void
    {
        \App\Models\Setting::setValue(PayoutSettings::FLAG, true, 'payouts');
        $this->orderLog(100.00);
        $this->seedWhiteLabelSale(50.00);

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $account = PayoutAccount::create([
            'user_id' => $admin->id, 'type' => 'bank', 'country' => 'US', 'currency' => 'USD',
            'bank_code' => '001', 'account_number' => '0123456789', 'account_name' => 'Supreme Ideas',
            'provider' => 'paystack', 'is_verified' => true, 'is_default' => true,
        ]);

        $this->expectException(\App\Services\Payouts\PayoutException::class);
        app(PlatformWithdrawalService::class)->request($admin, $account, 1000.0); // 150 available, way over
    }
}
