<?php

namespace App\Support\N2N;

use App\Models\Setting;
use App\Services\N2N\Messaging\Inbox;
use Illuminate\Http\Request;

/**
 * The quick "Naara chat" floating button (N2N, MASTER-ONLY), shown only where a fast jump into chat is useful and never on the dashboard home,
 * the chat shell itself, checkout, support or admin. Which routes show it is an admin Setting (comma-separated route-name patterns, `*`
 * allowed), so the owner can move it without a deploy.
 */
class FloatingChat
{
    public const DEFAULT_ROUTES = 'numbers.messages,numbers.contacts,numbers.dialer,numbers.lines,rewards.hunt,rewards.hunt.profile,referrals,wallet';

    /** Never, whatever the setting says: noise, or already in chat. */
    private const NEVER = ['dashboard', 'n2n.*', 'support', 'help.*', 'checkout*', 'admin.*', 'login', 'register', 'password.*', 'verification.*'];

    public static function enabled(): bool
    {
        return N2NSettings::enabled() && (bool) Setting::getValue('n2n.floating_chat', true);
    }

    /** @return list<string> */
    public static function routes(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) Setting::getValue('n2n.floating_chat_routes', self::DEFAULT_ROUTES)))));
    }

    public static function shouldShow(Request $request): bool
    {
        if (! $request->user() || ! self::enabled() || $request->routeIs(...self::NEVER)) {
            return false;
        }
        $patterns = self::routes();

        return $patterns !== [] && $request->routeIs(...$patterns);
    }

    public static function unread(): int
    {
        $u = auth()->user();

        return $u ? app(Inbox::class)->totalUnread($u) : 0;
    }

    public static function dismissHours(): int
    {
        return max(1, (int) Setting::getValue('n2n.floating_chat_dismiss_hours', 24));
    }
}
