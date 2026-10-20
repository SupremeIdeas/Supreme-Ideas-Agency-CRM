<?php

namespace Tests\Feature;

use App\Models\CapabilityProposal;
use App\Models\ProviderCapabilitySnapshot;
use App\Services\ProviderRadar\CapabilityDiffService;
use App\Services\ProviderRadar\Contracts\ProviderCapabilityWatcherInterface;
use App\Services\ProviderRadar\ProviderCapabilityScanService;
use App\Services\ProviderRadar\ResponseShape;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeAnthropicClient;
use Tests\Support\FakeCapabilityWatcher;
use Tests\TestCase;

/**
 * Tier 5 #14 (Provider Capability Radar, master-only). Constraints under
 * test, restated from the blueprint: no auto-implementation ever — every
 * outcome here is a stored, reviewable row; Claude is only called when a
 * hash comparison finds a REAL change; a missing/empty diff produces no
 * proposal rather than inventing one.
 */
class ProviderCapabilityRadarTest extends TestCase
{
    use RefreshDatabase;

    private function scanner(FakeAnthropicClient $ai): ProviderCapabilityScanService
    {
        return new ProviderCapabilityScanService(new CapabilityDiffService, $ai);
    }

    public function test_the_first_ever_fetch_stores_a_baseline_with_no_proposal(): void
    {
        $ai = new FakeAnthropicClient(['summary' => 'x']);
        $watcher = new FakeCapabilityWatcher('acme', 'v1 content');

        $results = $this->scanner($ai)->scan([$watcher]);

        $this->assertSame('baseline stored', $results['acme']);
        $this->assertDatabaseCount('provider_capability_snapshots', 1);
        $this->assertDatabaseCount('capability_proposals', 0);
    }

    public function test_an_unchanged_signal_produces_no_new_snapshot_or_proposal(): void
    {
        $ai = new FakeAnthropicClient(['summary' => 'x']);
        $watcher = new FakeCapabilityWatcher('acme', 'v1 content');

        $this->scanner($ai)->scan([$watcher]); // baseline
        $results = $this->scanner($ai)->scan([$watcher]); // identical run

        $this->assertSame('unchanged', $results['acme']);
        $this->assertDatabaseCount('provider_capability_snapshots', 1);
        $this->assertDatabaseCount('capability_proposals', 0);
    }

    public function test_a_real_change_generates_an_evidence_linked_proposal(): void
    {
        $ai = new FakeAnthropicClient([
            'summary' => 'Acme added bulk SMS sending.',
            'capability_added' => 'POST /bulk-send endpoint',
            'relevance_to_naara' => 'Could speed up OTP delivery at scale.',
            'proposed_feature' => 'Batch OTP dispatch via Acme bulk-send',
            'estimated_effort' => 'medium',
            'confidence' => 'high',
            'requires_human_verification_because' => 'Confirm the endpoint is generally available, not beta-gated.',
        ]);

        $this->scanner($ai)->scan([new FakeCapabilityWatcher('acme', 'v1 content')]);
        $results = $this->scanner($ai)->scan([new FakeCapabilityWatcher('acme', 'v2 content, new stuff')]);

        $this->assertSame('proposal generated', $results['acme']);
        $this->assertDatabaseCount('provider_capability_snapshots', 2);

        $proposal = CapabilityProposal::first();
        $this->assertSame('acme', $proposal->provider);
        $this->assertSame(CapabilityProposal::NEW, $proposal->status);
        $this->assertSame('high', $proposal->confidence);
        $this->assertSame('medium', $proposal->estimated_effort);
        $this->assertNotEmpty($proposal->requires_human_verification_because);
        $this->assertStringContainsString('+ v2 content, new stuff', $proposal->raw_diff);

        // Evidence link: the proposal's raw_diff traces back to a real,
        // fetched snapshot a reviewer can open — never a floating claim.
        $this->assertNotNull($proposal->sourceSnapshot);
        $this->assertSame('v2 content, new stuff', $proposal->sourceSnapshot->raw_content);
    }

