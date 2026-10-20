<?php

namespace App\Support\Business;

use App\Models\MessageThread;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Models\WalletGroup;
use App\Support\ComingSoon;
use App\Support\Workspace;
use Illuminate\Support\Facades\Route;

/**
 * Navigation of the Business shell (MASTER-ONLY): one catalogue feeds the mobile bottom bar, the desktop rail and the More sheet, the same way the Naara chat
 * shell does. A destination whose module is off, or that this person may not use (recordings, channels and the assistant are owner/admin only), is simply
 * absent, so nothing is shown that leads to a dead end.
 */
class BusinessNav
{
    /** key => [route, label, icon, bar, admin, feature]. `bar` = eligible for the phone bottom bar; `admin` = workspace owner/admin only. */
    public const CATALOG = [
        'overview' => ['route' => 'business.workspace', 'label' => 'Overview', 'icon' => 'briefcase', 'bar' => true, 'admin' => false, 'feature' => null],
        'inbox' => ['route' => 'numbers.messages', 'label' => 'Inbox', 'icon' => 'inbox', 'bar' => true, 'admin' => false, 'feature' => null],
        'calls' => ['route' => 'business.call-flows', 'label' => 'Call flows', 'icon' => 'phone-forwarded', 'bar' => true, 'admin' => false, 'feature' => 'business_call_flows'],
        'recordings' => ['route' => 'business.recordings', 'label' => 'Recordings', 'icon' => 'mic', 'bar' => true, 'admin' => true, 'feature' => 'business_recording'],
        'respond' => ['route' => 'business.respond', 'label' => 'Respond', 'icon' => 'message-circle', 'bar' => false, 'admin' => false, 'feature' => 'omni_respond'],
        'channels' => ['route' => 'business.channels', 'label' => 'Channels', 'icon' => 'link', 'bar' => false, 'admin' => true, 'feature' => 'business_inbox'],
        'guides' => ['route' => 'business.guides', 'label' => 'Guides', 'icon' => 'book-open', 'bar' => false, 'admin' => false, 'feature' => null],
        'assistant' => ['route' => 'business.assistant', 'label' => 'AI assistant', 'icon' => 'sparkles', 'bar' => false, 'admin' => true, 'feature' => 'business_ai_voice'],
    ];

    /**
     * Where a "Business Suite" link should land. Someone who already has (or belongs to) a business workspace goes straight to its dashboard; the public
     * marketing page (with its own navigation) is only for people who do not have one yet, so owners never have to pass through it.
     */
    public static function entryRoute(?User $user): string
    {
        static $memo = [];
        if (! $user || ! Route::has('business.workspace') || ! BusinessSuiteSettings::enabled()) {
            return 'business';
        }

        return $memo[$user->id] ??= (function () use ($user) {
            try {
                return Workspace::switchableFor($user)->contains(fn ($g) => $g->isBusiness()) ? 'business.workspace' : 'business';
            } catch (\Throwable) {
                return 'business';
            }
        })();
    }

    /** Whether the page that is rendering should wear the Business shell: a business workspace is the active one. */
    public static function wants(): bool
    {
        return BusinessSuiteSettings::enabled() && Workspace::isBusinessActive();
    }

    /** @return list<array{key: string, route: string, label: string, icon: string, bar: bool, badge: int}> */
    public static function items(?User $user, ?WalletGroup $group): array
    {
        $out = [];
        foreach (self::CATALOG as $key => $row) {
            if (! Route::has($row['route'])) {
                continue;
            }
            if ($row['feature'] && ! ComingSoon::isLive($row['feature'])) {
                continue;
            }
            if ($row['admin'] && (! $user || ! $group || ! WorkspaceAdminAccess::can($group, $user))) {
                continue;
            }
            $out[] = ['key' => $key, 'route' => $row['route'], 'label' => $row['label'], 'icon' => $row['icon'], 'bar' => $row['bar'], 'badge' => $key === 'inbox' ? self::unread($group) : 0];
        }

        return $out;
    }

    /** The bar: at most 4, the raised centre "new message" button is added by the view. */
    public static function bar(array $items): array
    {
        return array_slice(array_values(array_filter($items, fn ($i) => $i['bar'])), 0, 4);
    }

    public static function active(array $item): bool
    {
        return request()->routeIs($item['route']) || ($item['key'] === 'overview' && request()->routeIs('business.workspace'));
    }

    /** Unread conversations across the workspace's channels and its own phone lines. */
    public static function unread(?WalletGroup $group): int
    {
        if (! $group) {
            return 0;
        }
        $lines = VirtualNumber::query()->where('wallet_group_id', $group->id)->pluck('id');

        return (int) MessageThread::query()
            ->where(fn ($q) => $q->where('wallet_group_id', $group->id)->orWhereIn('virtual_number_id', $lines->all() ?: [0]))
            ->sum('unread_count');
    }
}
