<?php

namespace App\Support\Business;

/**
 * Typed accessors for the Business Suite control panel (see BusinessSettingsSchema for the full list, defaults and help text).
 * MASTER-ONLY. Every limit falls back to its schema default, so a fresh install behaves exactly as documented without any row stored.
 */
class BusinessSettings
{
    public const WORKSPACE_CREATION = 'business.workspace_creation_open';

    public const ACCESS = 'business.access.';

    public const MODULE = 'business.module.';

    public const LIMIT = 'business.limit.';

    public const CALL = 'business.call.';

    public const RECORDING = 'business.recording.';

    public const COST = 'business.cost.';

    public const AI = 'business.ai_voice.';

    public const INBOX = 'business.inbox.';

    public const BRAIN = 'business.brain.';

    /** A module is live only when the Suite is on, the module has shipped and its own switch is on. */
    public static function module(string $name): bool
    {
        $field = BusinessSettingsSchema::field(self::MODULE.$name);
        if (! $field || ! ($field['available'] ?? true) || ! BusinessSuiteSettings::enabled()) {
            return false;
        }

        return (bool) BusinessSettingsSchema::current($field);
    }

    /** The AI voice agent is on and its server address is set. */
    public static function aiVoiceReady(): bool
    {
        return self::module('ai_voice') && str_starts_with(self::aiText('server_url'), 'wss://');
    }

    /** Used by ComingSoon::isLive('business_ai_voice'): the page is available once the module is on (the server address is a separate readiness check). */
    public static function aiVoiceLive(): bool
    {
        return self::module('ai_voice');
    }

    public static function aiText(string $name): string
    {
        return trim((string) self::value(self::AI.$name));
    }

    public static function aiInt(string $name): int
    {
        return (int) self::value(self::AI.$name);
    }

    /** Used by ComingSoon::isLive('omni_respond'). */
    public static function omniLive(): bool
    {
        return self::module('omni');
    }

    /** The native softphone (MASTER-ONLY): pairing, access tokens and the device list. */
    public static function softphoneLive(): bool
    {
        return self::module('softphone');
    }

    /** Used by ComingSoon::isLive('business_inbox'). */
    public static function inboxLive(): bool
    {
        return self::module('inbox');
    }

    /** Shared-inbox sending knobs (see the 'inbox_sending' group): bool for attachments/scheduling, int for the rest. */
    public static function inbox(string $name): int|bool
    {
        return self::value(self::INBOX.$name);
    }

    /** Used by ComingSoon::isLive('business_recording'). */
    public static function recordingLive(): bool
    {
        return self::module('recording');
    }

    /** Used by ComingSoon::isLive('business_call_flows'). */
    public static function callFlowsLive(): bool
    {
        return self::module('call_flows');
    }

    /** 'pro' (a plan must include the feature) or 'open' (anyone). */
    public static function accessModel(): string
    {
        return self::value(self::ACCESS.'model') === 'open' ? 'open' : 'pro';
    }

    public static function premiumSkinsReserved(): bool
    {
        return (bool) self::value(self::ACCESS.'premium_skins');
    }

    public static function creditRefillOn(): bool
    {
        return (bool) self::value(self::ACCESS.'credit_refill');
    }

    public static function workspaceCreationOpen(): bool
    {
        return (bool) self::value(self::WORKSPACE_CREATION);
    }

    public static function limit(string $name): int
    {
        return (int) self::value(self::LIMIT.$name);
    }

    public static function call(string $name): int
    {
        return (int) self::value(self::CALL.$name);
    }

    /** The NaaraBrain inbox assistant setting (monthly_actions | depth | context_messages | per_minute). */
    public static function brain(string $name): mixed
    {
        return self::value(self::BRAIN.$name);
    }

    public static function recordingDefaultRetention(): int
    {
        return (int) self::value(self::RECORDING.'default_retention_days');
    }

    public static function recordingRetentionCeiling(): int
    {
        return (int) self::value(self::RECORDING.'retention_ceiling_days');
    }

    public static function recordingMaxMinutes(): int
    {
        return (int) self::value(self::RECORDING.'max_minutes');
    }

    public static function recordingMinBalance(): float
    {
        return (float) self::value(self::RECORDING.'min_balance_usd');
    }

    /** 'keypad' or 'announce_only'. */
    public static function recordingConsentMode(): string
    {
        return (string) self::value(self::RECORDING.'consent_mode');
    }

    /** Supplier cost input in USD (never shown to a workspace): record_per_min | transcribe_per_min | intelligence_per_run. */
    public static function cost(string $name): float
    {
        return (float) self::value(self::COST.$name);
    }

    private static function value(string $key): mixed
    {
        return BusinessSettingsSchema::current(BusinessSettingsSchema::field($key));
    }
}
