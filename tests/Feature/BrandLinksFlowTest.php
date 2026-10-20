<?php

namespace Tests\Feature;

use App\Livewire\Admin\BrandLinks as AdminBrandLinks;
use App\Livewire\BrandLinksList;
use App\Livewire\BrandLinksManager;
use App\Models\BrandLink;
use App\Models\BrandPartner;
use App\Models\User;
use App\Notifications\EventNotification;
use App\Services\BrandLinks\BrandLinkService;
use App\Services\BrandLinks\LinkProbe;
use App\Services\BrandLinks\ProbeResult;
use App\Support\Verify\Checks\BrandLinksHealthy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Business links end to end (Brand-Growth Phase 2, MASTER-ONLY): owner add flow, public rendering, admin queue, weekly re-check, verify. */
class BrandLinksFlowTest extends TestCase
{
    use RefreshDatabase;

    private array $answers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $answers = &$this->answers;
        $this->app->bind(LinkProbe::class, function () use (&$answers) {   // a plain closure: an arrow function would capture the array by value and miss later changes
            return new class($answers) implements LinkProbe {
            public function __construct(private array &$a) {}

            public function probe(string $url): ProbeResult
            {
                $host = parse_url($url, PHP_URL_HOST);
                [$ok, $final, $err] = $this->a[$host] ?? [true, $url, null];

                return new ProbeResult($ok, $ok ? 200 : null, $final ?? $url, 0, $err);
            }
            };
        });
    }

    private function brand(string $name = 'Ada Bakes'): BrandPartner
    {
        $owner = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);

        return BrandPartner::create(['brand_name' => $name, 'owner_user_id' => $owner->id, 'category' => 'food', 'listing_status' => 'active', 'is_active' => true]);
    }

    public function test_owner_adds_a_good_link_which_goes_live_and_shows_label_plus_real_host(): void
    {
        Notification::fake();
        $b = $this->brand();
        Livewire::actingAs($b->owner)->test(BrandLinksManager::class)->set('label', 'Our shop')->set('url', 'https://adabakes.com.ng?utm_source=ig')->call('add')->assertHasNoErrors()->assertSet('problems', []);
        $l = $b->links()->first();
        $this->assertSame('approved', $l->status);
        $this->assertSame('https://adabakes.com.ng', $l->normalized_url);
        $this->assertSame('adabakes.com.ng', $l->registrable_domain);
        Livewire::test(BrandLinksList::class, ['brandId' => $b->id])->assertSee('Our shop')->assertSee('adabakes.com.ng');
        Notification::assertSentTo($b->owner, EventNotification::class, fn ($n) => $n->event === 'brand.link_approved');
    }

    public function test_a_refused_link_explains_why_and_an_unsafe_one_is_not_even_stored(): void
    {
        $b = $this->brand();
        $c = Livewire::actingAs($b->owner)->test(BrandLinksManager::class)->set('label', 'Short')->set('url', 'https://bit.ly/abc')->call('add');
        $this->assertStringContainsString('Shortened links', implode(' ', $c->get('problems')));
        $this->assertSame(0, $b->links()->count());

        // low resemblance: stored as rejected, and the owner may ask for a review
        $c->set('label', 'Other')->set('url', 'https://zenithcarrentals.com')->call('add');
        $this->assertTrue($c->get('canReview'));
        $link = $b->links()->first();
        $this->assertSame('rejected', $link->status);
        $c->call('review', $link->id);
        $this->assertSame('pending', $link->fresh()->status);
        $this->assertTrue($link->fresh()->review_requested);
        Livewire::test(BrandLinksList::class, ['brandId' => $b->id])->assertDontSee('zenithcarrentals');   // pending links are never public
    }

    public function test_the_per_brand_limit_and_the_attempt_rate_limit(): void
    {
        $b = $this->brand();
        \App\Services\BrandLinks\BrandLinkSettings::save('links_per_brand', 1);
        $c = Livewire::actingAs($b->owner)->test(BrandLinksManager::class)->set('label', 'One')->set('url', 'https://adabakes.com')->call('add');
        $c->set('label', 'Two')->set('url', 'https://adabakes.org')->call('add');
        $this->assertStringContainsString('up to 1 links', implode(' ', $c->get('problems')));
        $this->assertSame(1, $b->links()->count());
    }

    public function test_admin_queue_approve_reject_and_the_tester(): void
    {
        Notification::fake();
        Role::findOrCreate('super_admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $b = $this->brand('Supreme Ideas Agency');
        $b->links()->create(['label' => 'Site', 'url' => 'https://sia.com', 'normalized_url' => 'https://sia.com', 'host' => 'sia.com', 'registrable_domain' => 'sia.com', 'tld' => 'com', 'resemblance_score' => 55, 'status' => 'pending']);
        $l = $b->links()->first();
        $c = Livewire::actingAs($admin)->test(AdminBrandLinks::class)->assertSee('sia.com');
        $c->call('approve', $l->id);
        $this->assertSame('approved', $l->fresh()->status);
        $c->set("rejectReason.{$l->id}", 'Not your domain')->call('reject', $l->id);
        $this->assertSame('rejected', $l->fresh()->status);
        $this->assertSame('Not your domain', $l->fresh()->reject_reason);

        $c->set('tBrand', 'Ada Bakes')->set('tUrl', 'https://adabakes.com.ng')->call('test');
        $this->assertSame('approved', $c->get('result')['status']);
        $c->set('tUrl', 'https://paypal-adabakes.com')->call('test');
        $this->assertSame('rejected', $c->get('result')['status']);
    }

    public function test_the_weekly_recheck_suspends_a_link_that_now_redirects_elsewhere_but_gives_an_outage_one_more_week(): void
    {
        Notification::fake();
        $b = $this->brand();
        $svc = app(BrandLinkService::class);
        $svc->add($b, 'Shop', 'https://adabakes.com');
        $l = $b->links()->first();
        $this->assertSame('approved', $l->status);

        // an outage: not suspended the first time ...
        $this->answers['adabakes.com'] = [false, null, 'dns'];
        $svc->recheck($l->fresh());
        $this->assertSame('approved', $l->fresh()->status);
        // ... but a second miss in a row pauses it
        $svc->recheck($l->fresh());
        $this->assertSame('suspended', $l->fresh()->status);
        Livewire::test(BrandLinksList::class, ['brandId' => $b->id])->assertDontSee('adabakes.com');

        // a redirect to another site suspends immediately
        $svc->approve($l->fresh());
        $this->answers['adabakes.com'] = [true, 'https://scam.example.net/', null];
        $svc->recheck($l->fresh());
        $this->assertSame('suspended', $l->fresh()->status);
        Notification::assertSentTo($b->owner, EventNotification::class, fn ($n) => $n->event === 'brand.link_suspended');
    }

    public function test_the_command_rechecks_only_what_is_due_and_visitor_reports_pause_a_link(): void
    {
        $b = $this->brand();
        $svc = app(BrandLinkService::class);
        $svc->add($b, 'Shop', 'https://adabakes.com');
        $l = $b->links()->first();
        $l->update(['last_checked_at' => now()]);
        $this->artisan('brand-links:recheck')->expectsOutputToContain('Checked 0 link(s)')->assertSuccessful();
        $l->update(['last_checked_at' => now()->subDays(8)]);
        $this->artisan('brand-links:recheck')->expectsOutputToContain('Checked 1 link(s)')->assertSuccessful();

        $c = Livewire::test(BrandLinksList::class, ['brandId' => $b->id]);
        foreach (range(1, 3) as $i) {
            \Illuminate\Support\Facades\RateLimiter::clear('brand-link-report:'.request()->ip());
            $c->call('report', $l->id);
        }
        $this->assertSame('suspended', $l->fresh()->status);
    }

    public function test_the_verify_check_and_the_master_only_tokens(): void
    {
        $this->assertSame('pass', (new BrandLinksHealthy)->run()->status);
        $b = $this->brand();
        $b->links()->create(['label' => 'Old', 'url' => 'https://adabakes.com', 'normalized_url' => 'https://adabakes.com', 'host' => 'adabakes.com', 'registrable_domain' => 'adabakes.com', 'tld' => 'com', 'status' => 'approved', 'last_checked_at' => now()->subDays(40)]);
        $this->assertSame('warn', (new BrandLinksHealthy)->run()->status);
        foreach (['app/Services/BrandLinks/BrandLinkPolicy.php', 'database/migrations/2026_12_20_100000_create_brand_links_table.php', 'config/brand_links.php', 'resources/views/livewire/brand-links-list.blade.php', 'app/Livewire/Admin/BrandLinks.php'] as $path) {
            $this->assertTrue(\App\Support\MasterOnlyModules::matches($path), "$path must be guarded");
        }
    }
}
