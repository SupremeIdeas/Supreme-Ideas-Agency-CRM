<?php

namespace App\Support\Business;

use App\Models\CallRecordingSetting;
use App\Models\VirtualNumber;
use libphonenumber\PhoneNumberUtil;

/**
 * Decides, per call, whether it is recorded and what the caller must be told or asked (MASTER-ONLY, Business Suite Prompt 15).
 * Pure decision logic: no I/O except reading settings. The resolver turns the answer into TwiML.
 */
class RecordingPolicy
{
    public const OFF = 'off';

    public const NOTICE = 'notice';

    public const CONSENT = 'consent';

    /**
     * @return array{mode: string, regime: ?string, from_country: ?string, to_country: ?string}
     *   mode: off (do not record), notice (announce, record), consent (announce, ask for 1, record only on 1)
     */
    public function decide(VirtualNumber $number, string $from, string $to): array
    {
        $off = ['mode' => self::OFF, 'regime' => null, 'from_country' => null, 'to_country' => null];

        if (! BusinessSettings::module('recording') || ! $number->recording_enabled || ! $number->wallet_group_id) {
            return $off;
        }
        $settings = CallRecordingSetting::query()->where('wallet_group_id', $number->wallet_group_id)->first();
        if (! $settings || ! $settings->recording_enabled) {
            return $off;
        }
        // The owner's plan must include recording: a lapsed plan stops NEW recordings at once (nothing already stored is touched).
        $group = \App\Models\WalletGroup::find($number->wallet_group_id);
        if ($group === null || ! app(\App\Services\Business\BusinessAccess::class)->allowsWorkspace($group, 'recording')) {
            return $off;
        }
        if ($this->neverRecord($from) || $this->neverRecord($to) || array_intersect([$from, $to], $settings->sensitiveList()) !== []) {
            return $off;
        }

        // Never record a call the owner's wallet could not pay for.
        $balance = (float) ($number->walletGroup?->owner?->wallet?->usd_balance ?? 0);
        if ($balance < BusinessSettings::recordingMinBalance()) {
            return $off;
        }

        $fromCountry = $this->country($from);
        $toCountry = $this->country($to);
        $regime = $this->regime($fromCountry, $toCountry);

        $mode = $regime === self::CONSENT && BusinessSettings::recordingConsentMode() === 'keypad' ? self::CONSENT : self::NOTICE;

        return ['mode' => $mode, 'regime' => $regime, 'from_country' => $fromCountry, 'to_country' => $toCountry];
    }

    /** Strictest wins; an unknown or unlisted country is strict. */
    public function regime(?string $a, ?string $b): string
    {
        $map = (array) config('call_recording.regimes', []);
        $default = (string) config('call_recording.default_regime', self::CONSENT);

        $regimes = array_map(fn (?string $c) => $c !== null ? ($map[$c] ?? $default) : $default, [$a, $b]);

        return in_array(self::CONSENT, $regimes, true) ? self::CONSENT : self::NOTICE;
    }

    public function country(string $e164): ?string
    {
        try {
            $util = PhoneNumberUtil::getInstance();

            return $util->getRegionCodeForNumber($util->parse($e164, null)) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function neverRecord(string $e164): bool
    {
        $digits = ltrim($e164, '+');
        foreach ((array) config('call_recording.never_record_prefixes', []) as $p) {
            if ($digits !== '' && str_starts_with($digits, (string) $p) && strlen($digits) <= strlen((string) $p) + 1) {
                return true;
            }
        }

        return false;
    }
}
