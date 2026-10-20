<?php

namespace App\Services\JourneyEngine;

use App\Models\JourneyEngine\JourneyChannelConsent;
use App\Models\User;

/**
 * Per-channel consent for Journey alerts (J2). A channel with no active grant is OFF, so nothing is pushed or emailed without an explicit yes; in-app needs none.
 * Only the protective ("guardian") category is deliverable off-app today: suggestions never leave the app.
 */
class ChannelConsent
{
    public const CHANNELS = ['push', 'email'];

    public const CATEGORY = 'guardian';

    public function granted(User $user, string $channel, string $category = self::CATEGORY): bool
    {
        if (! in_array($channel, self::CHANNELS, true) || $category !== self::CATEGORY) {
            return false;
        }
        $row = JourneyChannelConsent::where(['user_id' => $user->id, 'channel' => $channel, 'category' => $category])->first();

        return $row !== null && $row->active();
    }

    public function grant(User $user, string $channel, string $source = 'settings'): void
    {
        if (! in_array($channel, self::CHANNELS, true)) {
            return;
        }
        JourneyChannelConsent::updateOrCreate(
            ['user_id' => $user->id, 'channel' => $channel, 'category' => self::CATEGORY],
            ['granted_at' => now(), 'revoked_at' => null, 'source' => $source],
        );
    }

    /** Takes effect immediately: the very next delivery run sees it. */
    public function revoke(User $user, string $channel): void
    {
        JourneyChannelConsent::where(['user_id' => $user->id, 'channel' => $channel, 'category' => self::CATEGORY])->update(['revoked_at' => now()]);
    }
}
