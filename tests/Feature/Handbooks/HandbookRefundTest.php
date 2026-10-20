<?php

namespace Tests\Feature\Handbooks;

use App\Jobs\AlertAdminJob;
use App\Livewire\Admin\Handbooks\HandbookRefunds;
use App\Livewire\Handbooks\Library;
use App\Models\Handbook;
use App\Models\HandbookDownload;
use App\Models\HandbookEntitlement;
use App\Models\HandbookRefundRequest;
use App\Models\PlatformEarning;
use App\Models\User;
use App\Services\Handbooks\HandbookAnomalies;
use App\Services\Handbooks\HandbookException;
use App\Services\Handbooks\HandbookPurchaseService;
use App\Services\Handbooks\HandbookRefundService;
use App\Services\Platform\PlatformEarningsService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** Handbook refund requests, the download watch and the dashboard line (MASTER-ONLY): a person decides, money moves once, access ends, the ledger nets out. */
class HandbookRefundTest extends TestCase
{
    use RefreshDatabase;

    private function buyer(float $usd = 50): User
    {
        $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        app(WalletService::class)->credit($u, $usd, 'USD', ['reference' => 'f'.$u->id.uniqid()]);

        return $u->fresh();
    }

    private function admin(): User
    {
        $a = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        \Spatie\Permission\Models\Role::findOrCreate('super_admin', 'web');
        $a->assignRole('super_admin');

        return $a;
    }

    private function book(): Handbook
    {
        return Handbook::create(['slug' => 'uk-'.uniqid(), 'title' => 'UK Handbook', 'country_code' => 'GB', 'series_slot' => 'S1', 'list_price_usd' => 9.99, 'status' => Handbook::PUBLISHED, 'description' => 'd', 'epub_path' => 'x.epub', 'cover_path' => 'c.jpg', 'last_verified_on' => now()->toDateString()]);
    }

    private function bought(): array
    {
        $u = $this->buyer();
        $b = $this->book();
        $e = app(HandbookPurchaseService::class)->buy($u, $b);

        return [$u, $b, $e];
    }

    public function test_an_approved_refund_returns_the_money_once_ends_access_and_nets_the_ledger(): void
    {
        [$u, $b, $e] = $this->bought();
        $svc = app(HandbookRefundService::class);
        $this->assertEqualsWithDelta(40.01, (float) $u->wallet->fresh()->usd_balance, 0.0001);
        $r = $svc->request($u, $e, 'Wrong country');
        $this->assertSame('pending', $r->status);

        $svc->approve($r, $this->admin(), 'Sorry about that');
        $this->assertEqualsWithDelta(50.0, (float) $u->wallet->fresh()->usd_balance, 0.0001);
        $this->assertNull(HandbookEntitlement::query()->find($e->id), 'the bought book is removed');
        $this->assertSame('approved', $r->fresh()->status);
        $this->assertEqualsWithDelta(0.0, app(PlatformEarningsService::class)->balance(), 0.0001, 'the sale and the refund cancel out');
        $this->assertSame(1, PlatformEarning::query()->where('type', 'reversal')->count());

        $this->expectException(HandbookException::class);   // a second click never refunds twice
        $svc->approve($r->fresh(), $this->admin());
    }

    public function test_declining_moves_no_money_and_keeps_access(): void
    {
        [$u, $b, $e] = $this->bought();
        $svc = app(HandbookRefundService::class);
        $r = $svc->request($u, $e, null);
        $svc->decline($r, $this->admin(), 'Outside our policy');
        $this->assertEqualsWithDelta(40.01, (float) $u->wallet->fresh()->usd_balance, 0.0001);
        $this->assertNotNull(HandbookEntitlement::query()->find($e->id));
        $this->assertSame('declined', $r->fresh()->status);
    }

    public function test_a_refunded_upgrade_drops_back_to_the_unlocked_level(): void
    {
        $u = $this->buyer();
        $b = $this->book();
        HandbookEntitlement::create(['user_id' => $u->id, 'handbook_id' => $b->id, 'level' => 'unlocked', 'source' => 'esim_order']);
        $e = app(HandbookPurchaseService::class)->buy($u, $b);
        $this->assertSame('upgrade', $e->source);
        $svc = app(HandbookRefundService::class);
        $svc->approve($svc->request($u, $e, null), $this->admin());
        $e = $e->fresh();
        $this->assertSame('unlocked', $e->level);
        $this->assertEqualsWithDelta(50.0, (float) $u->wallet->fresh()->usd_balance, 0.0001);
    }

