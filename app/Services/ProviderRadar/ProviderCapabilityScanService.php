<?php

namespace App\Services\ProviderRadar;

use App\Models\CapabilityProposal;
use App\Models\ProviderCapabilitySnapshot;
use App\Services\AI\AnthropicClient;
use App\Services\ProviderRadar\Contracts\ProviderCapabilityWatcherInterface;
use App\Support\Auditor;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Throwable;

/**
 * Tier 5 #14 (Provider Capability Radar, master-only). Runs every registered
 * watcher, and ONLY calls Claude when a hash comparison finds a real change
 * — never on a fixed schedule regardless of whether anything actually
 * changed (keeps cost proportional to real upstream activity).
 *
 * No auto-implementation, no auto-deployment: this produces reviewable
 * `CapabilityProposal` rows only. A missing/empty diff produces no proposal
 * that day, rather than inventing one to have something to show.
 */
class ProviderCapabilityScanService
{
    public function __construct(
        private CapabilityDiffService $diff,
        private AnthropicClient $ai,
    ) {
    }

    /** @param list<ProviderCapabilityWatcherInterface> $watchers */
    public function scan(array $watchers): array
    {
        $results = [];

        foreach ($watchers as $watcher) {
            $provider = $watcher->provider();

            try {
                $results[$provider] = $this->scanOne($watcher);
            } catch (Throwable $e) {
                Log::warning("Provider capability scan failed for [{$provider}]: {$e->getMessage()}");
                $results[$provider] = 'error: '.$e->getMessage();
            }
        }

        return $results;
    }

    private function scanOne(ProviderCapabilityWatcherInterface $watcher): string
    {
        $signal = $watcher->fetchCurrentSignal();
        $hash = hash('sha256', $signal);

        $latest = ProviderCapabilitySnapshot::query()
            ->where('provider', $watcher->provider())
            ->where('source_type', $watcher->sourceType())
            ->latest('fetched_at')
            ->first();

        if ($latest !== null && $latest->content_hash === $hash) {
            return 'unchanged';
        }

        $snapshot = ProviderCapabilitySnapshot::create([
            'provider' => $watcher->provider(),
            'source_type' => $watcher->sourceType(),
            'content_hash' => $hash,
            'raw_content' => $signal,
            'fetched_at' => now(),
        ]);

        // First-ever snapshot for this provider+source — nothing to diff
        // against, so no proposal (there's no "change" yet, just a baseline).
        if ($latest === null) {
            return 'baseline stored';
        }

        $rawDiff = $this->diff->diffFor($watcher->sourceType(), $latest->raw_content, $signal);
        if (trim($rawDiff) === '') {
            return 'no material diff';
        }

        return $this->generateProposal($watcher, $snapshot, $rawDiff)
            ? 'proposal generated'
            : 'change detected, no proposal (AI unavailable)';
    }

    private function generateProposal(
        ProviderCapabilityWatcherInterface $watcher,
        ProviderCapabilitySnapshot $snapshot,
        string $rawDiff,
    ): bool {
        if (! $this->ai->enabled()) {
            Log::info("Capability change detected for [{$watcher->provider()}] but no Anthropic key configured — skipping proposal generation.");

            return false;
        }

        $integrated = $this->integratedMethods($watcher);

        $system = <<<SYS
        You review REAL, evidence-based diffs of provider API changes for NaaraSim,
        a Pan-African travel-connectivity SaaS selling eSIM data and virtual/verification
        phone numbers. You only propose based on the supplied diff content — never on
        your own prior knowledge of this provider, since that knowledge may be stale or
        wrong. If the diff shows nothing that plausibly enables a new NaaraSim feature,
        say so honestly in your summary rather than inventing significance.

        What NaaraSim currently integrates of {$watcher->provider()}'s API today (real method list, not a guess):
        {$integrated}

        Respond with ONLY a JSON object with exactly these keys:
        {
          "summary": string,
          "capability_added": string,
          "relevance_to_naara": string,
          "proposed_feature": string,
          "estimated_effort": "small" | "medium" | "large",
          "confidence": "high" | "medium" | "low",
          "requires_human_verification_because": string
        }
        The last field is mandatory on every response — state a genuine, specific
        reason a human should verify this before acting on it, never a throwaway phrase.
        SYS;

        $proposal = $this->ai->completeJson($system, [
            ['role' => 'user', 'content' => $rawDiff],
        ]);

        CapabilityProposal::create([
            'provider' => $watcher->provider(),
            'source_snapshot_id' => $snapshot->id,
            'raw_diff' => $rawDiff,
            'summary' => (string) ($proposal['summary'] ?? ''),
            'capability_added' => (string) ($proposal['capability_added'] ?? ''),
            'relevance_to_naara' => (string) ($proposal['relevance_to_naara'] ?? ''),
            'proposed_feature' => (string) ($proposal['proposed_feature'] ?? ''),
            'estimated_effort' => in_array($proposal['estimated_effort'] ?? null, ['small', 'medium', 'large'], true)
                ? $proposal['estimated_effort'] : 'medium',
            'confidence' => in_array($proposal['confidence'] ?? null, ['high', 'medium', 'low'], true)
                ? $proposal['confidence'] : 'low',
            'requires_human_verification_because' => (string) ($proposal['requires_human_verification_because'] ?? 'Always verify AI-generated proposals against the linked raw evidence before acting.'),
            'status' => CapabilityProposal::NEW,
        ]);

        Auditor::log('capability_radar.proposal_generated', 'ProviderCapabilitySnapshot', $snapshot->id, [
            'provider' => $watcher->provider(),
        ]);

        return true;
    }

    /** A real, reflected method list — never a guess — for Claude's system prompt. */
    private function integratedMethods(ProviderCapabilityWatcherInterface $watcher): string
    {
        $class = $watcher->integratedServiceClass();
        if ($class === null || ! class_exists($class)) {
            return '(no single integrated service class identified for this provider)';
        }

        $methods = collect((new ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC))
            ->reject(fn ($m) => $m->isConstructor() || $m->isStatic())
            ->map(fn ($m) => $m->getName())
            ->values();

        return $methods->isEmpty() ? '(no public methods found)' : $methods->implode(', ');
    }
}
