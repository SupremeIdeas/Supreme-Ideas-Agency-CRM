<?php

namespace App\Services\Kyc;

use App\Models\KycVerification;
use App\Models\Merchant;
use App\Services\AI\AnthropicClient;
use App\Support\Auditor;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Oversight/Withdrawal blueprint Phase E2 — Claude-assisted cross-check for
 * KYC submissions that already reached manual review (a PENDING row: either
 * routed to the "manual" provider outright, or a provider left it pending —
 * e.g. Dojah's L3 CAC-lookup path for a non-CAC registration type). This is
 * an ADDITIONAL verification layer, never a replacement:
 *
 *   - It NEVER decides anything. approve()/reject() on KycService are
 *     completely unchanged; this only attaches `ai_assessment` for the human
 *     reviewer to read alongside the existing pending row.
 *   - Confident or not, the result is always routed to the same admin KYC
 *     review screen with Claude's reasoning attached — a human still clicks
 *     approve or reject. Full auto-approval on identity/financial
 *     verification is a materially bigger liability decision than
 *     AI-assisted triage; this deliberately defaults to the safer version.
 *   - Degrades to a complete no-op when no Anthropic key is configured,
 *     exactly like every other AI feature on this platform.
 *   - Never sees the raw ID/registration number — KycVerification.checks
 *     already excludes it by design (ManualKycProvider's own discipline),
 *     and this service only ever reads that same, already-redacted record.
 */
class KycCrossCheckService
{
    public function __construct(private AnthropicClient $ai) {}

    /**
     * Assess every currently-escalated (pending, not yet AI-assessed)
     * verification. Returns how many were assessed. Never throws — a single
     * bad row is logged and skipped so the rest of the batch still runs.
     */
    public function assessEscalated(): int
    {
        if (! $this->ai->enabled()) {
            return 0;
        }

        $count = 0;
        KycVerification::query()
            ->where('status', KycVerification::PENDING)
            ->whereNull('ai_assessed_at')
            ->with('user:id,name,email')
            ->chunkById(50, function ($chunk) use (&$count) {
                foreach ($chunk as $verification) {
                    if ($this->assess($verification)) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    /** Assess a single verification. Returns false (no-op, logged) on failure. */
    public function assess(KycVerification $verification): bool
    {
        if (! $this->ai->enabled()) {
            return false;
        }

        try {
            $result = $this->ai->completeJson($this->systemPrompt(), [
                ['role' => 'user', 'content' => json_encode($this->submissionSummary($verification))],
            ], 1024);
        } catch (Throwable $e) {
            Log::warning('kyc.ai_cross_check_failed', ['verification_id' => $verification->id, 'error' => $e->getMessage()]);

            return false;
        }

        $assessment = [
            'consistent' => (bool) ($result['consistent'] ?? false),
            'confidence' => in_array($result['confidence'] ?? null, ['low', 'medium', 'high'], true) ? $result['confidence'] : 'low',
            'reasoning' => (string) ($result['reasoning'] ?? ''),
        ];

        $verification->forceFill([
            'ai_assessment' => $assessment,
            'ai_assessed_at' => now(),
        ])->save();

        Auditor::log('kyc.ai_assessed', KycVerification::class, $verification->id, $assessment);

        return true;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            You are an advisory identity-verification triage assistant. You will be
            given a JSON summary of one KYC (identity/business verification)
            submission that a human reviewer is about to decide on. Assess whether
            the submitted details are INTERNALLY CONSISTENT — the account holder's
            name, the level requested, the provider's own structured findings, and
            (if present) the business name on file — with no contradictions.

            You are NOT approving or rejecting anything. A human always makes the
            final call. Respond with ONLY a JSON object of this exact shape:
            {"consistent": true|false, "confidence": "low"|"medium"|"high", "reasoning": "one or two sentences"}
            PROMPT;
    }

    /** @return array<string, mixed> */
    private function submissionSummary(KycVerification $verification): array
    {
        $businessName = null;
        if ($verification->level === KycVerification::L3 && $verification->user !== null) {
            $businessName = Merchant::where('owner_user_id', $verification->user->id)->value('business_name');
        }

        return [
            'account_name' => $verification->user?->name,
            'account_email' => $verification->user?->email,
            'level' => $verification->level === KycVerification::L3 ? 'business (L3)' : 'individual (L2)',
            'provider' => $verification->provider,
            'provider_checks' => $verification->checks,
            'business_name_on_file' => $businessName,
        ];
    }
}
