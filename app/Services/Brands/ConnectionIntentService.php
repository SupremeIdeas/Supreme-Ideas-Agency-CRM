<?php

namespace App\Services\Brands;

use App\Models\BrandPartner;
use App\Models\ConnectionIntent;
use App\Models\User;
use App\Support\Notifications\Notify;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Connect-to-referral journey (Brand-Growth Phase 6, MASTER-ONLY). A visitor who opens a brand's Business Card gets two things remembered:
 * who sent them (the brand owner's referral code, through the existing `?ref=` capture) and which brand they came for (this cookie, 7 days).
 * When they sign up we record the intent and send ONE in-app prompt; connecting is always the person's own tap on the brand profile, never automatic.
 */
class ConnectionIntentService
{
    public const COOKIE = 'naara_brand_intent';

    public const DAYS = 7;

    /** Remember which brand a signed-out visitor came from. */
    public function remember(BrandPartner $brand): void
    {
        Cookie::queue(Cookie::make(self::COOKIE, (string) $brand->id, self::DAYS * 24 * 60, '/', null, request()->isSecure(), true, false, 'lax'));
    }

    /** The owner's referral code (created on first use, same format as the Referrals page), so a card sign-up is attributed to the brand. */
    public function referralCode(BrandPartner $brand): ?string
    {
        $owner = $brand->owner;
        if (! $owner instanceof User) {
            return null;
        }
        if (empty($owner->referral_code)) {
            $owner->forceFill(['referral_code' => Str::upper(Str::random(8))])->save();
        }

        return $owner->referral_code;
    }

    /** At sign-up: turn the cookie into an intent row and a single prompt. Never throws into registration. */
    public function claimForUser(User $user, ?string $cookieValue = null): ?ConnectionIntent
    {
        try {
            $raw = $cookieValue ?? request()->cookie(self::COOKIE);
            if (! is_string($raw) || ! ctype_digit($raw) || $raw === '') {
                return null;
            }
            $brand = BrandPartner::listed()->whereKey((int) $raw)->first();
            if (! $brand || (int) $brand->owner_user_id === (int) $user->id) {
                return null;
            }
            $intent = ConnectionIntent::firstOrCreate(['user_id' => $user->id, 'brand_partner_id' => $brand->id], ['source' => 'card', 'status' => ConnectionIntent::PENDING]);
            if ($intent->wasRecentlyCreated) {
                $intent->forceFill(['prompted_at' => now()])->save();
                Notify::event($user, 'brand.connect_prompt', ['brand' => $brand->brand_name], 'connect-prompt-'.$intent->id);
            }
            Cookie::queue(Cookie::forget(self::COOKIE));

            return $intent;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public const POPUP_MAX = 3;

    public const POPUP_GAP_HOURS = 24;

    /**
     * The one brand to ask about on the dashboard, or null. At most 3 popups per intent, a day apart; a brand that stopped being listed, or one the person already follows, is settled and never asked about.
     * With $count (the default) calling this counts as showing it, so a reload never repeats the popup inside the gap; the dashboard peeks without counting and marks it shown once it really opens.
     */
    public function nextPopup(User $user, bool $count = true): ?ConnectionIntent
    {
        $intent = ConnectionIntent::query()->where('user_id', $user->id)->where('status', ConnectionIntent::PENDING)->where('popup_count', '<', self::POPUP_MAX)
            ->where(fn ($q) => $q->whereNull('popup_last_at')->orWhere('popup_last_at', '<=', now()->subHours(self::POPUP_GAP_HOURS)))->orderBy('id')->with('brand')->first();
        if (! $intent) {
            return null;
        }
        $brand = $intent->brand;
        if (! $brand || ! BrandPartner::listed()->whereKey($brand->id)->exists()) {
            $this->settle($user, $brand ?? new BrandPartner(['id' => $intent->brand_partner_id]), false);

            return null;
        }
        if (\App\Models\BrandPartnerFollower::query()->where('user_id', $user->id)->where('brand_partner_id', $brand->id)->exists()) {
            $this->settle($user, $brand, true);

            return null;
        }
        if ($count) {
            $this->markShown($intent);
        }

        return $intent;
    }

    /** The popup actually opened on screen (it waits while another dialog is open), so count it now. */
    public function markShown(ConnectionIntent $intent): void
    {
        $intent->forceFill(['popup_count' => $intent->popup_count + 1, 'popup_last_at' => now()])->save();
    }

    /** "Not now": the popup returns tomorrow until it has been shown the maximum number of times, then the intent is closed for good. */
    public function notNow(User $user, int $intentId): void
    {
        $intent = ConnectionIntent::query()->where('user_id', $user->id)->where('status', ConnectionIntent::PENDING)->find($intentId);
        if ($intent && $intent->popup_count >= self::POPUP_MAX) {
            $intent->forceFill(['status' => ConnectionIntent::DISMISSED, 'resolved_at' => now()])->save();
        }
    }

    /** The person connected (from anywhere): settle any open intent. */
    public function settle(User $user, BrandPartner $brand, bool $connected): void
    {
        ConnectionIntent::where('user_id', $user->id)->where('brand_partner_id', $brand->id)->where('status', ConnectionIntent::PENDING)
            ->update(['status' => $connected ? ConnectionIntent::CONNECTED : ConnectionIntent::DISMISSED, 'resolved_at' => now()]);
    }
}
