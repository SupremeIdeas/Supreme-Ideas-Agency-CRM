<?php

namespace App\Support\N2N;

use App\Models\User;
use App\Services\N2N\Messaging\Inbox;
use Illuminate\Support\Facades\Route;

/**
 * Navigation of the N2N shell (MASTER-ONLY): ONE catalogue feeds the mobile bottom bar, the desktop rail and the header, so a destination
 * added by a later batch (Calls in B10, ...) appears everywhere by registering a route and one row here. An item whose route does not exist
 * (feature not built, or switched off) is simply absent: nothing is ever shown that leads nowhere.
 */
class N2NNav
{
    /** key => [route, label, icon, priority]. The bar shows the first 4 available by priority; the rail shows all of them. */
    public const CATALOG = [
        'chats' => ['route' => 'n2n.chat', 'label' => 'Chats', 'icon' => 'message-circle', 'bar' => true],
        'updates' => ['route' => 'n2n.updates', 'label' => 'Updates', 'icon' => 'story-ring', 'bar' => true],
        'calls' => ['route' => 'n2n.calls', 'label' => 'Calls', 'icon' => 'phone', 'bar' => true],
        'people' => ['route' => 'n2n.people', 'label' => 'People', 'icon' => 'users', 'bar' => true],
        'planner' => ['route' => 'n2n.planner', 'label' => 'Planner', 'icon' => 'calendar', 'bar' => false],
        'credits' => ['route' => 'n2n.credits', 'label' => 'Credits', 'icon' => 'wallet', 'bar' => false],
        'me' => ['route' => 'n2n.profile.settings', 'label' => 'My profile', 'icon' => 'user', 'bar' => false],
        'settings' => ['route' => 'n2n.identity', 'label' => 'Naara ID', 'icon' => 'id-card', 'bar' => false],
    ];

    /** @return list<array{key: string, route: string, label: string, icon: string, bar: bool, badge: int}> */
    public static function items(?User $user): array
    {
        $unread = $user ? app(Inbox::class)->totalUnread($user) : 0;
        $out = [];
        foreach (self::CATALOG as $key => $row) {
            if (! Route::has($row['route']) || ($key === 'calls' && ! \App\Services\N2N\Calls\CallsAvailability::enabled())) {
                continue;      // a destination that does not exist, or Calls while the feature is off, is simply absent
            }
            $out[] = $row + ['key' => $key, 'badge' => $key === 'chats' ? $unread : 0];
        }

        return $out;
    }

    /** The bar: at most 4, centre button (new chat) is added by the view. */
    public static function bar(array $items): array
    {
        return array_slice(array_values(array_filter($items, fn ($i) => $i['bar'])), 0, 4);
    }

    public static function active(array $item): bool
    {
        return request()->routeIs($item['route']);
    }
}
