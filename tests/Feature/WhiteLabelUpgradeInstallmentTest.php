<?php

namespace Tests\Feature;

use App\Console\Commands\WhiteLabelUpgradeInstallmentsCommand;
use App\Exceptions\LicenseActivationException;
use App\Livewire\Admin\WhiteLabelRegistry;
use App\Livewire\MerchantWhiteLabel;
use App\Models\Merchant;
use App\Models\User;
use App\Models\WhiteLabelInstance;
use App\Notifications\WhiteLabelUpgradeDeadlineSetNotification;
use App\Notifications\WhiteLabelUpgradeDueSoonNotification;
use App\Notifications\WhiteLabelUpgradeMissedDeadlineNotification;
use App\Notifications\WhiteLabelUpgradeProposedNotification;
use App\Notifications\WhiteLabelUpgradeSucceededNotification;
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
 * Content/billing/hot-menu blueprint Phase F2 — the Basic-to-Extended
 * installment path: a merchant proposes a timeframe (informational only), a
 * super-admin confirms the actual enforced deadline, and a scheduled sweep
 * attempts the charge once it's due. Owner-chosen policy on a missed
 * deadline: revert to standard Basic terms — no suspension, no grace period.
 */
class WhiteLabelUpgradeInstallmentTest extends TestCase
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

    /** An active, Normal-tier instance priced at $1,500 — $3,500 short of the cheapest Extended plan ($5,000). */
    private function activeNormalInstance(Merchant $merchant): WhiteLabelInstance
    {
        // Fund the $1,500 activation charge separately from whatever the test
        // funded the merchant with for the upgrade balance itself.
        app(WalletService::class)->credit($merchant->owner, 1500, 'USD');

        $instance = app(WhiteLabelLicenseService::class)->register([
            'brand_name' => 'Biz', 'contact_email' => 'biz@test.co',
            'merchant_id' => $merchant->id, 'owner_user_id' => $merchant->owner_user_id,
            'acquisition_method' => WhiteLabelInstance::ACQUISITION_MERCHANT_SELF_SERVICE,
            'requested_tier' => WhiteLabelInstance::TIER_NORMAL,
        ]);
        $instance->forceFill(['price_usd' => 1500.00])->save();
        app(WhiteLabelLicenseService::class)->approveAndActivate($instance->fresh());

        return $instance->fresh();
    }

    public function test_proposing_an_upgrade_notifies_admins_and_stores_the_proposal(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $superAdmin = $this->superAdmin();
        $merchant = $this->merchant();
        $instance = $this->activeNormalInstance($merchant);

        Livewire::actingAs($merchant->owner)
            ->test(MerchantWhiteLabel::class)
            ->set('upgradeProposedDays', '45')
            ->call('proposeUpgrade');

        $instance->refresh();
        $this->assertNotNull($instance->upgrade_target_plan_id);
        $this->assertSame(3500.0, (float) $instance->upgrade_balance_due);
        $this->assertSame(45, $instance->upgrade_proposed_days);
        $this->assertNull($instance->upgrade_deadline_at);

        Notification::assertSentTo($admin, WhiteLabelUpgradeProposedNotification::class);
        Notification::assertSentTo($superAdmin, WhiteLabelUpgradeProposedNotification::class);
    }

    public function test_a_plain_admin_cannot_confirm_an_upgrade_deadline(): void
    {
        $merchant = $this->merchant();
        $instance = $this->activeNormalInstance($merchant);
        app(WhiteLabelLicenseService::class)->proposeUpgrade($instance, 30);

        Livewire::actingAs($this->admin())
            ->test(WhiteLabelRegistry::class)
            ->set('deadlineInputs.'.$instance->id, now()->addDays(30)->toDateString())
            ->call('setUpgradeDeadline', $instance->id)
            ->assertStatus(403);

        $this->assertNull($instance->fresh()->upgrade_deadline_at);
    }

    public function test_a_super_admin_confirming_a_deadline_notifies_the_merchant(): void
    {
        Notification::fake();
        $merchant = $this->merchant();
        $instance = $this->activeNormalInstance($merchant);
        app(WhiteLabelLicenseService::class)->proposeUpgrade($instance, 30);
        $deadline = now()->addDays(30)->toDateString();

        Livewire::actingAs($this->superAdmin())
            ->test(WhiteLabelRegistry::class)
            ->set('deadlineInputs.'.$instance->id, $deadline)
            ->call('setUpgradeDeadline', $instance->id);

        $instance->refresh();
        $this->assertNotNull($instance->upgrade_deadline_at);
        $this->assertSame($deadline, $instance->upgrade_deadline_at->toDateString());

        Notification::assertSentTo($merchant->owner, WhiteLabelUpgradeDeadlineSetNotification::class);
    }

    public function test_confirming_a_deadline_with_no_pending_proposal_fails_cleanly(): void
    {
        $merchant = $this->merchant();
        $instance = $this->activeNormalInstance($merchant);

        $this->expectException(LicenseActivationException::class);
        app(WhiteLabelLicenseService::class)->setUpgradeDeadline($instance, now()->addDays(10));
    }

    public function test_the_scheduled_command_sends_one_advance_notice_and_never_a_second(): void
    {
        Notification::fake();
        $merchant = $this->merchant();
        $instance = $this->activeNormalInstance($merchant);
        app(WhiteLabelLicenseService::class)->proposeUpgrade($instance, 30);
        app(WhiteLabelLicenseService::class)->setUpgradeDeadline($instance, now()->addDays(2));

        $this->artisan(WhiteLabelUpgradeInstallmentsCommand::class, ['--notice-days' => 3])->assertSuccessful();
        $this->assertNotNull($instance->fresh()->upgrade_notice_sent_at);
        Notification::assertSentToTimes($merchant->owner, WhiteLabelUpgradeDueSoonNotification::class, 1);

        $this->artisan(WhiteLabelUpgradeInstallmentsCommand::class, ['--notice-days' => 3])->assertSuccessful();
        Notification::assertSentToTimes($merchant->owner, WhiteLabelUpgradeDueSoonNotification::class, 1);
    }

    public function test_the_scheduled_command_charges_a_due_deadline_and_upgrades_to_extended(): void
    {
        Notification::fake();
        $merchant = $this->merchant(fund: 5000);
        $instance = $this->activeNormalInstance($merchant);
        app(WhiteLabelLicenseService::class)->proposeUpgrade($instance, 30);
        app(WhiteLabelLicenseService::class)->setUpgradeDeadline($instance, now()->subDay());

        $this->artisan(WhiteLabelUpgradeInstallmentsCommand::class)->assertSuccessful();

        $instance->refresh();
        $this->assertSame(WhiteLabelInstance::TIER_EXTENDED, $instance->tier);
        $this->assertNull($instance->upgrade_target_plan_id);
        $this->assertNull($instance->upgrade_balance_due);
        $this->assertNull($instance->upgrade_deadline_at);
        $this->assertSame('1500.0000', (string) $merchant->owner->wallet->fresh()->usd_balance);

        Notification::assertSentTo($merchant->owner, WhiteLabelUpgradeSucceededNotification::class);
    }

    public function test_a_missed_deadline_reverts_to_standard_basic_terms_with_no_suspension(): void
    {
        Notification::fake();
        $merchant = $this->merchant(fund: 100); // far short of the $3,500 balance
        $instance = $this->activeNormalInstance($merchant);
        app(WhiteLabelLicenseService::class)->proposeUpgrade($instance, 30);
        app(WhiteLabelLicenseService::class)->setUpgradeDeadline($instance, now()->subDay());

        $this->artisan(WhiteLabelUpgradeInstallmentsCommand::class)->assertSuccessful();

        $instance->refresh();
        $this->assertSame(WhiteLabelInstance::TIER_NORMAL, $instance->tier, 'a missed deadline must never upgrade the tier');
        $this->assertSame(WhiteLabelInstance::ACTIVE, $instance->status, 'a missed deadline must never suspend the instance');
        $this->assertNull($instance->upgrade_target_plan_id, 'the proposal must be cleared, not left stuck');
        $this->assertNull($instance->upgrade_deadline_at);
        $this->assertSame('100.0000', (string) $merchant->owner->wallet->fresh()->usd_balance, 'a failed charge must never touch the balance');

        Notification::assertSentTo($merchant->owner, WhiteLabelUpgradeMissedDeadlineNotification::class);
    }

    public function test_paying_the_balance_immediately_clears_any_pending_installment_proposal(): void
    {
        $merchant = $this->merchant(fund: 5000);
        $instance = $this->activeNormalInstance($merchant);
        app(WhiteLabelLicenseService::class)->proposeUpgrade($instance, 30);
        app(WhiteLabelLicenseService::class)->setUpgradeDeadline($instance, now()->addDays(30));

        app(WhiteLabelLicenseService::class)->payBalanceAndUpgrade($instance->fresh(), $merchant->owner, 3500.0);

        $instance->refresh();
        $this->assertSame(WhiteLabelInstance::TIER_EXTENDED, $instance->tier);
        $this->assertNull($instance->upgrade_target_plan_id);
        $this->assertNull($instance->upgrade_deadline_at);
    }
}