    public function test_an_unavailable_ai_client_skips_proposal_generation_without_erroring(): void
    {
        $ai = new FakeAnthropicClient([], on: false);

        $this->scanner($ai)->scan([new FakeCapabilityWatcher('acme', 'v1')]);
        $results = $this->scanner($ai)->scan([new FakeCapabilityWatcher('acme', 'v2, changed')]);

        $this->assertSame('change detected, no proposal (AI unavailable)', $results['acme']);
        $this->assertDatabaseCount('provider_capability_snapshots', 2);
        $this->assertDatabaseCount('capability_proposals', 0);
    }

    public function test_a_failing_watcher_never_kills_the_whole_scan_run(): void
    {
        $ai = new FakeAnthropicClient(['summary' => 'x']);
        $bad = new class implements ProviderCapabilityWatcherInterface
        {
            public function provider(): string
            {
                return 'broken';
            }

            public function sourceType(): string
            {
                return self::CHANGELOG;
            }

            public function sourceDescription(): string
            {
                return 'fake://broken';
            }

            public function fetchCurrentSignal(): string
            {
                throw new \RuntimeException('network is down');
            }

            public function integratedServiceClass(): ?string
            {
                return null;
            }
        };
        $good = new FakeCapabilityWatcher('acme', 'v1');

        $results = $this->scanner($ai)->scan([$bad, $good]);

        $this->assertStringContainsString('error:', $results['broken']);
        $this->assertSame('baseline stored', $results['acme']);
    }

    public function test_the_text_diff_shows_added_and_removed_lines(): void
    {
        $diff = (new CapabilityDiffService)->textDiff("line one\nline two", "line one\nline three");

        $this->assertStringContainsString('- line two', $diff);
        $this->assertStringContainsString('+ line three', $diff);
        $this->assertStringNotContainsString('line one', $diff); // unchanged lines aren't noise
    }

    public function test_the_structural_diff_detects_added_removed_and_changed_fields(): void
    {
        $old = json_encode(['a' => 'string', 'b' => ['c' => 'int']]);
        $new = json_encode(['a' => 'string', 'b' => ['c' => 'string', 'd' => 'bool']]);

        $diff = (new CapabilityDiffService)->structuralDiff($old, $new);

        $this->assertContains('b.d', $diff['added']);
        $this->assertContains('b.c', $diff['changed']);
        $this->assertEmpty($diff['removed']);
    }

    public function test_response_shape_captures_key_names_and_types_never_real_values(): void
    {
        $shape = ResponseShape::of([
            'plans' => [
                ['id' => 'abc', 'price' => 12.5, 'active' => true],
            ],
        ]);

        $encoded = json_encode($shape);
        $this->assertStringContainsString('"id":"string"', $encoded);
        $this->assertStringContainsString('"price":"float"', $encoded);
        // The real value never leaks into the shape (money-safety rule 2).
        $this->assertStringNotContainsString('12.5', $encoded);
        $this->assertStringNotContainsString('abc', $encoded);
    }

    public function test_a_deferred_proposal_resurfaces_once_its_window_passes(): void
    {
        $snapshot = ProviderCapabilitySnapshot::create([
            'provider' => 'acme', 'source_type' => 'changelog',
            'content_hash' => 'x', 'raw_content' => 'x', 'fetched_at' => now(),
        ]);
        $proposal = CapabilityProposal::create([
            'provider' => 'acme', 'source_snapshot_id' => $snapshot->id,
            'raw_diff' => 'x', 'summary' => 'x', 'capability_added' => 'x',
            'relevance_to_naara' => 'x', 'proposed_feature' => 'x',
            'estimated_effort' => 'small', 'confidence' => 'low',
            'requires_human_verification_because' => 'x',
            'status' => CapabilityProposal::DEFERRED,
            'deferred_until' => now()->addDay(),
        ]);

        $this->assertFalse($proposal->isDue());

        $proposal->deferred_until = now()->subMinute();
        $this->assertTrue($proposal->isDue());
    }
}
