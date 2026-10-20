<?php

namespace App\Services\Kyc;

use App\Models\KycVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Stripe Identity (UX/localization/platform-reach blueprint Phase F) — the
 * global KYC provider for users outside Dojah/Smile ID's pan-African
 * coverage. Stripe hosts the document + selfie capture flow itself, so
 * submit() creates a VerificationSession and returns `pending` with a
 * `redirectUrl` to Stripe's hosted page; the final decision arrives via the
 * `identity.verification_session.*` webhook, mirroring Smile ID's
 * callback-driven shape.
 *
 * Uses the same `services.stripe.secret_key` as the payment/payout Stripe
 * integrations (one Stripe account, one API key) — only the webhook secret
 * is separate, since Identity events arrive on their own endpoint in the
 * Stripe dashboard.
 */
class StripeIdentityKycProvider implements KycProviderInterface
{
    private const TOLERANCE_SECONDS = 300;

    public function name(): string
    {
        return 'stripe_identity';
    }

    public function available(): bool
    {
        return filled(config('services.stripe.secret_key'));
    }

    public function submit(KycVerification $verification, array $data): KycResult
    {
        try {
            $response = Http::asForm()
                ->withToken((string) config('services.stripe.secret_key'))
                ->post(rtrim((string) config('services.stripe.base_url'), '/').'/identity/verification_sessions', [
                    'type' => 'document',
                    'metadata' => ['reference' => $verification->reference],
                    'options' => ['document' => ['require_live_capture' => true]],
                ])->throw()->json();
        } catch (\Throwable $e) {
            return new KycResult(status: KycVerification::FAILED, reason: 'Could not reach Stripe Identity.');
        }

        $url = (string) ($response['url'] ?? '');
        if ($url === '') {
            return new KycResult(status: KycVerification::FAILED, reason: 'Stripe Identity did not return a verification link.');
        }

        return new KycResult(status: KycVerification::PENDING, redirectUrl: $url);
    }

    public function verifyWebhook(Request $request): bool
    {
        $header = $request->header('Stripe-Signature');
        $secret = (string) config('services.stripe.identity_webhook_secret');
        if (! is_string($header) || $secret === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $piece) {
            [$k, $v] = array_pad(explode('=', $piece, 2), 2, null);
            $parts[trim((string) $k)] = trim((string) $v);
        }

        $timestamp = $parts['t'] ?? null;
        $signature = $parts['v1'] ?? null;
        if ($timestamp === null || $signature === null) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhook(Request $request): ?KycEvent
    {
        $ref = (string) $request->input('data.object.metadata.reference', '');
        if ($ref === '') {
            return null;
        }

        $type = (string) $request->input('type', '');

        return match ($type) {
            'identity.verification_session.verified' => new KycEvent(
                provider: $this->name(),
                reference: $ref,
                status: KycVerification::APPROVED,
            ),
            'identity.verification_session.requires_input',
            'identity.verification_session.canceled' => new KycEvent(
                provider: $this->name(),
                reference: $ref,
                status: KycVerification::REJECTED,
                reason: (string) $request->input('data.object.last_error.reason', 'Not verified.'),
            ),
            default => null, // e.g. .processing / .created — no decision yet
        };
    }
}
