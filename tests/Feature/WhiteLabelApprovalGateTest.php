<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientBalanceException;
use App\Livewire\Admin\WhiteLabelRegistry;
use App\Livewire\MerchantWhiteLabel;
use App\Models\Merchant;
use App\Models\User;
use App\Models\WhiteLabelInstance;
use App\Notifications\WhiteLabelActivatedNotification;
use App\Notifications\WhiteLabelApprovalFailedNotification;
use App\Notifications\WhiteLabelApprovalNeedsRetryNotification;
use App\Notifications\WhiteLabelRequestNeedsReviewNotification;
use App\Notifications\WhiteLabelRequestSubmittedNotification;
use App\Services\Updater\WhiteLabelLicenseService;
use App\Services\Wallet\WalletService;
use App\Support\MailSettings;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WhiteLabelLicensePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Content/billing/hot-menu blueprint Phase F1 — a self-service white-label
 * request is never charged at request time; only a super-admin's explicit
 * approveAndActivate() moves money. Covers: request-time notifications,
 * approval success, approval failure -> APPROVED_AWAITING_FUNDS + retry, and
 * that the merchant no longer has any self-serve payment trigger of their own.
 */
class WhiteLabelApprovalGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(WhiteLabelLicensePlanSeeder::class);
        MailSettings::save([
            'mailer' => 'smtp', 'smtp_host' => 'smtp.example.com', 'smtp_port' => '587',
            'from_address' => 'hello@naara.test', 'from_name' => 'NaaraSim',
        ]);
    }

    private function superAdmin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('super_admin');

        return $u;
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    private function merchant(float $fund = 0): Merchant
    {
        $owner = User::factory()->create();
        if ($fund > 0) {
            app(WalletService::class)->credit($owner, $fund, 'USD');
        }

        return Merchant::create([
            'owner_user_id' => $owner->id, 'business_name' => 'Biz', 'slug' => 'biz-'.$owner->id,
            'status' => Merchant::ACTIVE, 'tier' => Merchant::TIER_V2,
        ]);
    }

    private function pricedInstance(Merchant $merchant, float $price = 1500.00): WhiteLabelInstance
    {
        $instance = app(WhiteLabelLicenseService::class)->register([
            'brand_name' => $merchant->business_name, 'contact_email' => 'biz@test.co',
            'merchant_id' => $merchant->id, 'owner_user_id' => $merchant->owner_user_id,
            'acquisition_method' => WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE,
            'requested_tier' => WhiteLabelInstance::TIER_NORMAL,
        ]);
        $instance->forceFill(['price_usd' => $price])->save();

        return $instance;
    }

    public function test_registering_notifies_the_merchant_and_every_admin_but_charges_nothing(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $superAdmin = $this->superAdmin();
        $merchant = $this->merchant();

        $instance = app(WhiteLabelLicenseService::class)->register([
            'brand_name' => 'New Co', 'contact_email' => 'nc@co.test',
            'merchant_id' => $merchant->id, 'owner_user_id' => $merchant->owner_user_id,
            'acquisition_method' => WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE,
            'requested_tier' => WhiteLabelInstance::TIER_NORMAL,
        ]);

        $this->assertSame(WhiteLabelInstance::PENDING, $instance->status);
        $this->assertNull($instance->license_key);
        Notification::assertSentTo($merchant->owner, WhiteLabelRequestSubmittedNotification::class);
        Notification::assertSentTo($admin, WhiteLabelRequestNeedsReviewNotification::class);
        Notification::assertSentTo($superAdmin, WhiteLabelRequestNeedsReviewNotification::class);
    }

    public function test_an_admin_provisioned_instance_sends_no_pending_approval_notifications(): void
    {
        Notification::fake();
        $this->admin();

        app(WhiteLabelLicenseService::class)->register(['brand_name' => 'Admin Flow', 'contact_email' => 'a@a.test']);

        Notification::assertNothingSent();
    }

    public function test_a_plain_admin_cannot_approve_and_activate(): void
    {
        $merchant = $this->merchant(fund: 1500);
        $instance = $this->pricedInstance($merchant);

        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->call('approveAndActivate', $instance->id)
            ->assertStatus(403);

        $this->assertSame(WhiteLabelInstance::PENDING, $instance->fresh()->status);
    }

    public function test_a_super_admin_approving_charges_the_merchant_issues_the_license_and_notifies_them(): void
    {
        Notification::fake();
        $merchant = $this->merchant(fund: 1500);
        $instance = $this->pricedInstance($merchant);

        Livewire::actingAs($this->superAdmin())
            ->test(WhiteLabelRegistry::class)
            ->call('approveAndActivate', $instance->id);

        $instance->refresh();
        $this->assertSame(WhiteLabelInstance::ACTIVE, $instance->status);
        $this->assertNotNull($instance->license_key);
        $this->assertSame('0.0000', (string) $merchant->owner->wallet->fresh()->usd_balance);
        Notification::assertSentTo($merchant->owner, WhiteLabelActivatedNotification::class);
    }

    public function test_approval_with_insufficient_funds_moves_to_awaiting_funds_and_notifies_both_sides(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $merchant = $this->merchant(fund: 100); // way short of $1500
        $instance = $this->pricedInstance($merchant);

        Livewire::actingAs($this->superAdmin())
            ->test(WhiteLabelRegistry::class)
            ->call('approveAndActivate', $instance->id);

        $instance->refresh();
        $this->assertSame(WhiteLabelInstance::APPROVED_AWAITING_FUNDS, $instance->status);
        $this->assertNull($instance->license_key, 'no license may be issued when the charge failed');
        $this->assertSame('100.0000', (string) $merchant->owner->wallet->fresh()->usd_balance, 'a failed charge must never touch the balance');

        Notification::assertSentTo($merchant->owner, WhiteLabelApprovalFailedNotification::class, function ($notification) {
            return $notification->shortfallUsd === 1400.0;
        });
        Notification::assertSentTo($admin, WhiteLabelApprovalNeedsRetryNotification::class);
    }

    public function test_re_approving_after_a_top_up_succeeds_from_the_awaiting_funds_state(): void
    {
        $merchant = $this->merchant(fund: 100);
        $instance = $this->pricedInstance($merchant);

        try {
            app(WhiteLabelLicenseService::class)->approveAndActivate($instance);
        } catch (InsufficientBalanceException) {
            // expected first attempt
        }
        $this->assertSame(WhiteLabelInstance::APPROVED_AWAITING_FUNDS, $instance->fresh()->status);

        app(WalletService::class)->credit($merchant->owner, 1400, 'USD');

        Livewire::actingAs($this->superAdmin())
            ->test(WhiteLabelRegistry::class)
            ->call('approveAndActivate', $instance->id);

        $this->assertSame(WhiteLabelInstance::ACTIVE, $instance->fresh()->status);
        $this->assertNotNull($instance->fresh()->license_key);
    }

    public function test_the_merchant_dashboard_has_no_self_serve_pay_action_left(): void
    {
        $merchant = $this->merchant(fund: 1500);
        $this->pricedInstance($merchant);

        $component = Livewire::actingAs($merchant->owner)->test(MerchantWhiteLabel::class);

        $this->assertFalse(method_exists($component->instance(), 'payNow'), 'the merchant self-pay action must no longer exist');
    }
}
