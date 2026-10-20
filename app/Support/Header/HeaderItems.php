<?php

namespace App\Support\Header;

use App\Models\Merchant;
use App\Models\User;
use App\Models\UserHeaderPref;
use App\Support\ComingSoon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * The dashboard header shortcuts a member can choose (owner request, 2026-10-11). At most MAX are shown so the header never crowds a phone; the
 * notification bell and the menu button are always there and do not count. Which shortcuts EXIST depends on the install: language and theme
 * everywhere; NaaraBrain, Naara chat and the Business Suite only where those modules are installed and switched on. The merchant panel button is
 * separate (merchants only, mobile only) and has its own switch.
 */
final class HeaderItems
{
    public const MAX = 3;

    /** key => label, icon. Order here is the default order. */
    public const CATALOGUE = [
        'language' => ['label' => 'Language', 'hint' => 'Switch the app language', 'icon' => 'globe'],
        'theme' => ['label' => 'Dark / light mode', 'hint' => 'Flip between dark and light', 'icon' => 'moon'],
        'brain' => ['label' => 'NaaraBrain', 'hint' => 'Ask NaaraBrain in a pop-up', 'icon' => 'sparkles'],
        'chat' => ['label' => 'Naara chat', 'hint' => 'Jump to your chats', 'icon' => 'message-circle'],
        'business' => ['label' => 'Business Suite', 'hint' => 'Open your business workspace', 'icon' => 'briefcase'],
    ];

    /** What a member who never touched the setting sees: dark/light plus NaaraBrain where it exists. Language lives in the global menu by default (owner, 2026-10-11) and can be pinned here. */
    public const DEFAULTS = ['theme', 'brain'];

    /** Is this shortcut something this install can offer right now? (A switched-off module is not offered, never shown as a dead icon.) */
    public static function exists(string $key, ?User $user = null): bool
    {
        return match ($key) {
            'language', 'theme' => true,
            'brain' => class_exists(\App\Support\Brain\BrainSettings::class) && ComingSoon::isLive('brain') && Route::has('brain.chat'),
            'chat' => ComingSoon::isLive('n2n') && Route::has('n2n.chat'),
            'business' => ComingSoon::isLive('business_suite') && Route::has('business'),
            default => false,
        };
    }

    /** @return array<string, array{label: string, hint: string, icon: string}> only the shortcuts this install offers */
    public static function available(?User $user = null): array
    {
        return array_filter(self::CATALOGUE, fn ($_, $k) => self::exists($k, $user), ARRAY_FILTER_USE_BOTH);
    }

    private static function pref(?User $user): ?UserHeaderPref
    {
        if (! $user || ! Schema::hasTable('user_header_prefs')) {
            return null;
        }

        return UserHeaderPref::query()->where('user_id', $user->id)->first();
    }

    /** The shortcuts to render, in order, limited to MAX and to what exists. @return list<string> */
    public static function chosen(?User $user): array
    {
        $stored = self::pref($user)?->items;
        $keys = is_array($stored) ? $stored : self::DEFAULTS;

        return array_slice(array_values(array_filter(array_unique($keys), fn ($k) => is_string($k) && self::exists($k, $user))), 0, self::MAX);
    }

    /** True when this member runs an approved merchant account (the merchant panel exists for them). */
    public static function isMerchant(?User $user): bool
    {
        $m = $user?->merchantAccount;

        return $m instanceof Merchant && $m->isActive();
    }

    public static function showMerchantButton(?User $user): bool
    {
        return self::isMerchant($user) && (self::pref($user)?->merchant_button ?? true);
    }

    /**
     * Save the chosen shortcuts. Unknown or unavailable keys are dropped and the list is cut to MAX, so a crafted request can never exceed it.
     *
     * @param list<string> $keys
     * @return list<string> what was stored
     */
    public static function save(User $user, array $keys, ?bool $merchantButton = null): array
    {
        $clean = array_slice(array_values(array_filter(array_unique($keys), fn ($k) => is_string($k) && self::exists($k, $user))), 0, self::MAX);
        $data = ['items' => $clean];
        if ($merchantButton !== null) {
            $data['merchant_button'] = $merchantButton;
        }
        UserHeaderPref::updateOrCreate(['user_id' => $user->id], $data);

        return $clean;
    }
}
