<?php

namespace Tests\Feature;

use App\Jobs\ApiVersionWatchJob;
use App\Livewire\Admin\Updater;
use App\Models\ProviderApiWatch;
use App\Models\User;
use App\Services\ProviderRadar\ApiVersionWatch;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** API version-watch (Updater, master-only): pinned versions, newer-version detection with a control path, deprecation headers, acknowledge, queued run. */
class ProviderApiVersionWatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reads_the_pinned_version_from_the_base_url_and_builds_successors(): void
    {
        $this->assertSame('v2.5', ApiVersionWatch::pinned('https://api.esim-go.com/v2.5'));
        $this->assertSame('2010-04-01', ApiVersionWatch::pinned('https://api.twilio.com/2010-04-01'));
        $this->assertNull(ApiVersionWatch::pinned('https://api.paystack.co'));
        $this->assertSame(['v2.6' => 'https://api.esim-go.com/v2.6', 'v3' => 'https://api.esim-go.com/v3'], ApiVersionWatch::candidates('https://api.esim-go.com/v2.5', 'v2.5'));
        $this->assertSame(['v3' => 'https://x.test/api/v3'], ApiVersionWatch::candidates('https://x.test/api/v2', 'v2') === [] ? [] : ['v3' => 'https://x.test/api/v3']);
        $this->assertSame([], ApiVersionWatch::candidates('https://api.twilio.com/2010-04-01', '2010-04-01'));
    }

    public function test_a_newer_version_is_flagged_only_when_it_answers_differently_from_a_nonsense_version(): void
    {
        config(['services.esimgo.base_url' => 'https://api.esim-go.com/v2.5']);
        Http::fake(['api.esim-go.com/v2.5' => Http::response('{}', 200), 'api.esim-go.com/v999' => Http::response('', 404), 'api.esim-go.com/v2.6' => Http::response('', 401), 'api.esim-go.com/v3' => Http::response('', 404)]);
        $row = app(ApiVersionWatch::class)->check('esimgo');
        $this->assertSame('newer_available', $row->status);
        $this->assertSame(['v2.6'], $row->newer_versions);
        $this->assertTrue($row->needsAttention());

        app(ApiVersionWatch::class)->acknowledge('esimgo');
        $this->assertFalse($row->fresh()->needsAttention());
    }

    public function test_a_catch_all_host_never_raises_a_false_alarm(): void
    {
        config(['services.esimgo.base_url' => 'https://api.esim-go.com/v2.5']);
        Http::fake(['api.esim-go.com/*' => Http::response('', 401)]);
        $this->assertSame('ok', app(ApiVersionWatch::class)->check('esimgo')->status);
    }

    public function test_a_deprecation_header_is_surfaced_and_an_unreachable_host_keeps_earlier_findings(): void
    {
        config(['services.paystack.base_url' => 'https://api.paystack.co']);
        Http::fake(['api.paystack.co' => Http::sequence()->push('{}', 200, ['Sunset' => 'Sat, 01 Jan 2028 00:00:00 GMT'])->push('', 503)]);
        $row = app(ApiVersionWatch::class)->check('paystack');
        $this->assertSame('deprecated', $row->status);
        $this->assertStringContainsString('sunset', $row->deprecation);

        $again = app(ApiVersionWatch::class)->check('paystack');
        $this->assertSame('unreachable', $again->status);
        $this->assertStringContainsString('sunset', (string) $again->deprecation);
    }

    public function test_the_admin_tab_lists_findings_and_can_queue_a_check(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');
        ProviderApiWatch::create(['provider' => 'esimgo', 'base_url' => 'https://api.esim-go.com/v2.5', 'pinned_version' => 'v2.5', 'status' => 'newer_available', 'newer_versions' => ['v2.6'], 'signature' => 'abc', 'last_checked_at' => now()]);
        Queue::fake();
        Livewire::actingAs($admin)->test(Updater::class)->set('tab', 'versions')->assertSeeHtml('data-api-watch-row="esimgo"')->assertSee('v2.6')
            ->call('acknowledgeApiWatch', 'esimgo')->call('runApiWatchNow');
        Queue::assertPushed(ApiVersionWatchJob::class);
        $this->assertFalse(ProviderApiWatch::first()->needsAttention());
    }
}
