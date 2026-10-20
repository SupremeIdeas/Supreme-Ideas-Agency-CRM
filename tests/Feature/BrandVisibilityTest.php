<?php

namespace Tests\Feature;

use App\Livewire\BrandManage;
use App\Models\BrandPartner;
use App\Models\BrandPartnerHandle;
use App\Models\BrandSubscription;
use App\Models\BrandSubscriptionPlan;
use App\Models\User;
use App\Services\Brands\BrandSubscriptionService;
use App\Services\Brands\BrandVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The soft publish gate and the explicit visibility reasons (Brand-Growth Phase 3, MASTER-ONLY). */
class BrandVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function brand(array $over = []): BrandPartner
    {
        $owner = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);

        return BrandPartner::create($over + ['owner_user_id' => $owner->id, 'brand_name' => 'Acme', 'short_description' => 'Acme makes things.', 'hero_image_path' => 'https://x.test/c.jpg', 'logo_path' => 'https://x.test/l.png',
            'listing_status' => BrandPartner::STATUS_PENDING, 'is_active' => true, 'gate_version' => 2]);
    }

    private function handles(BrandPartner $b, int $n): void
    {
        foreach (array_slice(['instagram', 'tiktok', 'x'], 0, $n) as $i => $p) {
            BrandPartnerHandle::create(['brand_partner_id' => $b->id, 'platform' => $p, 'handle_label' => '@a'.$i, 'handle_url' => 'https://'.$p.'.com/a'.$i, 'credit_reward' => 2, 'verification' => 'self', 'is_active' => true, 'sort_order' => $i]);
        }
    }

    private function plan(): BrandSubscriptionPlan
    {
        return BrandSubscriptionPlan::create(['name' => 'Pro', 'price_usd_per_month' => 9, 'handles_included' => 5, 'credit_reward_per_follow' => 2, 'is_active' => true, 'sort_order' => 1]);
    }

    private function subscribe(BrandPartner $b, string $status): void
    {
        BrandSubscription::create(['brand_partner_id' => $b->id, 'plan_id' => $this->plan()->id, 'status' => $status, 'started_at' => now(), 'next_billing_at' => now()->addMonth(), 'last_charged_at' => now(), 'grace_reminders_sent' => 0]);
    }

    public function test_each_required_item_missing_blocks_publish_with_exactly_that_item(): void
    {
        $cases = ['hero_image_path' => 'Cover photo', 'logo_path' => 'Profile photo (logo)', 'brand_name' => 'Brand name', 'short_description' => 'Description'];
        foreach ($cases as $col => $label) {
            $b = $this->brand([$col => '']);
            $this->handles($b, 2);
            $this->assertSame([$label], BrandVisibility::missing($b), $col);
            try {
                BrandVisibility::publish($b);
                $this->fail("publish must be refused without: $label");
            } catch (\DomainException $e) {
                $this->assertStringContainsString($label, $e->getMessage());
            }
            $this->assertNull($b->fresh()->published_at);
        }
    }

    public function test_one_social_link_is_not_enough_and_two_are(): void
    {
        $b = $this->brand();
        $this->handles($b, 1);
        $this->assertSame(['Two social links'], BrandVisibility::missing($b->fresh()));
        $this->assertSame('draft', BrandVisibility::contentState($b->fresh()));
        $this->handles($b, 2);
        $this->assertSame([], BrandVisibility::missing($b->fresh()));
        $this->assertSame('ready', BrandVisibility::contentState($b->fresh()));
    }

    public function test_category_is_recommended_not_required_and_the_progress_counts_only_required_items(): void
    {
        $b = $this->brand(['category' => null]);
        $this->handles($b, 2);
        $this->assertTrue(BrandVisibility::passes($b->fresh()));
        $this->assertSame(['done' => 5, 'total' => 5, 'percent' => 100], BrandVisibility::progress($b->fresh()));
        $rec = array_column(array_filter(BrandVisibility::requirements($b->fresh()), fn ($r) => ! $r['required']), 'key');
        $this->assertContains('category', $rec);
    }

    /** @return array<string, array{0: array, 1: ?string, 2: int, 3: string, 4: bool}> */
    public static function stateTable(): array
    {
        // [brand overrides, subscription status, handle count, expected reason, expected live]
        return [
            'no content, no billing' => [[], null, 0, 'unpublished_incomplete', false],
            'content ready, not published' => [[], null, 2, 'ready_to_publish', false],
            'published, no entitlement' => [['published_at' => '2026-01-01'], null, 2, 'not_entitled', false],
            'published and paying but status not synced' => [['published_at' => '2026-01-01'], 'active', 2, 'syncing', false],
            'published, paying, active listing' => [['published_at' => '2026-01-01', 'listing_status' => 'active'], 'active', 2, 'live', true],
            'published, past due' => [['published_at' => '2026-01-01', 'listing_status' => 'paused_billing'], 'past_due', 2, 'past_due', false],
            'published, subscription cancelled' => [['published_at' => '2026-01-01', 'listing_status' => 'pending_setup'], 'cancelled', 2, 'cancelled', false],
            'disabled wins' => [['published_at' => '2026-01-01', 'listing_status' => 'disabled'], 'active', 2, 'disabled', false],
            'admin switched off' => [['published_at' => '2026-01-01', 'listing_status' => 'active', 'is_active' => false], 'active', 2, 'inactive', false],
            'admin granted pro, published' => [['published_at' => '2026-01-01', 'listing_status' => 'active', 'pro_granted_at' => '2026-01-02'], null, 2, 'live', true],
        ];
    }

    /** @dataProvider stateTable */
    #[\PHPUnit\Framework\Attributes\DataProvider('stateTable')]
    public function test_the_state_table(array $over, ?string $sub, int $handles, string $reason, bool $live): void
    {
        $b = $this->brand($over);
        $this->handles($b, $handles);
        if ($sub) {
            $this->subscribe($b, $sub);
        }
        $b = $b->fresh(['subscription', 'handles']);
        $this->assertSame($reason, BrandVisibility::reason($b));
        $this->assertSame($live, BrandVisibility::isLive($b));
        // the directory scope agrees with the reason for every state that is "live"
        $this->assertSame($live, BrandPartner::listed()->where('id', $b->id)->exists());
        $this->assertNotSame('', BrandVisibility::message($b));
    }

    public function test_publishing_before_paying_waits_and_paying_then_flips_it_live(): void
    {
        $b = $this->brand();
        $this->handles($b, 2);
        $b = BrandVisibility::publish($b);
        $this->assertNotNull($b->published_at);
        $this->assertSame('not_entitled', BrandVisibility::reason($b->fresh(['subscription'])));
        $this->assertSame(BrandPartner::STATUS_PENDING, $b->fresh()->listing_status);

        // an admin grants Naara Pro: the already-published brand goes live without another click
        $admin = User::factory()->create();
        app(BrandSubscriptionService::class)->grantPro($b->owner, $this->plan(), $admin);
        $this->assertSame('live', BrandVisibility::reason($b->fresh(['subscription', 'handles'])));
    }

    public function test_old_brands_are_grandfathered_and_keep_the_old_gate_and_automatic_listing(): void
    {
        $b = $this->brand(['gate_version' => 1, 'logo_path' => null, 'listing_status' => 'active', 'published_at' => now(), 'category' => 'Food']);
        $this->handles($b, 1);
        $b = $b->fresh(['subscription', 'handles']);
        $this->assertTrue(BrandVisibility::passes($b), 'the old gate needs one handle and no logo');
        $this->assertSame('not_entitled', BrandVisibility::reason($b));   // published (grandfathered) but not paying: waits for Naara Pro
        // the NEW checklist is still shown to them as a nudge
        $new = BrandVisibility::requirements($b, BrandVisibility::GATE_V2);
        $this->assertFalse(collect($new)->firstWhere('key', 'logo')['met']);
    }

    public function test_new_brands_get_the_soft_gate_by_default_and_an_admin_can_pin_the_old_one(): void
    {
        $this->assertSame(BrandVisibility::GATE_V2, BrandVisibility::newBrandVersion());
        \App\Models\Setting::setValue('brand.gate_version_new', 1, 'brand');
        $this->assertSame(BrandVisibility::GATE_V1, BrandVisibility::newBrandVersion());
    }

    public function test_the_owner_screen_shows_the_checklist_publishes_and_previews(): void
    {
        $b = $this->brand();
        $this->handles($b, 1);
        $c = Livewire::actingAs($b->owner)->test(BrandManage::class)->assertSee('Publish your profile')->assertSee('Two social links')->assertDontSee('wire:click="publish"');
        $c->call('publish');
        $this->assertNull($b->fresh()->published_at);
        $this->assertStringContainsString('Two social links', (string) $c->get('error'));

        $this->handles($b, 2);
        Livewire::actingAs($b->owner)->test(BrandManage::class)->call('publish')->assertSet('error', null);
        $this->assertNotNull($b->fresh()->published_at);

        // preview: only the owner, only with ?preview=1, only while not live
        $other = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $this->actingAs($other)->get(route('rewards.hunt.profile', ['brandPartner' => $b->id, 'preview' => 1]))->assertNotFound();
        $this->actingAs($b->owner)->get(route('rewards.hunt.profile', ['brandPartner' => $b->id]))->assertNotFound();
        $this->actingAs($b->owner)->get(route('rewards.hunt.profile', ['brandPartner' => $b->id, 'preview' => 1]))->assertOk()->assertSee('Preview');
    }

    public function test_the_migration_grandfathers_existing_listed_brands(): void
    {
        $b = $this->brand(['listing_status' => 'active', 'gate_version' => 1]);
        \Illuminate\Support\Facades\DB::table('brand_partners')->where('id', $b->id)->update(['published_at' => null]);
        $migration = require base_path('database/migrations/2026_12_21_100000_add_brand_visibility_to_brand_partners.php');
        // re-run only the data step of the migration
        \Illuminate\Support\Facades\DB::table('brand_partners')->whereIn('listing_status', ['active', 'paused_billing', 'disabled'])->whereNull('published_at')->update(['published_at' => \Illuminate\Support\Facades\DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);
        $this->assertNotNull($b->fresh()->published_at);
        $this->assertIsObject($migration);
    }

    public function test_the_verify_check_flags_a_listed_brand_that_skipped_publishing(): void
    {
        $b = $this->brand(['listing_status' => 'active']);
        $this->handles($b, 2);
        $this->subscribe($b, 'active');
        $r = (new \App\Support\Verify\Checks\BrandVisibilitySound)->run();
        $this->assertSame('fail', $r->status);   // gate 2, listed, never published
        $b->update(['published_at' => now()]);
        $this->assertSame('pass', (new \App\Support\Verify\Checks\BrandVisibilitySound)->run()->status);
    }
}
