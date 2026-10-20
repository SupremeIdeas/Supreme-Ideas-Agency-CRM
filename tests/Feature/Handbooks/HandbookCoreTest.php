<?php

namespace Tests\Feature\Handbooks;

use App\Exceptions\InsufficientBalanceException;
use App\Models\BrandPartner;
use App\Models\BrandSubscription;
use App\Models\BrandSubscriptionPlan;
use App\Models\Handbook;
use App\Models\HandbookEntitlement;
use App\Models\PlatformEarning;
use App\Models\User;
use App\Services\Handbooks\EpubInspector;
use App\Services\Handbooks\HandbookAccess;
use App\Services\Handbooks\HandbookException;
use App\Services\Handbooks\HandbookPurchaseService;
use App\Services\Handbooks\HandbookUnlockService;
use App\Services\Wallet\WalletService;
use App\Support\Handbooks\HandbookPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\MakesEpubs;
use Tests\TestCase;

/** Naara Travel Handbook Store (MASTER-ONLY): pricing maths, EPUB validation, the access matrix, purchase idempotency and the orphan-charge guard, Pro slots. */
class HandbookCoreTest extends TestCase
{
    use MakesEpubs;
    use RefreshDatabase;

    private function book(array $over = []): Handbook
    {
        return Handbook::create($over + ['slug' => 'ghana-'.uniqid(), 'title' => 'Ghana Handbook', 'country_code' => 'GH', 'series_slot' => 'S1', 'list_price_usd' => 9.99, 'status' => Handbook::PUBLISHED, 'description' => 'd', 'epub_path' => 'x.epub', 'cover_path' => 'c.jpg', 'last_verified_on' => now()->toDateString()]);
    }

    private function funded(float $usd = 50): User
    {
        $u = User::factory()->create();
        app(WalletService::class)->credit($u, $usd, 'USD', ['reference' => 'f'.$u->id.uniqid()]);

        return $u->fresh();
    }

    private function pro(User $u): BrandSubscription
    {
        $plan = BrandSubscriptionPlan::create(['name' => 'Pro', 'price_usd_per_month' => 10, 'handles_included' => 5, 'guaranteed_followers_per_handle_per_month' => 0, 'video_previews_allowed' => 3, 'credit_reward_per_follow' => 1, 'is_active' => true, 'sort_order' => 1]);
        $b = BrandPartner::create(['owner_user_id' => $u->id, 'brand_name' => 'Pro Brand', 'category' => 'Food', 'listing_status' => BrandPartner::STATUS_ACTIVE, 'is_featured' => false, 'background_color' => '#0A6E6E', 'is_active' => true, 'current_plan_id' => $plan->id]);

        return BrandSubscription::create(['brand_partner_id' => $b->id, 'plan_id' => $plan->id, 'status' => BrandSubscription::ACTIVE, 'started_at' => now()]);
    }

    public function test_upgrade_price_is_half_of_list_rounded_up_to_the_cent(): void
    {
        $this->assertSame(5.0, HandbookPricing::upgradeUsd(9.99));
        $this->assertSame(3.5, HandbookPricing::upgradeUsd(6.99));
        $this->assertSame(3.5, HandbookPricing::upgradeUsd(7.00));
        $this->assertSame(0.01, HandbookPricing::upgradeUsd(0.01));
        $this->assertSame(0.0, HandbookPricing::upgradeUsd(0));
        $this->assertSame(9.99, HandbookPricing::fullUsd('9.99'));
    }

    public function test_a_valid_epub_is_accepted_with_toc_pages_and_imprint(): void
    {
        $r = app(EpubInspector::class)->inspect($this->makeEpub());

        $this->assertTrue($r['ok']);
        $this->assertSame('Ghana Handbook', $r['title']);
        $this->assertSame([['Housing', 0], ['Visas', 0], ['Student visa', 1]], array_map(fn ($t) => [$t['title'], $t['depth']], $r['toc']));
        $this->assertTrue($r['has_imprint']);
        $this->assertGreaterThan(0, $r['pages']);
        $this->assertSame([], $r['warnings']);
    }

    public function test_an_epub2_ncx_navigation_also_becomes_the_toc(): void
    {
        $r = app(EpubInspector::class)->inspect($this->makeEpub(['ncx' => true]));
        $this->assertTrue($r['ok']);
        $this->assertSame(['Housing', 'Visas', 'Student visa'], array_column($r['toc'], 'title'));
    }

