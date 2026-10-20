<?php

namespace App\Services\N2N\Calls;

use App\Models\N2N\Call;
use App\Models\N2N\CallRating;
use App\Models\Setting;
use App\Models\User;
use App\Support\Trustpilot\TrustpilotSettings;

/**
 * The after-call "How was your call?" sheet and its Trustpilot invitation (MASTER-ONLY). Off until an admin switches it on AND the Trustpilot profile address is set.
 *
 * Trustpilot rules this honours: the invitation is offered to EVERYONE who answers, whatever stars they gave (no review gating, no filtering of happy customers),
 * nothing is promised or paid for a review, and we never see or touch what someone writes on Trustpilot. A low rating adds a support link, never replaces the invitation.
 * The stars are also kept with the call as a private quality signal.
 */
class CallReviewPrompt
{
    public static function enabled(): bool
    {
        return (bool) Setting::getValue('n2n.call_review_enabled', false) && self::reviewUrl() !== null;
    }

    public static function minSeconds(): int
    {
        return max(0, min(600, (int) Setting::getValue('n2n.call_review_min_s', 20)));
    }

    public static function cooldownDays(): int
    {
        return max(0, min(90, (int) Setting::getValue('n2n.call_review_cooldown_days', 7)));
    }

    /** Where "Write a review" goes: Trustpilot's own review form for the profile, derived from the saved profile address. Null when no Trustpilot profile is set. */
    public static function reviewUrl(): ?string
    {
        return TrustpilotSettings::reviewFormUrl();
    }

    /** Should this person be asked about this call right now? */
    public function eligible(User $user, Call $call): bool
    {
        if (! self::enabled() || ! $call->isParticipant($user->id) || $call->state !== 'ended') {
            return false;
        }
        if ($call->answered_at === null || $call->ended_at === null || $call->answered_at->diffInSeconds($call->ended_at) < self::minSeconds()) {
            return false;   // a call that barely happened is not worth a review
        }
        if (CallRating::query()->where('call_id', $call->id)->where('user_id', $user->id)->exists()) {
            return false;
        }

        return ! CallRating::query()->where('user_id', $user->id)->where('created_at', '>=', now()->subDays(self::cooldownDays()))->exists();
    }

    /** @return array{review_url:string, support_url:?string} */
    public function rate(User $user, Call $call, int $stars): array
    {
        $row = CallRating::query()->firstOrCreate(['call_id' => $call->id, 'user_id' => $user->id], ['video' => (bool) $call->video]);
        $row->forceFill(['stars' => max(1, min(5, $stars))])->save();

        return ['review_url' => (string) self::reviewUrl(), 'support_url' => $stars <= 3 && \Illuminate\Support\Facades\Route::has('support') ? route('support') : null];
    }

    public function dismiss(User $user, Call $call): void
    {
        CallRating::query()->firstOrCreate(['call_id' => $call->id, 'user_id' => $user->id], ['video' => (bool) $call->video]);
    }

    public function opened(User $user, Call $call): void
    {
        CallRating::query()->where('call_id', $call->id)->where('user_id', $user->id)->update(['trustpilot_opened' => true]);
    }
}