    public function test_only_a_paid_recent_book_without_a_waiting_request_can_be_refunded(): void
    {
        $svc = app(HandbookRefundService::class);
        $u = $this->buyer();
        $free = HandbookEntitlement::create(['user_id' => $u->id, 'handbook_id' => $this->book()->id, 'level' => 'unlocked', 'source' => 'esim_order']);
        $this->assertNotNull($svc->problem($free), 'a free unlock has nothing to refund');

        [$u2, , $e] = $this->bought();
        $this->assertNull($svc->problem($e));
        $svc->request($u2, $e, null);
        $this->assertStringContainsString('already have a refund request', (string) $svc->problem($e));

        [$u3, , $old] = $this->bought();
        $old->forceFill(['updated_at' => now()->subDays(30)])->saveQuietly();
        $this->assertStringContainsString('7 days', (string) $svc->problem($old));
        $this->expectException(HandbookException::class);
        $svc->request($this->buyer(), $e, null);   // someone else's purchase
    }

    public function test_the_library_offers_a_request_and_shows_it_waiting(): void
    {
        [$u, $b, $e] = $this->bought();
        Livewire::actingAs($u)->test(Library::class)->assertSee('Request a refund')
            ->call('openRefund', $e->id)->set('refundReason', 'Bought the wrong one')->call('requestRefund')
            ->assertSee('Refund request with our team')->assertDontSee('Request a refund');
        $this->assertSame(1, HandbookRefundRequest::query()->where('user_id', $u->id)->where('status', 'pending')->count());
    }

    public function test_the_admin_queue_is_staff_only_and_decides_requests(): void
    {
        [$u, $b, $e] = $this->bought();
        $r = app(HandbookRefundService::class)->request($u, $e, 'x');
        $this->actingAs($u)->get('/adminmaster/handbooks/refunds')->assertNotFound();
        $a = $this->admin();
        $this->actingAs($a)->get('/adminmaster/handbooks')->assertOk()->assertSee('Refund requests');
        Livewire::actingAs($a)->test(HandbookRefunds::class)->assertSee('UK Handbook')->set('notes.'.$r->id, 'Done')->call('approve', $r->id)->assertSee('Refund approved');
        $this->assertSame('approved', $r->fresh()->status);
        $this->assertEqualsWithDelta(50.0, (float) $u->wallet->fresh()->usd_balance, 0.0001);
    }

    public function test_the_download_watch_flags_many_networks_and_alerts_without_blocking(): void
    {
        Queue::fake();
        $u = User::factory()->create();
        $b = $this->book();
        foreach (['a', 'b', 'c'] as $ip) {
            HandbookDownload::create(['user_id' => $u->id, 'handbook_id' => $b->id, 'format' => 'epub', 'edition' => 1, 'stamped' => true, 'ip_hash' => $ip, 'created_at' => now()->subHour()]);
        }
        $calm = User::factory()->create();
        HandbookDownload::create(['user_id' => $calm->id, 'handbook_id' => $b->id, 'format' => 'epub', 'edition' => 1, 'stamped' => true, 'ip_hash' => 'z', 'created_at' => now()->subHour()]);
        $flags = app(HandbookAnomalies::class)->scan();
        $this->assertCount(1, $flags);
        $this->assertSame($u->id, $flags->first()['user_id']);
        Artisan::call('handbooks:watch-downloads');
        Queue::assertPushed(AlertAdminJob::class, fn ($j) => $j->code === 'handbook_download_watch');
    }

    public function test_the_dashboard_shows_handbook_sales_net_of_refunds(): void
    {
        [$u, $b, $e] = $this->bought();
        $svc = app(HandbookRefundService::class);
        $svc->approve($svc->request($u, $e, null), $this->admin());
        $this->buyerBuys();
        $this->actingAs($this->admin())->get('/adminmaster')->assertOk()->assertSee('Handbook sales (30d)', false)->assertSee('9.99', false);
    }

    private function buyerBuys(): void
    {
        $u = $this->buyer();
        app(HandbookPurchaseService::class)->buy($u, $this->book());
    }
}
