<?php

namespace App\Services\N2N\Connections;

use App\Jobs\N2N\SendInviteJob;
use App\Models\N2N\Invite;
use App\Models\User;
use App\Services\N2N\Credit\CreditGifts;
use App\Services\N2N\Identity\IdentityResolver;
use App\Services\N2N\Identity\PhoneNormalizer;
use App\Support\N2N\N2NSettings;

/**
 * Inviting someone who is not on Naara yet (N2N Batch 9, MASTER-ONLY). Order: resolve first (someone who can be found is on Naara, so the UI
 * offers Chat, never an invite), then daily limit, then deliver. An attached credit is NOT a new mechanism: it becomes a pending Batch 5
 * gift, which already carries the sender's referral link, escrow and 24h expiry. The clear address is never stored, only a keyed hash.
 */
class Invites
{
    public function __construct(private readonly IdentityResolver $resolver, private readonly CreditGifts $gifts) {}

    /** The shareable link for the share sheet / copy button (no address involved). */
    public function link(User $user): string
    {
        return url('/?ref='.$user->referral_code);
    }

    /** @return array{status: 'exists'|'sent'|'gift'|'invalid'|'limit'|'error', message: string} */
    public function invite(User $from, string $identifier, float $credits = 0.0, ?string $key = null, ?string $ip = null): array
    {
        $identifier = trim($identifier);
        [$kind, $norm] = $this->normalise($identifier, $from);
        if ($kind === null) {
            return ['status' => 'invalid', 'message' => 'Enter a valid email address or phone number.'];
        }
        if ($this->resolver->resolve($identifier, 'search', $from, $ip)->found) {
            return ['status' => 'exists', 'message' => 'That person is already on Naara. You can chat with them now.'];
        }
        $perDay = N2NSettings::int('invites_per_day', 20);
        if (Invite::query()->where('inviter_id', $from->id)->where('created_at', '>=', now()->subDay())->count() >= $perDay) {
            return ['status' => 'limit', 'message' => 'You have reached the daily invite limit.'];
        }
        $hash = $this->gifts->hash($kind, $norm);
        if (Invite::query()->where('inviter_id', $from->id)->where('target_hash', $hash)->where('created_at', '>=', now()->subDay())->exists()) {
            return ['status' => 'sent', 'message' => 'You already invited them today.'];   // never a second message to the same person within a day
        }

        if ($credits > 0) {
            $res = $this->gifts->create($from, $identifier, $credits, $key ?: 'inv:'.$hash.':'.now()->format('Ymd'), $ip);
            if (! $res['ok']) {
                return ['status' => 'error', 'message' => $res['message']];
            }
            Invite::query()->create(['inviter_id' => $from->id, 'kind' => $kind, 'target_hash' => $hash]);

            return ['status' => 'gift', 'message' => $res['message']];
        }
        Invite::query()->create(['inviter_id' => $from->id, 'kind' => $kind, 'target_hash' => $hash]);
        SendInviteJob::dispatch($from->id, $kind, $norm);

        return ['status' => 'sent', 'message' => 'Invite sent.'];
    }

    private function normalise(string $input, User $from): array
    {
        if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
            return ['email', strtolower($input)];
        }
        $p = PhoneNormalizer::normalise($input, $from->country_code);

        return $p ? ['phone', $p['e164']] : [null, null];
    }
}
