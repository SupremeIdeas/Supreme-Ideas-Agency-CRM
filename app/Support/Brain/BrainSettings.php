<?php

namespace App\Support\Brain;

use App\Models\Setting;
use App\Support\FeatureEntitlements;
use Illuminate\Http\Request;

/** Admin-owned knobs for NaaraBrain (MASTER-ONLY). Off by default; every limit is a setting (brain.<key>) that falls back to config/brain.php. */
class BrainSettings
{
    public const FLAG = 'brain.enabled';

    /** NaaraBrain exists only on the master install, and only when the admin has switched it on. */
    public static function enabled(): bool
    {
        return FeatureEntitlements::isMaster() && (bool) Setting::getValue(self::FLAG, false);
    }

    public static function int(string $key, int $min = 1): int
    {
        return max($min, (int) Setting::getValue('brain.'.$key, config('brain.'.$key)));
    }

    /** @return list<string> */
    public static function list(string $key): array
    {
        $v = Setting::getValue('brain.'.$key, config('brain.'.$key));

        return array_values(array_map('strval', is_array($v) ? $v : []));
    }

    /** @return array<string, array{class: string, label: string, hint: string}> */
    public static function depths(): array
    {
        return (array) config('brain.depths');
    }

    public static function depth(?string $d): string
    {
        return array_key_exists((string) $d, self::depths()) ? (string) $d : (string) config('brain.default_depth', 'balanced');
    }

    /** The brand assets (committed WebP, never hotlinked). `still` is the reduced-motion poster of the same animation. */
    public static function logo(string $kind, bool $still = false): string
    {
        $kinds = ['mark', 'splash', 'thinking', 'chat'];
        $kind = in_array($kind, $kinds, true) ? $kind : 'mark';

        return asset('images/brand/brain/naarabrain-'.$kind.($still ? '-still' : '').'.webp');
    }

    // ---- The floating NaaraBrain widget (admin: Admin > NaaraBrain) ----

    /** Sections a person can see the widget in. Each maps to route-name patterns; the admin ticks the sections, never types a pattern. */
    public const SECTIONS = [
        'dashboard' => ['label' => 'Home', 'routes' => ['dashboard']],
        'esim' => ['label' => 'eSIMs', 'routes' => ['catalogue', 'products', 'data-estimator']],
        'numbers' => ['label' => 'Numbers and calling', 'routes' => ['numbers', 'numbers.*']],
        'wallet' => ['label' => 'Wallet and receipts', 'routes' => ['wallet', 'receipts', 'referrals']],
        'rewards' => ['label' => 'Rewards and journey', 'routes' => ['rewards', 'rewards.*', 'journey']],
        'gifts' => ['label' => 'Gifts', 'routes' => ['gift-cards', 'gift-cards.*']],
        'account' => ['label' => 'Account and profile', 'routes' => ['account', 'account.*', 'profile', 'security']],
    ];

    public const DEFAULT_SECTIONS = ['dashboard', 'esim', 'wallet'];

    /** Never, whatever is ticked: already in Brain, or a place a floating button is noise or in the way. */
    private const NEVER = ['brain.*', 'support', 'help.*', 'checkout*', 'admin.*', 'n2n.*', 'business*', 'login', 'register', 'password.*', 'verification.*', 'two-factor.*', 'onboarding', 'welcome'];

    public static function widgetOn(): bool
    {
        return self::enabled() && (bool) Setting::getValue('brain.widget_enabled', true);
    }

    /** Left by default: the right-hand corner already carries the Wizard, My Journey and WhatsApp. */
    public static function widgetSide(): string
    {
        return Setting::getValue('brain.widget_side', 'left') === 'right' ? 'right' : 'left';
    }

    /** Phone only: pixels above the screen's bottom edge, so the button sits right above the bottom nav. */
    public static function widgetOffset(): int
    {
        return max(60, min(320, (int) Setting::getValue('brain.widget_offset', 96)));
    }

    public static function dismissHours(): int
    {
        return max(1, (int) Setting::getValue('brain.widget_dismiss_hours', 24));
    }

    /** @return list<string> ticked section keys */
    public static function widgetSections(): array
    {
        $v = Setting::getValue('brain.widget_sections', self::DEFAULT_SECTIONS);

        return array_values(array_intersect(array_keys(self::SECTIONS), is_array($v) ? $v : self::DEFAULT_SECTIONS));
    }

    public static function widgetShouldShow(Request $request): bool
    {
        if (! $request->user() || ! self::widgetOn() || $request->routeIs(...self::NEVER)) {
            return false;
        }
        $patterns = [];
        foreach (self::widgetSections() as $key) {
            array_push($patterns, ...self::SECTIONS[$key]['routes']);
        }

        return $patterns !== [] && $request->routeIs(...$patterns);
    }
}
