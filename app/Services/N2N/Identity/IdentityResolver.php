<?php

namespace App\Services\N2N\Identity;

use App\Models\N2N\UserPhone;
use App\Models\User;
use App\Support\N2N\N2NSettings;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Primitive P1 (N2N, MASTER-ONLY): Naara ID / @username / phone / email -> user, behind ONE code path used by chat, credit share, calls
 * and contact linking.
 *
 *  - purpose `search`: a person looking someone up. Honours "hide me", the phone discoverable flag and the email opt-in; throttled per
 *    user and per IP; returns a uniform shape (and a uniform minimum time) for a hit and a miss.
 *  - purpose `deliver`: server-side routing to a recipient the sender already addressed. Ignores discovery switches (they control being
 *    FOUND, not being reachable) and must never be echoed to the caller as "this hidden account exists".
 *
 * A Naara ID the person chose to share is always honoured: it is the handle you hand out on purpose.
 */
class IdentityResolver
{
    private const MIN_MICROS = 40000;

    public function resolve(string $input, string $purpose = 'search', ?User $actor = null, ?string $ip = null): ResolveResult
    {
        $started = microtime(true);
        $search = $purpose === 'search';
        if ($search) {
            $this->throttle($actor, $ip);
        }

        $user = $this->lookup(trim($input), $search, $actor);
        $result = $user === null ? ResolveResult::miss() : new ResolveResult(
            true, $user['user']->id, $user['user']->naara_id, $user['user']->username, $user['user']->name, $user['user']->avatar, $user['match'],
        );

        if ($search) {
            $left = self::MIN_MICROS - (int) ((microtime(true) - $started) * 1_000_000);
            if ($left > 0) {
                usleep($left);   // a hit and a miss take the same time, so timing is not an oracle
            }
        }

        return $result;
    }

    /** @return array{user: User, match: string}|null */
    private function lookup(string $input, bool $search, ?User $actor): ?array
    {
        if ($input === '' || strlen($input) > 254) {
            return null;
        }
        $active = fn ($q) => $q->where('is_active', true)->whereNull('anonymized_at');
        $visible = fn ($q) => $search ? $q->where('n2n_hide_me', false) : $q;

        if (($id = NaaraId::normalise($input)) !== null) {
            $u = User::query()->where('naara_id', $id)->tap($active)->first();

            return $u ? ['user' => $u, 'match' => 'naara_id'] : null;
        }
        if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
            $q = User::query()->where('email', strtolower($input))->tap($active)->tap($visible);
            if ($search) {
                $q->where('n2n_find_by_email', true);
            }
            $u = $q->first();

            return $u ? ['user' => $u, 'match' => 'email'] : null;
        }
        $bare = UsernamePolicy::normalise($input);
        $looksPhone = preg_match('/^\+?[\d\s\-().]{7,}$/', $input) === 1;   // 7+ digits is a phone number, not a username
        if (str_starts_with($input, '@') || (! $looksPhone && preg_match('/^[a-z0-9][a-z0-9_.]{1,19}$/i', $input) === 1)) {
            $u = User::query()->where('username', $bare)->tap($active)->tap($visible)->first();
            if ($u) {
                return ['user' => $u, 'match' => 'username'];
            }
            if (str_starts_with($input, '@')) {
                return null;
            }
        }
        if ($looksPhone) {
            $parsed = PhoneNormalizer::normalise($input, $actor?->country_code);
            if ($parsed !== null) {
                $q = UserPhone::query()->where('e164', $parsed['e164']);
                if ($search) {
                    $q->where('discoverable', true);
                }
                $phone = $q->first();
                $u = $phone ? User::query()->whereKey($phone->user_id)->tap($active)->tap($visible)->first() : null;

                return $u ? ['user' => $u, 'match' => 'phone'] : null;
            }
        }

        return null;
    }

    private function throttle(?User $actor, ?string $ip): void
    {
        $limit = N2NSettings::searchPerMinute();
        $keys = array_filter([$actor ? 'n2n-resolve:user:'.$actor->id : null, $ip ? 'n2n-resolve:ip:'.$ip : null]);
        foreach ($keys as $key) {
            if (RateLimiter::tooManyAttempts($key, str_contains($key, ':ip:') ? $limit * 3 : $limit)) {
                throw new ResolveThrottled('Too many lookups. Please slow down.');
            }
        }
        foreach ($keys as $key) {
            RateLimiter::hit($key, 60);
        }
    }
}
