<?php

namespace App\Support;

use App\Jobs\PollSmsOtpJob;
use App\Models\WhiteLabelLicensePlan;
use App\Support\Niche\SupportLinks;

/**
 * Resolves the small set of `{{token}}` placeholders an admin can type into a
 * FAQ item's answer (see `FaqSeeder`), so a fact that's really a live number
 * elsewhere in the app (the OTP refund timeout, the NaaraCredits earn rate,
 * the cheapest live white-label plan) can never quietly drift out of sync
 * with what the FAQ claims. An unrecognised `{{...}}` is left untouched
 * rather than stripped, so a typo is visible instead of silently vanishing.
 */
class FaqTokens
{
    public static function replace(string $html): string
    {
        return strtr($html, [
            '{{otp_timeout}}' => (string) PollSmsOtpJob::TIMEOUT_MINUTES,
            '{{credit_earn_rate}}' => (string) CreditSettings::all()['per_usd'],
            '{{whitelabel_from_price}}' => self::whiteLabelFromPrice(),
            '{{whatsapp_answer}}' => self::whatsappAnswer(),
        ]);
    }

    private static function whiteLabelFromPrice(): string
    {
        $min = WhiteLabelLicensePlan::where('is_active', true)->min('price_usd');

        return $min !== null ? '$'.number_format((float) $min).' upward' : 'a plan to suit you';
    }

    private static function whatsappAnswer(): string
    {
        if (! SupportLinks::hasWhatsapp()) {
            return 'Not right now — use in-app Support and Nia replies instantly.';
        }

        return 'Yes — message us at <a href="'.e(SupportLinks::whatsappUrl()).'">WhatsApp</a>.';
    }
}