    public function test_a_missing_imprint_and_scripts_are_warnings_not_errors(): void
    {
        $r = app(EpubInspector::class)->inspect($this->makeEpub(['imprint' => false, 'scripts' => true]));
        $this->assertTrue($r['ok']);
        $this->assertTrue($r['scripts']);
        $this->assertCount(2, $r['warnings']);
    }

    public function test_broken_epubs_are_refused_with_a_readable_reason(): void
    {
        $insp = app(EpubInspector::class);
        $txt = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($txt, 'not a zip');

        foreach ([[$txt, 'not a valid EPUB'], [$this->makeEpub(['mimetype' => 'text/plain']), 'mimetype'], [$this->makeEpub(['drm' => true]), 'DRM'], [$this->makeEpub(['badSpine' => true]), 'reading order']] as [$file, $needle]) {
            $r = $insp->inspect($file);
            $this->assertFalse($r['ok']);
            $this->assertStringContainsString($needle, $r['errors'][0]);
        }
    }

    public function test_the_access_matrix(): void
    {
        $b = $this->book();
        $u = $this->funded();
        $a = app(HandbookAccess::class);

        $this->assertNull($a->levelFor($u, $b));
        $this->assertFalse($a->canRead($u, $b));
        $o = $a->offers($u, $b);
        $this->assertSame(9.99, $o['buy']);
        $this->assertNull($o['upgrade']);

        app(HandbookUnlockService::class)->unlock($u, $b, 'esim_order', 'o1');
        $this->assertTrue($a->canRead($u, $b));
        $this->assertFalse($a->canDownload($u, $b), 'unlocked is read-only');
        $o = $a->offers($u, $b);
        $this->assertNull($o['buy']);
        $this->assertSame(5.0, $o['upgrade']);
    }

    public function test_buying_charges_the_list_price_grants_download_and_accrues_admin_revenue_once(): void
    {
        $b = $this->book();
        $u = $this->funded(50);
        $e = app(HandbookPurchaseService::class)->buy($u, $b);

        $this->assertSame('owned', $e->level);
        $this->assertSame('purchase', $e->source);
        $this->assertSame('9.99', (string) $e->price_paid_usd);
        $this->assertEqualsWithDelta(40.01, (float) $u->wallet->fresh()->usd_balance, 0.0001);
        $this->assertTrue(app(HandbookAccess::class)->canDownload($u, $b));
        $this->assertSame(1, PlatformEarning::where('source_type', 'handbook')->count());
        $this->assertEqualsWithDelta(9.99, (float) PlatformEarning::where('source_type', 'handbook')->value('amount'), 0.0001);

        $b->update(['list_price_usd' => 19.99]);   // a later price change never rewrites what was paid
        $this->assertSame('9.99', (string) $e->fresh()->price_paid_usd);

        $this->expectException(HandbookException::class);   // a second purchase is blocked and never charges
        try {
            app(HandbookPurchaseService::class)->buy($u, $b);
        } finally {
            $this->assertEqualsWithDelta(40.01, (float) $u->wallet->fresh()->usd_balance, 0.0001);
            $this->assertSame(1, PlatformEarning::where('source_type', 'handbook')->count());
        }
    }

    public function test_an_unlocked_book_upgrades_for_half_price(): void
    {
        $b = $this->book();
        $u = $this->funded(50);
        app(HandbookUnlockService::class)->unlock($u, $b, 'esim_order', 'o1');

        $e = app(HandbookPurchaseService::class)->buy($u, $b);

        $this->assertSame('upgrade', $e->source);
        $this->assertSame('5.00', (string) $e->price_paid_usd);
        $this->assertEqualsWithDelta(45.0, (float) $u->wallet->fresh()->usd_balance, 0.0001);
        $this->assertTrue(app(HandbookAccess::class)->canDownload($u, $b));
        $this->assertSame(1, HandbookEntitlement::count(), 'the same row is upgraded, not duplicated');
    }

    public function test_too_little_balance_charges_nothing_and_grants_nothing(): void
    {
        $b = $this->book();
        $u = $this->funded(3);

        try {
            app(HandbookPurchaseService::class)->buy($u, $b);
            $this->fail('should not be affordable');
        } catch (InsufficientBalanceException) {
            $this->assertEqualsWithDelta(3.0, (float) $u->wallet->fresh()->usd_balance, 0.0001);
            $this->assertSame(0, HandbookEntitlement::count());
            $this->assertSame(0, PlatformEarning::count());
        }
    }

