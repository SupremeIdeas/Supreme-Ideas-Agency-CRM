<?php

namespace Tests\Feature;

use App\Models\CapabilityProposal;
use App\Models\ProviderCapabilitySnapshot;
use App\Services\AI\AnthropicClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeAnthropicClient;
use Tests\TestCase;

/**
 * Tier 5 #14 Phase E — end-to-end verification of the real command against
 * the two registered strategies (changelog + openapi are both exercised
 * here; response-schema is covered directly in ProviderCapabilityRadarTest).
 * Confirms: a snapshot is stored on first run, and running the scan twice
 * in a row with no real upstream change produces zero new proposals.
 *
 * Http::fake() STACKS rules rather than replacing them on a second call —
 * the first-registered pattern for a URL always wins — so a test that needs
 * the response to change mid-test (baseline, then an upstream change)
 * registers everything ONCE via closures reading a mutable `$state` array,
 * rather than calling Http::fake() twice.
 */
class ProviderCapabilityScanCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @param array{twilio: string, stripe: string, esimgo: array, airalo: array} $state */
    private function fakeAllProviderEndpoints(array &$state): void
    {
        config(['services.esimgo.api_key' => 'k', 'services.airalo.client_id' => 'id', 'services.airalo.client_secret' => 'secret']);

        Http::fake([
            // Regular closures with `use (&$state)` — NOT arrow functions,
            // which auto-capture by value at definition time and would
            // freeze on the first scan's content.
            'https://raw.githubusercontent.com/*' => function () use (&$state) {
                return Http::response($state['twilio'], 200);
            },
            'https://docs.stripe.com/*' => function () use (&$state) {
                return Http::response($state['stripe'], 200);
            },
            'https://api.esim-go.com/*' => function () use (&$state) {
                return Http::response($state['esimgo'], 200);
            },
            // Airalo's token is cached across calls (23h TTL) — route by
            // path, not call order, so a cache hit on /token never skews
            // which body /packages receives.
            'https://partners-api.airalo.com/*' => function ($request) use (&$state) {
                return str_contains($request->url(), '/token')
                    ? Http::response(['data' => ['access_token' => 'tok']], 200)
                    : Http::response(['data' => $state['airalo']], 200);
            },
        ]);
    }

    public function test_the_command_stores_a_baseline_for_every_registered_watcher_on_first_run(): void
    {
        $this->app->instance(AnthropicClient::class, new FakeAnthropicClient([]));
        $state = [
            'twilio' => json_encode(['openapi' => '3.0.0', 'paths' => []]),
            'stripe' => '<html><body>Nothing new.</body></html>',
            'esimgo' => [['id' => 'p1', 'price' => 5.0]],
            'airalo' => [['id' => 'a1', 'net_price' => 4.0]],
        ];
        $this->fakeAllProviderEndpoints($state);

        $this->artisan('provider:capability-scan')->assertExitCode(0);

        $this->assertDatabaseCount('provider_capability_snapshots', 4);
        $this->assertEqualsCanonicalizing(
            ['stripe', 'twilio', 'esimgo', 'airalo'],
            ProviderCapabilitySnapshot::pluck('provider')->unique()->values()->all(),
        );
        $this->assertDatabaseCount('capability_proposals', 0); // baseline only, nothing to diff yet
    }

    public function test_running_the_scan_twice_with_no_upstream_change_produces_zero_new_proposals(): void
    {
        $this->app->instance(AnthropicClient::class, new FakeAnthropicClient(['summary' => 'x']));
        $state = [
            'twilio' => json_encode(['openapi' => '3.0.0', 'paths' => []]),
            'stripe' => '<html><body>Nothing new.</body></html>',
            'esimgo' => [['id' => 'p1', 'price' => 5.0]],
            'airalo' => [['id' => 'a1', 'net_price' => 4.0]],
        ];
        $this->fakeAllProviderEndpoints($state);

        $this->artisan('provider:capability-scan');
        $this->artisan('provider:capability-scan'); // identical content, second run

        $this->assertDatabaseCount('provider_capability_snapshots', 4); // no new snapshots either
        $this->assertDatabaseCount('capability_proposals', 0);
    }

    public function test_a_real_stripe_changelog_change_produces_one_evidence_linked_proposal(): void
    {
        $this->app->instance(AnthropicClient::class, new FakeAnthropicClient([
            'summary' => 'Stripe added Radar rule templates.',
            'capability_added' => 'Radar rule templates',
            'relevance_to_naara' => 'Could improve fraud screening on top-ups.',
            'proposed_feature' => 'Adopt Radar rule templates for wallet top-ups',
            'estimated_effort' => 'small',
            'confidence' => 'medium',
            'requires_human_verification_because' => 'Confirm template availability on our Stripe plan tier.',
        ]));

        $state = [
            'twilio' => json_encode(['openapi' => '3.0.0', 'paths' => []]),
            'stripe' => '<html><body>Nothing new yet.</body></html>',
            'esimgo' => [['id' => 'p1', 'price' => 5.0]],
            'airalo' => [['id' => 'a1', 'net_price' => 4.0]],
        ];
        $this->fakeAllProviderEndpoints($state);
        $this->artisan('provider:capability-scan'); // baseline

        $state['stripe'] = '<html><body>New: Radar rule templates are now available.</body></html>';
        $this->artisan('provider:capability-scan');

        $this->assertDatabaseCount('capability_proposals', 1);
        $proposal = CapabilityProposal::first();
        $this->assertSame('stripe', $proposal->provider);
        $this->assertSame(CapabilityProposal::NEW, $proposal->status);
        $this->assertStringContainsString('Radar rule templates', $proposal->raw_diff);
    }
}
