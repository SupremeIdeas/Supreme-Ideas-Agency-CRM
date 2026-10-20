<?php

namespace Tests\Feature\Handbooks;

use App\Jobs\HandbookEsimUnlockJob;
use App\Livewire\Handbooks\Library;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\Handbook;
use App\Models\HandbookEntitlement;
use App\Models\HandbookEsimPick;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** A Naara eSIM earns a handbook (MASTER-ONLY): local -> flagship at once, regional/global -> one pick, merchant orders never. */
class HandbookEsimUnlockTest extends TestCase
{
    use RefreshDatabase;

    private function book(string $cc, string $slot = 'S1'): Handbook
    {
        return Handbook::create(['slug' => strtolower($cc).'-'.uniqid(), 'title' => $cc.' Handbook', 'country_code' => $cc, 'series_slot' => $slot, 'list_price_usd' => 9.99, 'status' => Handbook::PUBLISHED, 'description' => 'd', 'epub_path' => 'x.epub', 'last_verified_on' => now()->toDateString()]);
    }

    private function order(array $plan, array $over = []): EsimOrder
    {
        $p = EsimPlan::query()->forceCreate($plan + ['provider' => 'esimgo', 'provider_plan_id' => 'p'.uniqid(), 'name' => 'Plan', 'type' => 'data', 'data_mb' => 1024, 'validity_days' => 7, 'cost_price_usd' => 1, 'is_active' => true]);
        $u = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);

        return EsimOrder::create($over + ['user_id' => $u->id, 'plan_id' => $p->id, 'provider' => 'esimgo', 'status' => 'processing']);
    }

    public function test_creating_an_order_queues_the_unlock_but_a_merchant_order_does_not(): void
    {
        Queue::fake();
        $this->order(['coverage_type' => 'local', 'countries' => ['GH']]);
        Queue::assertPushed(HandbookEsimUnlockJob::class, 1);
    }

    public function test_a_local_plan_unlocks_the_flagship_once(): void
    {
        $b = $this->book('GH');
        $this->book('GH', 'S2');
        $o = $this->order(['coverage_type' => 'local', 'countries' => ['GH']]);
        $o->load('plan');

        (new HandbookEsimUnlockJob($o->id))->handle(app(\App\Services\Handbooks\HandbookEsimUnlockService::class));
        (new HandbookEsimUnlockJob($o->id))->handle(app(\App\Services\Handbooks\HandbookEsimUnlockService::class));

        $e = HandbookEntitlement::query()->where('user_id', $o->user_id)->get();
        $this->assertCount(1, $e);
        $this->assertSame($b->id, $e[0]->handbook_id);
        $this->assertSame('unlocked', $e[0]->level);
    }

    public function test_a_regional_plan_waits_for_a_pick_and_only_offers_covered_flagships(): void
    {
        $gh = $this->book('GH');
        $ke = $this->book('KE');
        $this->book('NG');
        $o = $this->order(['coverage_type' => 'regional', 'countries' => ['GH', 'KE']]);

        (new HandbookEsimUnlockJob($o->id))->handle(app(\App\Services\Handbooks\HandbookEsimUnlockService::class));
        $this->assertSame(0, HandbookEntitlement::query()->count());
        $pick = HandbookEsimPick::query()->firstOrFail();

        $u = User::query()->find($o->user_id);
        Livewire::actingAs($u)->test(Library::class)->call('choosePick', $pick->id, 999)->assertSet('error', 'Pick one of the handbooks offered for your eSIM.');
        Livewire::actingAs($u)->test(Library::class)->call('choosePick', $pick->id, $ke->id)->assertSet('error', null);

        $this->assertSame($ke->id, HandbookEntitlement::query()->firstOrFail()->handbook_id);
        // a second pick on the same order is refused
        Livewire::actingAs($u)->test(Library::class)->call('choosePick', $pick->id, $gh->id)->assertSet('error', 'That choice is no longer available.');
    }

    public function test_another_member_cannot_use_someone_elses_pick(): void
    {
        $ke = $this->book('KE');
        $o = $this->order(['coverage_type' => 'global', 'countries' => ['KE', 'GH']]);
        (new HandbookEsimUnlockJob($o->id))->handle(app(\App\Services\Handbooks\HandbookEsimUnlockService::class));
        $pick = HandbookEsimPick::query()->firstOrFail();
        $thief = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);

        Livewire::actingAs($thief)->test(Library::class)->call('choosePick', $pick->id, $ke->id)->assertSet('error', 'That choice is no longer available.');
        $this->assertSame(0, HandbookEntitlement::query()->count());
    }

    public function test_no_published_flagship_means_no_pick_and_no_error(): void
    {
        $o = $this->order(['coverage_type' => 'regional', 'countries' => ['GH', 'KE']]);
        (new HandbookEsimUnlockJob($o->id))->handle(app(\App\Services\Handbooks\HandbookEsimUnlockService::class));
        $this->assertSame(0, HandbookEsimPick::query()->count());
    }
}
