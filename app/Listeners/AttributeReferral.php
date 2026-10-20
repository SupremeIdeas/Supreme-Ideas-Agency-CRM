<?php

namespace App\Listeners;

use App\Http\Middleware\CaptureReferral;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Cookie;

/**
 * Sets `referred_by` on a newly registered account from the referral cookie (CaptureReferral). Never overwrites an existing
 * attribution, never self-refers, and an unknown or malformed code is silently ignored: it must never break registration.
 */
class AttributeReferral
{
    public function handle(Registered $event): void
    {
        try {
            $user = $event->user;
            $code = request()->cookie(CaptureReferral::COOKIE);
            if (! $user instanceof User || $user->referred_by !== null || ! is_string($code) || preg_match('/^[A-Z0-9]{6,12}$/', $code) !== 1) {
                return;
            }
            $referrer = User::query()->where('referral_code', $code)->where('is_active', true)->first();
            if ($referrer !== null && $referrer->id !== $user->id) {
                $user->forceFill(['referred_by' => $referrer->id])->save();
            }
            Cookie::queue(Cookie::forget(CaptureReferral::COOKIE));
            Cookie::queue(Cookie::forget(CaptureReferral::KIND_COOKIE));
        } catch (\Throwable) {
            // best effort: attribution is a growth nicety, registration comes first
        }
    }
}
