<?php

namespace App\Support\MasterMarketing;

use App\Models\User;
use App\Models\WlFunnelInstance;
use App\Services\WhiteLabel\Funnel\WlFunnel;
use App\Services\WhiteLabel\Funnel\WlFunnelStates;

/**
 * The side widget that keeps an unfinished White Label purchase one tap away while the buyer looks around the platform (MASTER-ONLY: it lives under
 * `MasterMarketing`, so MasterOnlyModules keeps it out of every fork and package). It shows only for a signed-in person whose purchase is still open, never
 * on the purchase pages themselves, and it disappears for good the moment the purchase is paid (or the instance is closed).
 */
final class WlResumeWidget
{
    /** States in which the purchase is still open (not paid, not closed). */
    private const OPEN = [
        WlFunnelStates::REGISTERED, WlFunnelStates::PREVIEW_ACTIVE, WlFunnelStates::PREVIEW_EXPIRED, WlFunnelStates::WIZARD,
        WlFunnelStates::DRAFT_SAVED, WlFunnelStates::AWAITING_PAYMENT, WlFunnelStates::PAYMENT_REVIEW,
    ];

    /** @return array{code:string, plan:string, url:string, label:string, cta:string}|null */
    public static function for(?User $user, ?string $routeName = null): ?array
    {
        if (! $user || ! class_exists(WlFunnel::class) || ! WlFunnel::enabled()) {
            return null;
        }
        if ($routeName !== null && (str_starts_with($routeName, 'wl-funnel.') || str_starts_with($routeName, 'wl-custom-orders') || $routeName === 'welcome' || str_starts_with($routeName, 'handbooks.read') || str_starts_with($routeName, 'admin.') || $routeName === 'n2n.chat' || $routeName === 'support')) {   // never over the full-screen reader, the admin area, or a conversation (it would cover the thread)
            return null;
        }
        $inst = WlFunnelInstance::query()->where('user_id', $user->id)->whereIn('status', self::OPEN)->orderByDesc('id')->first();
        if (! $inst) {
            return null;
        }
        // A person who already owns a paid purchase never sees the widget again, even if an older draft is still lying around.
        if (WlFunnelInstance::query()->where('user_id', $user->id)->whereIn('status', [WlFunnelStates::PAID, WlFunnelStates::LICENSE_ISSUED, WlFunnelStates::SETUP, WlFunnelStates::LIVE])->exists()) {
            return null;
        }

        $review = $inst->status === WlFunnelStates::PAYMENT_REVIEW;

        return [
            'code' => $inst->public_code,
            'plan' => (string) (optional($inst->plan())->name ?? 'White Label'),
            'url' => WlFunnel::resumeUrl($inst),
            'label' => $review ? 'Your payment is being checked' : 'Your White Label is waiting',
            'cta' => $review ? 'See status' : 'Continue purchase',
        ];
    }
}
