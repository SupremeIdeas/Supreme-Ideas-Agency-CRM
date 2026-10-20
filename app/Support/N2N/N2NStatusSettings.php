<?php

namespace App\Support\N2N;

use App\Models\Setting;
use App\Support\MediaStorage;

/**
 * Admin controls for Naara Chat status (MASTER-ONLY). Mode: auto (on only while a cloud store is configured), on (always, even with no cloud store:
 * then photos and voice use the server's private disk with tighter limits and video is refused), off. The brand-profile Highlights never depend on this.
 */
final class N2NStatusSettings
{
    public const HOURS = 24;

    public const MODES = ['auto', 'on', 'off'];

    public const TEXT_MAX = 700;

    public static function mode(): string
    {
        $m = (string) Setting::getValue('n2n.status.mode', 'auto');

        return in_array($m, self::MODES, true) ? $m : 'auto';
    }

    public static function cloudConfigured(): bool
    {
        return MediaStorage::r2Configured() || MediaStorage::wasabiConfigured();
    }

    public static function enabled(): bool
    {
        return match (self::mode()) {
            'on' => true,
            'off' => false,
            default => self::cloudConfigured(),
        };
    }

    /** Whether a status may tag people (admin switch, on by default). */
    public static function mentionsEnabled(): bool
    {
        return (bool) Setting::getValue('n2n.status.mentions', true);
    }

    /** Whether the in-app camera is offered (admin switch, on by default; a member whose browser has no camera always gets the gallery picker). */
    public static function cameraEnabled(): bool
    {
        return (bool) Setting::getValue('n2n.status.camera', true);
    }

    public static function dailyLimit(): int
    {
        return max(1, min(100, (int) Setting::getValue('n2n.status.daily_limit', 5)));
    }

    /** @return list<string> kinds a member may post right now */
    public static function allowedKinds(): array
    {
        if (! self::enabled()) {
            return [];
        }

        return self::cloudConfigured() ? ['text', 'image', 'video', 'voice'] : ['text', 'image', 'voice'];
    }

    /** Byte caps: generous on a cloud store, tight on the server's own disk so a status can never fill it. */
    public static function maxBytes(string $kind): int
    {
        $cloud = self::cloudConfigured();

        return match ($kind) {
            'image' => ($cloud ? 6 : 2) * 1048576,
            'video' => 16 * 1048576,
            'voice' => ($cloud ? 4 : 1) * 1048576,
            default => 0,
        };
    }

    public static function maxSeconds(string $kind): int
    {
        return $kind === 'video' ? 30 : 90;
    }

    public static function disk(): string
    {
        return MediaStorage::privateDisk();
    }
}
