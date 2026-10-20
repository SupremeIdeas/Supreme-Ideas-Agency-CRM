<?php

namespace Tests\Feature\WhiteLabelFunnel;

use App\Exceptions\InsufficientBalanceException;
use App\Livewire\Admin\WlCustomOrdersAdmin;
use App\Livewire\WlCustomOrderRequest;
use App\Livewire\WlCustomOrderView;
use App\Models\PlatformEarning;
use App\Models\User;
use App\Models\WlCustomOrder as O;
use App\Services\Wallet\WalletService;
use App\Services\WhiteLabel\Funnel\WlCustomOrderException;
use App\Services\WhiteLabel\Funnel\WlCustomOrders;
use App\Support\FeatureFlags;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** White Label custom orders (Funnel Phase 8, MASTER-ONLY): request, quote, accept, pay, offline payment, ownership and the switch. */
class WlCustomOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        FeatureFlags::setEnabled('wl_custom_orders', true);
        Queue::fake();
    }

    private function user(): User
    {
        return User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
    }

    private function admin(): User
    {
        $a = $this->user();
        $a->assignRole('admin');

        return $a;
    }

    private function request(User $u, array $over = []): O
    {
        return app(WlCustomOrders::class)->request($u, $over + ['brand_name' => 'Zenith Cloud', 'contact_email' => 'z@example.com', 'modules' => ['theme', 'languages'], 'brief' => 'We sell eSIMs in five West African countries and want a French and Hausa storefront with our own theme.']);
    }

    private function fund(User $u, float $usd): void
    {
        app(WalletService::class)->credit($u, $usd, 'USD', ['reference' => 'seed:'.$u->id.':'.$usd, 'description' => 'seed']);
    }

    private function quoted(User $u, float $total = 1500): O
    {
        $o = $this->request($u);

        return app(WlCustomOrders::class)->quote($o, $this->admin(), [['label' => 'Custom theme', 'amount' => $total - 300], ['label' => 'French and Hausa copy', 'amount' => 300]], 'Thanks', 14);
    }

    public function test_the_whole_feature_is_a_plain_404_while_switched_off(): void
    {
        FeatureFlags::setEnabled('wl_custom_orders', false);
        $u = $this->user();
        $this->actingAs($u)->get('/account/white-label-custom')->assertNotFound();
        $this->expectException(WlCustomOrderException::class);
        $this->request($u);
    }

    public function test_a_buyer_requests_a_quote_with_validation_and_a_cap_on_open_requests(): void
    {
        $u = $this->user();
        $this->actingAs($u)->get('/account/white-label-custom')->assertOk()->assertSee('Custom White Label');
        Livewire::actingAs($u)->test(WlCustomOrderRequest::class)->set('brand_name', 'Zenith')->set('brief', 'short')->call('submit')->assertHasErrors(['modules', 'brief']);
        Livewire::actingAs($u)->test(WlCustomOrderRequest::class)->set('brand_name', 'Zenith Cloud')->set('modules', ['theme'])->set('brief', str_repeat('We need a branded storefront. ', 3))->call('submit')->assertHasNoErrors()->assertRedirect();
        $this->assertSame(1, O::query()->count());
        $this->assertSame(O::REQUESTED, O::first()->status);
        $this->assertMatchesRegularExpression('/^WLC-[A-Z0-9]{6}$/', O::first()->reference);
        $this->request($u);
        $this->request($u);
        $this->expectException(WlCustomOrderException::class);
        $this->request($u);   // a fourth open one
    }

    public function test_an_unknown_module_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->request($this->user(), ['modules' => ['hack']]);
    }

    public function test_the_total_is_summed_on_the_server_and_bad_lines_are_refused(): void
    {
        $o = $this->request($this->user());
        $svc = app(WlCustomOrders::class);
        $q = $svc->quote($o, $this->admin(), [['label' => 'A', 'amount' => '100.10'], ['label' => 'B', 'amount' => 49.9], ['label' => '', 'amount' => '']], null);
        $this->assertEquals(150.00, (float) $q->quote_total_usd);
        $this->assertSame(O::QUOTED, $q->status);
        foreach ([[['label' => 'A', 'amount' => 0]], [['label' => '', 'amount' => 5]], [], [['label' => 'Big', 'amount' => 100001]]] as $bad) {
            try {
                $svc->quote($o->fresh(), $this->admin(), $bad, null);
                $this->fail('A bad quote was accepted');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_accept_then_pay_from_the_wallet_debits_once_and_books_revenue_once(): void
    {
        $u = $this->user();
        $o = $this->quoted($u, 1500);
        $this->fund($u, 2000);
        $svc = app(WlCustomOrders::class);

        try {
            $svc->payFromWallet($o, $u);
            $this->fail('An unaccepted quote was payable');
        } catch (WlCustomOrderException $e) {
            $this->assertSame('Accept the quote before paying.', $e->getMessage());
        }
        $svc->accept($o, $u);
        $svc->accept($o->fresh(), $u);   // a double click is harmless
        $svc->payFromWallet($o->fresh(), $u);
        $svc->payFromWallet($o->fresh(), $u);   // a second click never charges again
        $o = $o->fresh();
        $this->assertSame(O::PAID, $o->status);
        $this->assertSame('wallet', $o->paid_via);
        $this->assertEqualsWithDelta(500.0, app(WalletService::class)->spendableUsd($u), 0.001);
        $this->assertEquals(1, PlatformEarning::query()->where('source_type', 'wl_custom_order')->count());
    }

    public function test_insufficient_funds_charges_nothing_and_leaves_the_order_accepted(): void
    {
        $u = $this->user();
        $o = $this->quoted($u, 1500);
        $this->fund($u, 100);
        $svc = app(WlCustomOrders::class);
        $svc->accept($o, $u);
        try {
            $svc->payFromWallet($o->fresh(), $u);
            $this->fail('Should not be payable');
        } catch (InsufficientBalanceException) {
            $this->assertSame(O::ACCEPTED, $o->fresh()->status);
            $this->assertEqualsWithDelta(100.0, app(WalletService::class)->spendableUsd($u), 0.001);
            $this->assertSame(0, PlatformEarning::query()->where('source_type', 'wl_custom_order')->count());
        }
    }

    public function test_an_expired_quote_cannot_be_accepted_or_paid(): void
    {
        $u = $this->user();
        $o = $this->quoted($u);
        $o->forceFill(['quote_valid_until' => now()->subDay()])->save();
        $this->expectExceptionMessage('expired');
        try {
            app(WlCustomOrders::class)->accept($o->fresh(), $u);
        } finally {
            $this->assertSame(O::EXPIRED, $o->fresh()->status);
        }
    }

    public function test_the_daily_job_expires_unanswered_quotes_only(): void
    {
        $u = $this->user();
        $a = $this->quoted($u);
        $b = $this->quoted($u);
        $a->forceFill(['quote_valid_until' => now()->subHour()])->save();
        $this->artisan('wl-custom-orders:expire')->assertSuccessful();
        $this->assertSame(O::EXPIRED, $a->fresh()->status);
        $this->assertSame(O::QUOTED, $b->fresh()->status);
    }

    public function test_one_buyer_can_never_see_or_touch_another_buyers_order(): void
    {
        $owner = $this->user();
        $o = $this->quoted($owner);
        $other = $this->user();
        $this->actingAs($other)->get('/account/white-label-custom/'.$o->reference)->assertNotFound();
        $this->actingAs($owner)->get('/account/white-label-custom/'.$o->reference)->assertOk()->assertSee('$1,500.00');
        $this->expectException(WlCustomOrderException::class);
        app(WlCustomOrders::class)->accept($o, $other);
    }

    public function test_the_buyer_page_accepts_pays_and_chats(): void
    {
        $u = $this->user();
        $o = $this->quoted($u, 400);
        $this->fund($u, 500);
        Livewire::actingAs($u)->test(WlCustomOrderView::class, ['reference' => $o->reference])
            ->assertSee('Accept this quote')
            ->call('accept')->assertSee('Quote accepted')->assertSee('Pay $400.00 from wallet')
            ->call('pay')->assertSee('Paid from your wallet')
            ->set('message', 'When do you start?')->call('send')->assertSee('When do you start?');
        $this->assertSame(1, $o->messages()->count());
        $this->assertSame(O::PAID, $o->fresh()->status);
    }

    public function test_admin_marks_a_transfer_paid_with_a_reference_once_and_books_revenue(): void
    {
        $u = $this->user();
        $o = $this->quoted($u, 900);
        $admin = $this->admin();
        $svc = app(WlCustomOrders::class);
        $svc->accept($o, $u);
        try {
            $svc->markPaidOffline($o->fresh(), $admin, '   ');
            $this->fail('A blank reference was accepted');
        } catch (WlCustomOrderException) {
        }
        $svc->markPaidOffline($o->fresh(), $admin, 'GTB-77123');
        $o = $o->fresh();
        $this->assertSame('offline', $o->paid_via);
        $this->assertSame('GTB-77123', $o->paid_reference);
        $this->assertEquals(1, PlatformEarning::query()->where('source_type', 'wl_custom_order')->count());
        $this->expectException(WlCustomOrderException::class);
        $svc->markPaidOffline($o, $admin, 'again');   // already paid
    }

    public function test_the_admin_queue_is_staff_only_and_can_quote_reject_and_reply(): void
    {
        $buyer = $this->user();
        $o = $this->request($buyer);
        $this->actingAs($this->user())->get('/adminmaster/white-label/custom-orders')->assertNotFound();
        $admin = $this->admin();
        $this->actingAs($admin)->get('/adminmaster/white-label/custom-orders')->assertOk()->assertSee('Custom orders')->assertSee('Zenith Cloud');
        $c = Livewire::actingAs($admin)->test(WlCustomOrdersAdmin::class)->call('open', $o->id)
            ->set('items.0.label', 'Theme')->set('items.0.amount', '250')->call('addLine')->set('items.1.label', 'Copy')->set('items.1.amount', '50.5')
            ->assertSee('Total $300.50')->call('sendQuote')->assertSee('Quote sent');
        $this->assertEquals(300.50, (float) $o->fresh()->quote_total_usd);
        $c->set('reply', 'Happy to adjust.')->call('send')->assertSee('Happy to adjust.');
        $c->set('rejectReason', '')->call('reject')->assertSee('Tell the buyer why');
        $c->set('rejectReason', 'Out of scope')->call('reject')->assertSee('declined');
        $this->assertSame(O::DECLINED, $o->fresh()->status);
    }

    public function test_the_hub_tab_and_wizard_link_only_show_while_switched_on(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/adminmaster/white-label/funnel-check')->assertOk()->assertSee('Custom orders');
        FeatureFlags::setEnabled('wl_custom_orders', false);
        $this->actingAs($admin)->get('/adminmaster/white-label/funnel-check')->assertOk()->assertDontSee('Custom orders');
    }
}
