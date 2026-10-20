<?php

namespace App\Services\Messaging;

use App\Models\Setting;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Services\N2N\Credit\Money;
use App\Services\N2N\Credit\N2NPricing;
use App\Services\N2N\Credit\UsageMeter;
use App\Services\N2N\Media\NaaraPro;
use App\Services\SMS\MessageSenderService;
use App\Services\SMS\NumberBlocklist;
use App\Services\Voice\SpamReportService;
use App\Services\Voice\VoiceDialerService;

/**
 * Everything the Conversation Router needs to know about the world, behind one seam (Brand-Growth Phase 9, MASTER-ONLY). The router holds the rules; this
 * class answers questions (balances, lines, prices, blocklists). Tests and the verify check swap in a fake so the charge matrix is proven without any provider.
 */
class ConversationRouterFacts
{
    /** N2N credit available to this person, in credits. */
    public function creditBalance(User $u): float
    {
        return Money::credits(app(UsageMeter::class)->available($u));
    }

    /** Main wallet balance in USD. */
    public function walletUsd(User $u): float
    {
        return round((float) ($u->wallet?->usd_balance ?? 0), 4);
    }

    public function isPro(User $u): bool
    {
        return NaaraPro::active($u);
    }

    /** The N2N message price in credits. */
    public function n2nMessageCredits(): float
    {
        return Money::credits(N2NPricing::messageCostCents());
    }

    public function n2nCallCreditsPerMinute(): float
    {
        return Money::credits(N2NPricing::callRatePerMinuteCents());
    }

    /** @return array{line: ?VirtualNumber, usd: ?float}|null a sending line and the per-message USD price, or null when the person has no line that can text */
    public function smsLineAndPrice(User $u, string $e164): ?array
    {
        $line = VirtualNumber::where('user_id', $u->id)->where('status', 'active')->get()->first(function (VirtualNumber $l) {
            $caps = (array) $l->capabilities;

            return ! array_key_exists('sms', $caps) || (bool) $caps['sms'];
        });
        if (! $line) {
            return null;
        }
        try {
            $q = app(MessageSenderService::class)->quote($line, 'x');

            return ['line' => $line, 'usd' => (float) $q['retail_total']];
        } catch (\Throwable) {
            return ['line' => $line, 'usd' => null];
        }
    }

    /** USD per minute for a destination, or null when no live quote can be had right now. */
    public function voiceUsdPerMinute(User $u, string $e164): ?float
    {
        try {
            return (float) app(VoiceDialerService::class)->quote($u, $e164)['retail_per_min'];
        } catch (\Throwable) {
            return null;
        }
    }

    public function numberBlocked(string $e164): bool
    {
        try {
            return app(NumberBlocklist::class)->isBlocked($e164) || app(SpamReportService::class)->isBlocked($e164);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Admin-set ISO-2 deny list (comma separated) and optional allow list; an empty allow list means "all not denied". */
    public function countryAllowed(?string $region): bool
    {
        $region = strtoupper((string) $region);
        $deny = array_filter(array_map('trim', explode(',', strtoupper((string) Setting::getValue('messaging.router.denied_countries', '')))));
        $allow = array_filter(array_map('trim', explode(',', strtoupper((string) Setting::getValue('messaging.router.allowed_countries', '')))));
        if ($region !== '' && in_array($region, $deny, true)) {
            return false;
        }

        return $allow === [] || ($region !== '' && in_array($region, $allow, true));
    }
}