    public function test_a_failure_after_the_charge_refunds_automatically(): void
    {
        Queue::fake();
        $b = $this->book();
        $u = $this->funded(50);
        HandbookEntitlement::saving(fn () => throw new \RuntimeException('disk full'));

        try {
            app(HandbookPurchaseService::class)->buy($u, $b);
            $this->fail('expected the guard to refuse');
        } catch (HandbookException $e) {
            $this->assertStringContainsString('not charged', $e->getMessage());
        }
        $this->assertEqualsWithDelta(50.0, (float) $u->wallet->fresh()->usd_balance, 0.0001);
        $this->assertSame(0, PlatformEarning::count(), 'no revenue for something not delivered');
        Queue::assertPushed(\App\Jobs\AlertAdminJob::class, fn ($j) => $j->code === 'handbook_orphan_charge');
    }

    public function test_an_unpublished_book_cannot_be_bought_or_read(): void
    {
        $b = $this->book(['status' => Handbook::DRAFT]);
        $u = $this->funded();
        $this->expectException(HandbookException::class);
        app(HandbookPurchaseService::class)->buy($u, $b);
    }

    public function test_esim_unlock_is_idempotent_and_never_downgrades_owned(): void
    {
        $b = $this->book();
        $u = $this->funded();
        $svc = app(HandbookUnlockService::class);

        $svc->unlock($u, $b, 'esim_order', 'o1');
        $svc->unlock($u, $b, 'esim_order', 'o2');
        $this->assertSame(1, HandbookEntitlement::count());

        app(HandbookPurchaseService::class)->buy($u, $b);
        $this->assertNull($svc->unlock($u, $b, 'esim_order', 'o3'));
        $this->assertSame('owned', HandbookEntitlement::first()->level);
        $this->assertSame($b->id, $svc->flagshipFor('gh')->id);
        $this->assertNull($svc->flagshipFor('ng'));
    }

    public function test_pro_gets_three_slots_not_four_and_lapse_locks_then_renewal_restores(): void
    {
        $u = $this->funded();
        $sub = $this->pro($u);
        $svc = app(HandbookUnlockService::class);
        $books = collect(range(1, 4))->map(fn ($i) => $this->book(['title' => "B{$i}", 'country_code' => 'G'.$i]));

        $this->assertSame(3, $svc->slotsLeft($u));
        foreach ($books->take(3) as $b) {
            $svc->useSlot($u, $b);
        }
        $this->assertSame(0, $svc->slotsLeft($u));
        try {
            $svc->useSlot($u, $books[3]);
            $this->fail('a fourth unlock must be refused');
        } catch (HandbookException $e) {
            $this->assertStringContainsString('used all 3', $e->getMessage());
        }
        $this->assertTrue(app(HandbookAccess::class)->canRead($u, $books[0]));
        $this->assertFalse(app(HandbookAccess::class)->canDownload($u, $books[0]), 'Pro slots read, they do not download');

        $sub->update(['status' => BrandSubscription::CANCELLED]);
        \App\Services\N2N\Media\NaaraPro::bust($u->id);
        $svc->syncProLocks($u);
        $this->assertFalse(app(HandbookAccess::class)->canRead($u, $books[0]), 'locked, not deleted');
        $this->assertSame(3, HandbookEntitlement::count());

        $sub->update(['status' => BrandSubscription::ACTIVE]);
        \App\Services\N2N\Media\NaaraPro::bust($u->id);
        $svc->syncProLocks($u);
        $this->assertTrue(app(HandbookAccess::class)->canRead($u, $books[0]), 'restored on renewal');
    }

    public function test_a_non_pro_person_cannot_use_a_slot(): void
    {
        $u = $this->funded();
        $this->expectException(HandbookException::class);
        app(HandbookUnlockService::class)->useSlot($u, $this->book());
    }

    public function test_the_publish_checklist_blocks_an_incomplete_title(): void
    {
        $b = Handbook::create(['slug' => 'x', 'title' => 'X', 'list_price_usd' => 0]);
        $p = $b->publishProblems();
        $this->assertContains('Add a cover.', $p);
        $this->assertContains('Add the manuscript as an EPUB file.', $p);
        $this->assertContains('Add the PDF for buyers to download.', $p);
        $this->assertContains('Set a price above zero.', $p);
        $this->assertContains('Set the "last verified" date.', $p);
        $this->assertContains('Choose at least one category.', $p);
        $this->assertFalse(app(\App\Services\Handbooks\HandbookCatalogueService::class)->publish($b)['ok']);
    }
}
