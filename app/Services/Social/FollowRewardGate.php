<?php

namespace App\Services\Social;

use App\Models\BrandPartnerHandle;
use App\Models\FollowRewardStart;
use App\Models\Setting;
use App\Models\SocialFollowClaim;
use App\Models\SocialFollowHandle;
use App\Models\User;

/**
 * Honest friction in front of follow rewards (Brand-Growth Phase 7, MASTER-ONLY). NaaraSim cannot see whether someone really follows an account on
 * another platform, so rewards are honour-based; this gate makes farming costly without bothering real people:
 *   1. the person must press Follow first (we record the moment), and may claim only after a short return delay (default 8 s);
 *   2. a verified email (and optionally a minimum account age) is required;
 *   3. a per-network-address and per-person hourly ceiling on claims.
 * The one-claim-per-handle guarantee and the daily cap still live in SocialFollowService; this only decides "not yet / not allowed" with a specific, friendly reason.
 */
class FollowRewardGate
{
    public static function enabled(): bool
    {
        return (bool) Setting::getValue('brand.follow_reward.enabled', true);
    }

    public static function minSeconds(): int
    {
        return max(0, (int) Setting::getValue('brand.follow_reward.min_seconds', 8));
    }

    public static function requireVerifiedEmail(): bool
    {
        return (bool) Setting::getValue('brand.follow_reward.require_verified_email', true);
    }

    public static function minAccountHours(): int
    {
        return max(0, (int) Setting::getValue('brand.follow_reward.min_account_hours', 0));
    }

    public static function maxPerIpHour(): int
    {
        return max(1, (int) Setting::getValue('brand.follow_reward.max_per_ip_hour', 10));
    }

    public static function maxPerUserHour(): int
    {
        return max(1, (int) Setting::getValue('brand.follow_reward.max_per_user_hour', 20));
    }

    /** Record that the person pressed Follow (keeps the earliest press: pressing again must not reset or fast-forward the clock). */
    public function begin(User $user, SocialFollowHandle|BrandPartnerHandle $handle): void
    {
        $col = $this->col($handle);
        FollowRewardStart::firstOrCreate(['user_id' => $user->id, $col => $handle->id], [
            'started_at' => now(), 'ip_hash' => $this->ipHash(), 'ua_hash' => $this->uaHash(),
        ]);
    }

    /** @return array{code:string,message:string,wait:int}|null why a claim must wait or be refused right now, or null when it may proceed */
    public function denial(User $user, SocialFollowHandle|BrandPartnerHandle $handle): ?array
    {
        if (! self::enabled()) {
            return null;
        }
        $col = $this->col($handle);
        if (SocialFollowClaim::where('user_id', $user->id)->where($col, $handle->id)->exists()) {
            return null;   // already claimed: the service answers that itself
        }
        if (self::requireVerifiedEmail() && ! $user->hasVerifiedEmail()) {
            return $this->deny('email', 'Confirm your email address to claim follow rewards.');
        }
        if (self::minAccountHours() > 0 && $user->created_at && $user->created_at->gt(now()->subHours(self::minAccountHours()))) {
            return $this->deny('account_new', 'New accounts can claim follow rewards after '.self::minAccountHours().' hours.');
        }
        $start = FollowRewardStart::where('user_id', $user->id)->where($col, $handle->id)->first();
        if (! $start) {
            return $this->deny('not_started', 'Press Follow first, then come back to claim.');
        }
        $wait = self::minSeconds() - (int) $start->started_at->diffInSeconds(now(), true);
        if ($wait > 0) {
            return $this->deny('too_soon', "Give it a moment: you can claim in {$wait} seconds.", $wait);
        }
        $since = now()->subHour();
        if (SocialFollowClaim::where('user_id', $user->id)->where('claimed_at', '>=', $since)->count() >= self::maxPerUserHour()) {
            return $this->deny('velocity_user', 'That is a lot of follows in an hour. Try again a little later.');
        }
        $ip = $this->ipHash();
        if ($ip !== null && SocialFollowClaim::where('ip_hash', $ip)->where('claimed_at', '>=', $since)->count() >= self::maxPerIpHour()) {
            return $this->deny('velocity_ip', 'Lots of follow rewards have been claimed from this connection in the last hour. Try again a little later.');
        }

        return null;
    }

    /** Stamp the network fingerprint on a freshly created claim (feeds the velocity ceiling and the admin audit). */
    public function stampClaim(SocialFollowClaim $claim): void
    {
        $claim->forceFill(['ip_hash' => $this->ipHash()])->save();
    }

    private function deny(string $code, string $message, int $wait = 0): array
    {
        return ['code' => $code, 'message' => $message, 'wait' => $wait];
    }

    private function col(SocialFollowHandle|BrandPartnerHandle $h): string
    {
        return $h instanceof BrandPartnerHandle ? 'brand_partner_handle_id' : 'handle_id';
    }

    private function ipHash(): ?string
    {
        $ip = request()->ip();

        return $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null;
    }

    private function uaHash(): ?string
    {
        $ua = (string) request()->userAgent();

        return $ua !== '' ? hash('sha256', $ua) : null;
    }
}
