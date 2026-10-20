<?php

namespace App\Services\N2N\Identity;

use App\Jobs\N2N\LinkContactsJob;
use App\Jobs\N2N\SendPhoneOtpJob;
use App\Models\N2N\PhoneVerification;
use App\Models\N2N\UserPhone;
use App\Models\User;
use App\Support\Auditor;
use App\Support\N2N\N2NSettings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Proves a user can receive an SMS on a number before it is linked (N2N, MASTER-ONLY). The OTP is the most abusable surface in the
 * module, so: three daily caps (user / number / IP), a resend gap, a short expiry, an attempt lock-out, a keyed hash instead of the code,
 * and one uniform reply whether or not the number is already linked to someone else (never an enumeration oracle).
 */
class PhoneLinkService
{
    public const GENERIC_SENT = 'If this number can be linked, a code is on its way.';

    /** @return array{ok: bool, message: string, retry_after?: int} */
    public function start(User $user, string $rawPhone, ?string $ip): array
    {
        $parsed = PhoneNormalizer::normalise($rawPhone, $user->country_code);
        if ($parsed === null) {
            return ['ok' => false, 'message' => 'Enter a valid phone number, including the country code if it is not local.'];
        }
        $e164 = $parsed['e164'];
        $day = now()->subDay();
        $caps = [
            PhoneVerification::query()->where('user_id', $user->id)->where('created_at', '>=', $day)->count() >= N2NSettings::otpPerUserPerDay(),
            PhoneVerification::query()->where('e164', $e164)->where('created_at', '>=', $day)->count() >= N2NSettings::otpPerNumberPerDay(),
            $ip !== null && PhoneVerification::query()->where('ip', $ip)->where('created_at', '>=', $day)->count() >= N2NSettings::otpPerIpPerDay(),
        ];
        if (in_array(true, $caps, true)) {
            Auditor::log('n2n.phone_otp_capped', User::class, $user->id, []);

            return ['ok' => false, 'message' => 'Too many verification attempts. Please try again tomorrow.'];
        }
        $last = PhoneVerification::query()->where('user_id', $user->id)->latest('id')->first();
        $gap = N2NSettings::otpResendSeconds();
        if ($last !== null && $gap > 0 && $last->created_at->gt(now()->subSeconds($gap))) {
            return ['ok' => false, 'message' => 'Please wait a moment before requesting another code.', 'retry_after' => $gap - (int) $last->created_at->diffInSeconds(now())];
        }

        $linkedElsewhere = UserPhone::query()->where('e164', $e164)->where('user_id', '!=', $user->id)->exists();
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $row = PhoneVerification::create([
            'user_id' => $user->id, 'e164' => $e164, 'code_hash' => $this->hash($user->id, $e164, $code), 'suppressed' => $linkedElsewhere,
            'ip' => $ip, 'expires_at' => now()->addMinutes(N2NSettings::otpTtlMinutes()),
        ]);
        if (! $linkedElsewhere) {
            SendPhoneOtpJob::dispatch($row->id, $code);   // an external SMS call is always a queued job
        } else {
            Auditor::log('n2n.phone_conflict', User::class, $user->id, ['verification_id' => $row->id]);
        }

        return ['ok' => true, 'message' => self::GENERIC_SENT];
    }

    /** @return array{ok: bool, message: string} */
    public function verify(User $user, string $code): array
    {
        $fail = ['ok' => false, 'message' => 'That code is wrong or has expired.'];
        $row = PhoneVerification::query()->where('user_id', $user->id)->whereNull('verified_at')->where('expires_at', '>', now())->latest('id')->first();
        if ($row === null || $row->attempts >= N2NSettings::otpMaxAttempts()) {
            return $fail;
        }
        $row->increment('attempts');   // counted BEFORE the comparison, so a guess is never free
        $code = preg_replace('/\D/', '', $code) ?? '';
        if ($row->suppressed || ! hash_equals($row->code_hash, $this->hash($user->id, $row->e164, $code))) {
            return $fail;
        }

        $oldE164 = null;
        try {
            DB::transaction(function () use ($user, $row, &$oldE164) {
                $existing = UserPhone::query()->where('user_id', $user->id)->lockForUpdate()->first();
                $oldE164 = $existing?->e164;
                $existing?->delete();
                UserPhone::create([
                    'user_id' => $user->id, 'e164' => $row->e164, 'country_code' => PhoneNormalizer::normalise($row->e164)['region'] ?? null,
                    'verified_at' => now(), 'discoverable' => false,   // discovery by phone is opt-in (blueprint 4.3); the member turns it on in Identity settings
                ]);
                $row->forceFill(['verified_at' => now()])->save();
                PhoneVerification::query()->where('user_id', $user->id)->whereNull('verified_at')->delete();
                Auditor::log('n2n.phone_linked', User::class, $user->id, ['replaced' => $oldE164 !== null]);
            });
        } catch (QueryException) {
            // The unique index won a race: another account verified this number first.
            return ['ok' => false, 'message' => 'This number could not be linked. Contact support if it is yours.'];
        }
        LinkContactsJob::dispatch($user->id, $row->e164);
        app(\App\Services\N2N\Credit\CreditGifts::class)->claimForUser($user);   // a gift addressed to this number
        if ($oldE164 !== null) {
            LinkContactsJob::dispatch(null, $oldE164);
        }

        return ['ok' => true, 'message' => 'Your number is linked.'];
    }

    public function unlink(User $user, ?User $by = null): void
    {
        $phone = UserPhone::query()->where('user_id', $user->id)->first();
        if ($phone === null) {
            return;
        }
        $e164 = $phone->e164;
        $phone->delete();
        Auditor::log('n2n.phone_unlinked', User::class, $user->id, ['by' => $by?->id ?? $user->id]);
        LinkContactsJob::dispatch(null, $e164);   // contacts that pointed at this person unlink within one job cycle
    }

    public function setDiscoverable(User $user, bool $on): void
    {
        $changed = UserPhone::query()->where('user_id', $user->id)->update(['discoverable' => $on]);
        if ($changed) {
            $e164 = UserPhone::query()->where('user_id', $user->id)->value('e164');
            LinkContactsJob::dispatch(null, (string) $e164);
        }
    }

    /** Average measured OTP cost per SENT code and per ACTIVATION (linked number). Null when nothing is measured yet: never guessed. */
    public function costReport(): array
    {
        $sent = PhoneVerification::query()->where('suppressed', false)->whereNotNull('cost_usd');
        $totalCost = (float) (clone $sent)->sum('cost_usd');
        $activations = UserPhone::query()->count();

        return [
            'sent_with_cost' => (clone $sent)->count(),
            'avg_per_send_usd' => (clone $sent)->count() > 0 ? round($totalCost / (clone $sent)->count(), 5) : null,
            'activations' => $activations,
            'per_activation_usd' => $activations > 0 && $totalCost > 0 ? round($totalCost / $activations, 5) : null,
        ];
    }

    private function hash(int $userId, string $e164, string $code): string
    {
        return hash_hmac('sha256', $userId.'|'.$e164.'|'.$code, (string) config('app.key'));
    }
}
