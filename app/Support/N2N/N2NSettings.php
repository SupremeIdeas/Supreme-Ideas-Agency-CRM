<?php

namespace App\Support\N2N;

use App\Models\Setting;
use App\Support\FeatureEntitlements;

/**
 * Admin-owned knobs for N2N (MASTER-ONLY). Off by default; every limit is a setting, never a literal in code.
 */
class N2NSettings
{
    public const FLAG = 'n2n.enabled';

    /** N2N exists only on the master install, and only when the admin has switched it on. */
    public static function enabled(): bool
    {
        return FeatureEntitlements::isMaster() && (bool) Setting::getValue(self::FLAG, false);
    }

    /** The shipped Naara chat mark; the seeded `n2n.logo_url` setting points at it, and an admin upload replaces it (Admin > N2N). */
    public const DEFAULT_LOGO = 'images/brand/animations/naara-chat-mark-192.webp';

    /** The Naara chat logo for the shell and the quick chat button: the admin's setting (a full URL from an upload, or an asset path from the seed), never a literal in a view. */
    public static function logoUrl(): string
    {
        $v = trim((string) Setting::getValue('n2n.logo_url', ''));
        $v = $v !== '' ? $v : self::DEFAULT_LOGO;

        return preg_match('#^(https?:)?//#', $v) || str_starts_with($v, '/') ? $v : asset($v);
    }

    public static function int(string $key, int $default, int $min = 1): int
    {
        return max($min, (int) Setting::getValue('n2n.'.$key, $default));
    }

    public static function usernameCooldownDays(): int
    {
        return self::int('username_cooldown_days', 30, 0);
    }

    /** @return list<string> */
    public static function reservedUsernames(): array
    {
        $extra = array_map('trim', explode(',', strtolower((string) Setting::getValue('n2n.reserved_usernames', ''))));

        return array_values(array_unique(array_filter(array_merge((array) config('n2n.reserved_usernames'), $extra))));
    }

    public static function otpTtlMinutes(): int
    {
        return min(10, self::int('otp_ttl_minutes', 10));
    }

    public static function otpMaxAttempts(): int
    {
        return self::int('otp_max_attempts', 5);
    }

    public static function otpPerUserPerDay(): int
    {
        return self::int('otp_per_user_day', 5);
    }

    public static function otpPerNumberPerDay(): int
    {
        return self::int('otp_per_number_day', 5);
    }

    public static function otpPerIpPerDay(): int
    {
        return self::int('otp_per_ip_day', 20);
    }

    public static function otpResendSeconds(): int
    {
        return self::int('otp_resend_seconds', 60, 0);
    }

    public static function otpFrom(): ?string
    {
        return ((string) Setting::getValue('n2n.otp_from', (string) config('services.twilio.caller_id', ''))) ?: null;
    }

    public static function searchPerMinute(): int
    {
        return self::int('search_per_min', 20);
    }

    public static function importMaxContacts(): int
    {
        return self::int('import_max_contacts', 500);
    }

    public static function importPerDay(): int
    {
        return self::int('import_per_day', 3);
    }

    // ---- voice calls (Batch 10) -------------------------------------------------------------------------------------

    public static function callHoldMinutes(): int
    {
        return min(30, self::int('call_hold_minutes', 2));
    }

    /** Billing increment in seconds (default 60: whole minutes; an admin may choose a smaller one later). */
    public static function callIncrementSeconds(): int
    {
        return min(600, self::int('call_increment_s', 60));
    }

    public static function callRingSeconds(): int
    {
        return min(120, self::int('call_ring_s', 40, 10));
    }

    public static function callHeartbeatSeconds(): int
    {
        return min(30, self::int('call_heartbeat_s', 8, 3));
    }

    /** A side that has not beaten for this long is gone. Must exceed a few heartbeats. */
    public static function callStaleSeconds(): int
    {
        return max(self::callHeartbeatSeconds() * 3, self::int('call_stale_s', 30, 10));
    }

    public static function callWarnSeconds(): int
    {
        return self::int('call_warn_s', 30, 5);
    }

    public static function callAttemptsPerMinute(): int
    {
        return self::int('call_attempts_per_min', 6);
    }

    // ---- group calls (Batch 11) ---------------------------------------------------------------------------------------

    /** Hard cap on speakers in a room (the host counts as one when speaking). The product rule is 5; an admin may lower it. */
    public static function groupMaxSpeakers(): int
    {
        return min(5, self::int('group_max_speakers', 5));
    }

    /** Most people (speakers + listeners) in one room at launch. Conservative on purpose: see docs/N2N-GROUP-CALLS.md for the measured egress. */
    public static function groupMaxCapacity(): int
    {
        return min(100, self::int('group_max_capacity', 20, 2));
    }

    public static function groupMaxRooms(): int
    {
        return self::int('group_max_rooms', 5);
    }

    public static function groupLinkHours(): int
    {
        return min(24 * 30, self::int('group_link_hours', 24));
    }

    /** An open room nobody has beaten in for this long is closed by the sweeper. */
    public static function groupIdleMinutes(): int
    {
        return self::int('group_idle_minutes', 3);
    }

    public static function groupRoomsPerHostPerDay(): int
    {
        return self::int('group_rooms_per_day', 10);
    }
}
