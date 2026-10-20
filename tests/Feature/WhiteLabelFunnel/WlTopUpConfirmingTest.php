<?php

namespace Tests\Feature\WhiteLabelFunnel;

use App\Livewire\WlFunnelPay;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WlFunnelInstance;
use App\Services\WhiteLabel\Funnel\WlFunnel;
use App\Services\WhiteLabel\Funnel\WlFunnelStates;
use App\Support\FeatureFlags;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WhiteLabelLicensePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** After the card gateway returns the buyer to the purchase page, the page says it is confirming, polls, never offers a second top-up, and resolves on credit or timeout. */
class WlTopUpConfirmingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(WhiteLabelLicensePlanSeeder::class);
        FeatureFlags::setEnabled('wl_funnel', true);
    }

    private function buyer(): User
    {
        $u = User::factory()->create();
        $inst = app(WlFunnel::class)->start('basic', bin2hex(random_bytes(8)), $u);
        $inst->forceFill(['user_id' => $u->id, 'status' => WlFunnelStates::DRAFT_SAVED, 'draft' => ['brand_name' => 'Zenith', 'palette_key' => 'x', 'hosting_choice' => 'supreme_ideas_server', 'contact_preference' => 'notification', 'billing_name' => 'Zed', 'billing_email' => 'z@example.com', 'plan_key' => 'basic']])->save();

        return $u;
    }

    private function marker(int $age = 5): array
    {
        return ['gateway' => 'paystack', 'reference' => 'NAARA-abc', 'amount' => 25.0, 'currency' => 'USD', 'started_at' => time() - $age];
    }

    public function test_a_returning_buyer_sees_the_confirming_note_and_no_second_top_up(): void
    {
        $u = $this->buyer();
        session(['pending_topup' => $this->marker()]);
        Livewire::actingAs($u)->test(WlFunnelPay::class)
            ->assertSee('Confirming your payment')->assertSeeHtml('data-wl-pending')->assertDontSeeHtml('data-wl-topup');
    }

    public function test_it_clears_and_toasts_the_moment_the_credit_lands(): void
    {
        $u = $this->buyer();
        session(['pending_topup' => $this->marker()]);
        $c = Livewire::actingAs($u)->test(WlFunnelPay::class)->assertSeeHtml('data-wl-pending');
        WalletTransaction::forceCreate(['user_id' => $u->id, 'type' => 'credit', 'currency' => 'USD', 'amount' => 25, 'balance_before' => 0, 'balance_after' => 25, 'reference' => 'topup:paystack:NAARA-abc', 'description' => 'top-up']);
        $c->call('checkPendingTopUp')->assertDispatched('nx-toast')->assertDontSeeHtml('data-wl-pending');
        $this->assertNull(session('pending_topup'));
    }

    public function test_a_stale_marker_times_out_with_an_honest_message_and_the_buyer_can_cancel(): void
    {
        $u = $this->buyer();
        session(['pending_topup' => $this->marker(700)]);
        Livewire::actingAs($u)->test(WlFunnelPay::class)->assertDontSeeHtml('data-wl-pending')->assertSee('not received confirmation');
        session(['pending_topup' => $this->marker()]);
        Livewire::actingAs($u)->test(WlFunnelPay::class)->assertSeeHtml('data-wl-pending')->call('cancelPendingTopUp')->assertDontSeeHtml('data-wl-pending');
        $this->assertNull(session('pending_topup'));
    }
}
