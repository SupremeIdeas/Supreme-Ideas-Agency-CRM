<?php

namespace App\Services\JourneyEngine;

use App\Models\BrandPartner;
use App\Models\CallEvent;
use App\Models\EsimOrder;
use App\Models\Referral;
use App\Models\Setting;
use App\Models\SmsOrder;
use App\Models\User;
use App\Models\VirtualNumber;
use Illuminate\Support\Facades\DB;

/**
 * The signal library: COUNTS AND STATES read live from the user's own account tables. By design (blueprint principle 8) nothing here reads a message body, a call
 * transcript, a voicemail or a contact; a test fails if this file ever mentions them. Each method returns null (the signal is not firing) or
 * ['subject' => string, 'priority' => ?string, 'evidence' => array<string, scalar>]. Thresholds are Settings (journey.*), never literals in logic.
 */
class Signals
{
    /** An eSIM was bought but is still not activated after N hours. */
    public function installGuide(User $user): ?array
    {
        $hours = (float) Setting::getValue('journey.install_hours', 6);
        $order = EsimOrder::where('user_id', $user->id)->whereIn('status', ['processing', 'active'])->whereNull('activated_at')
            ->where('created_at', '<=', now()->subHours((int) $hours))->where('created_at', '>=', now()->subDays(14))->latest('id')->first();

        return $order ? ['subject' => (string) $order->id, 'evidence' => ['hours_since_purchase' => (int) $order->created_at->diffInHours(now()), 'plan' => (string) ($order->plan?->name ?? 'your eSIM')]] : null;
    }

    /** Latest usage reading of an active eSIM crossed the warning (important) or critical threshold. */
    public function lowData(User $user): ?array
    {
        $warn = (float) Setting::getValue('journey.low_data_pct', 80);
        $crit = (float) Setting::getValue('journey.critical_data_pct', 95);
        foreach (EsimOrder::where('user_id', $user->id)->where('status', 'active')->get() as $order) {
            $snap = DB::table('esim_usage_snapshots')->where('esim_order_id', $order->id)->orderByDesc('captured_at')->first();
            if (! $snap || ! $snap->data_total_mb || $snap->data_total_mb <= 0) {
                continue;
            }
            $pct = (int) round(((int) $snap->data_used_mb) / (int) $snap->data_total_mb * 100);
            if ($pct >= $warn) {
                return ['subject' => (string) $order->id, 'priority' => $pct >= $crit ? 'critical' : 'important', 'evidence' => ['percent_used' => $pct, 'mb_left' => max(0, (int) $snap->data_remaining_mb)]];
            }
        }

        return null;
    }

    /** An active eSIM expires within the warning window (critical inside the last few hours). */
    public function esimExpiring(User $user): ?array
    {
        $warn = (int) (float) Setting::getValue('journey.expiry_hours', 48);
        $crit = (int) (float) Setting::getValue('journey.expiry_critical_hours', 6);
        $order = EsimOrder::where('user_id', $user->id)->where('status', 'active')->whereNotNull('expires_at')
            ->where('expires_at', '>', now())->where('expires_at', '<=', now()->addHours($warn))->orderBy('expires_at')->first();
        if (! $order) {
            return null;
        }
        $left = (int) max(1, now()->diffInHours($order->expires_at, false));

        return ['subject' => (string) $order->id, 'priority' => $left <= $crit ? 'critical' : 'important', 'evidence' => ['hours_left' => $left]];
    }

    /** A permanent number renews within N days and the wallet cannot cover it: the number would be released. */
    public function numberRenewal(User $user): ?array
    {
        $days = (int) (float) Setting::getValue('journey.renewal_days', 5);
        $balance = (float) ($user->wallet?->usd_balance ?? 0);
        $number = VirtualNumber::where('user_id', $user->id)->where('status', 'active')->whereNotNull('next_billing_date')
            ->whereDate('next_billing_date', '<=', now()->addDays($days)->toDateString())->orderBy('next_billing_date')->get()
            ->first(fn ($n) => (float) $n->monthly_retail > $balance);

        return $number ? ['subject' => (string) $number->id, 'evidence' => ['days_left' => (int) max(0, now()->startOfDay()->diffInDays($number->next_billing_date, false)), 'needed_usd' => round((float) $number->monthly_retail, 2), 'balance_usd' => round($balance, 2)]] : null;
    }

    /** The number of calls that rang out unanswered in the last 7 days (a COUNT only; who called, and what they said, is never read). */
    public function missedCalls(User $user): ?array
    {
        $min = (int) Setting::getValue('journey.missed_calls_7d', 3);
        $count = CallEvent::where('user_id', $user->id)->whereIn('status', ['no-answer', 'busy', 'failed'])->where('created_at', '>=', now()->subDays(7))->count();

        return $count >= $min ? ['subject' => '', 'evidence' => ['missed_calls_7d' => $count]] : null;
    }

    /** A success moment (a one-time code arrived) and the user has not referred anyone yet. */
    public function referralAfterSuccess(User $user): ?array
    {
        $recent = SmsOrder::where('user_id', $user->id)->where('status', 'completed')->where('completed_at', '>=', now()->subDays(3))->exists();
        if (! $recent || Referral::where('referrer_id', $user->id)->exists()) {
            return null;
        }

        return ['subject' => '', 'evidence' => ['event' => 'a one-time code arrived']];
    }

    /** J4 brand flow: the member's own brand listing was paused because the wallet could not cover its renewal (the brand is hidden from the directory until topped up). */
    public function brandListingPaused(User $user): ?array
    {
        $brand = BrandPartner::where('owner_user_id', $user->id)->where('listing_status', BrandPartner::STATUS_PAUSED)->orderBy('id')->first();

        return $brand ? ['subject' => (string) $brand->id, 'evidence' => ['brand' => (string) $brand->brand_name]] : null;
    }

    /** J4 brand flow: a brand was started but the listing is still not live after N hours (setup incomplete). */
    public function brandSetupIncomplete(User $user): ?array
    {
        $hours = (int) (float) Setting::getValue('journey.brand_setup_hours', 24);
        $brand = BrandPartner::where('owner_user_id', $user->id)->where('listing_status', BrandPartner::STATUS_PENDING)
            ->where('created_at', '<=', now()->subHours($hours))->where('created_at', '>=', now()->subDays(30))->orderBy('id')->first();

        return $brand ? ['subject' => (string) $brand->id, 'evidence' => ['brand' => (string) $brand->brand_name, 'hours_since_start' => (int) $brand->created_at->diffInHours(now())]] : null;
    }

    /** J4 brand flow: the member said they want to grow a brand (the `brand` intent, confirmed by them) and has no listing yet. Fires only because of that stated intent. */
    public function brandGetListed(User $user): ?array
    {
        return ['subject' => '', 'evidence' => ['intent' => 'you chose to grow your brand or music']];
    }

    /** True if the user owns something (used by `owns` suppression in rules). */
    public function owns(User $user, string $what): bool
    {
        return match ($what) {
            'line' => VirtualNumber::where('user_id', $user->id)->where('status', 'active')->exists(),
            'esim' => EsimOrder::where('user_id', $user->id)->exists(),
            'brand_listing' => BrandPartner::where('owner_user_id', $user->id)->exists(),
            default => false,
        };
    }
}
