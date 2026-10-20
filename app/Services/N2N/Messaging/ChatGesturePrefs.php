<?php

namespace App\Services\N2N\Messaging;

use App\Models\Setting;
use App\Models\User;

/**
 * Chat gesture and control preferences (N2N, MASTER-ONLY). Three layers, always clamped: built-in defaults, then the admin's platform defaults
 * (Admin > Naara Chat > Chat controls), then the member's own choices. The engine only ever receives the merged, validated result, so a bad stored
 * value can never reach the browser. "Buttons only" turns every gesture off; the visible alternatives (the long-press menu, the row buttons) remain.
 */
class ChatGesturePrefs
{
    public const SWIPE_START = ['info', 'forward', 'star', 'none'];

    public const DEFAULTS = [
        'double_tap_react' => true,
        'double_tap_code' => 'love',
        'swipe_reply' => true,
        'swipe_start' => 'info',
        'list_swipe' => true,
        'haptics' => true,
        'long_press_ms' => 450,
        'swipe_commit_px' => 56,
        'buttons_only' => false,
    ];

    /** The gestures the engine knows, and what each one may report (the stats endpoint refuses anything else). */
    public const GESTURES = ['double_tap', 'swipe_end', 'swipe_start', 'long_press', 'list_pin', 'list_archive', 'lightbox_zoom', 'lightbox_dismiss', 'vn_skip', 'peek', 'pull_search', 'drag_select', 'call_minimize'];

    public const OUTCOMES = ['fired', 'committed', 'cancelled', 'fallback', 'undone'];   // undone = taken back within a few seconds: the mis-trigger signal

    /** @return array<string, mixed> the merged, validated preferences for this member */
    public static function for(?User $user): array
    {
        $admin = (array) Setting::getValue('n2n.gesture_defaults', []);
        $own = $user && is_array($user->n2n_gesture_prefs) ? $user->n2n_gesture_prefs : [];

        return self::clean($own + $admin + self::DEFAULTS);
    }

    /** Validate and clamp any input down to the known keys and ranges. @return array<string, mixed> */
    public static function clean(array $in): array
    {
        $d = self::DEFAULTS;
        $code = (string) ($in['double_tap_code'] ?? $d['double_tap_code']);

        return [
            'double_tap_react' => (bool) ($in['double_tap_react'] ?? $d['double_tap_react']),
            'double_tap_code' => array_key_exists($code, MessageActions::REACTIONS) ? $code : $d['double_tap_code'],
            'swipe_reply' => (bool) ($in['swipe_reply'] ?? $d['swipe_reply']),
            'swipe_start' => in_array($in['swipe_start'] ?? null, self::SWIPE_START, true) ? $in['swipe_start'] : $d['swipe_start'],
            'list_swipe' => (bool) ($in['list_swipe'] ?? $d['list_swipe']),
            'haptics' => (bool) ($in['haptics'] ?? $d['haptics']),
            'long_press_ms' => max(300, min(1000, (int) ($in['long_press_ms'] ?? $d['long_press_ms']))),
            'swipe_commit_px' => max(40, min(120, (int) ($in['swipe_commit_px'] ?? $d['swipe_commit_px']))),
            'buttons_only' => (bool) ($in['buttons_only'] ?? $d['buttons_only']),
        ];
    }

    public static function save(User $user, array $in): array
    {
        $clean = self::clean($in);
        $user->forceFill(['n2n_gesture_prefs' => $clean])->save();

        return $clean;
    }

    public static function reset(User $user): void
    {
        $user->forceFill(['n2n_gesture_prefs' => null])->save();
    }

    /** What the browser engine reads: the merged preferences plus the emoji glyph for the double-tap reaction. */
    public static function forEngine(?User $user): array
    {
        $p = self::for($user);
        $p['glyph'] = MessageActions::REACTIONS[$p['double_tap_code']] ?? '';
        $p['reduced_motion_respected'] = true;

        return $p;
    }
}
