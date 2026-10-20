<?php

namespace App\Services\JourneyEngine;

use App\Models\JourneyEngine\JourneyCard;
use App\Models\JourneyEngine\JourneyChannelConsent;
use App\Models\JourneyEngine\JourneyDelivery;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\JourneyAlertNotification;

/**
 * J2 delivery: pushes or emails ONE protective alert to a user, only when every guard passes. In order:
 *   1. master switch `journey.delivery_enabled` (ships OFF: blueprint section 11 wants legal review of consent per market before any off-app delivery),
 *   2. the user's per-channel consent for the guardian category (no grant = nothing is sent),
 *   3. quiet hours in the user's own timezone,
 *   4. the daily cap per user, and no repeat of the same alert on the same channel inside `journey.alert_repeat_hours`.
 * Only critical and important alerts are eligible; suggestions never leave the app. Bundled: at most one message per run per channel, the most urgent alert wins.
 * Evaluating cards here does NOT count as an impression (the user has not seen them), so delivery never inflates a card's fatigue score.
 */
class Delivery
{
    public function __construct(private readonly CardEngine $engine = new CardEngine, private readonly ChannelConsent $consent = new ChannelConsent) {}

    public function enabled(): bool
    {
        return (bool) Setting::getValue('journey.enabled', true) && (bool) Setting::getValue('journey.delivery_enabled', false);
    }

    public function quietNow(User $user): bool
    {
        $start = (int) (float) Setting::getValue('journey.quiet_hours_start', 21);
        $end = (int) (float) Setting::getValue('journey.quiet_hours_end', 8);
        $tz = $user->timezone ?: config('app.timezone');
        try {
            $hour = (int) now($tz)->format('G');
        } catch (\Throwable) {
            $hour = (int) now()->format('G');
        }

        return $start === $end ? false : ($start > $end ? ($hour >= $start || $hour < $end) : ($hour >= $start && $hour < $end));
    }

    /**
     * @return array{users: int, sent: int, skipped: array<string, int>}
     */
    public function run(bool $dry = false): array
    {
        $out = ['users' => 0, 'sent' => 0, 'skipped' => []];
        if (! $this->enabled()) {
            $out['skipped']['delivery_off'] = 1;

            return $out;
        }

        $ids = JourneyChannelConsent::query()->whereIn('channel', ChannelConsent::CHANNELS)->where('category', ChannelConsent::CATEGORY)
            ->whereNotNull('granted_at')->whereNull('revoked_at')->distinct()->pluck('user_id');

        User::query()->whereIn('id', $ids)->where('is_active', true)->chunkById(200, function ($users) use (&$out, $dry) {
            foreach ($users as $user) {
                $out['users']++;
                $r = $this->deliverTo($user, $dry);
                $out['sent'] += $r['sent'];
                if ($r['skipped']) {
                    $out['skipped'][$r['skipped']] = ($out['skipped'][$r['skipped']] ?? 0) + 1;
                }
            }
        });

        return $out;
    }

    /** @return array{sent: int, skipped: ?string} */
    public function deliverTo(User $user, bool $dry = false): array
    {
        if ($this->quietNow($user)) {
            return ['sent' => 0, 'skipped' => 'quiet_hours'];
        }
        $max = max(1, (int) (float) Setting::getValue('journey.delivery_max_per_day', 2));
        if (JourneyDelivery::where('user_id', $user->id)->where('sent_at', '>=', now()->subDay())->distinct('journey_card_id')->count('journey_card_id') >= $max) {
            return ['sent' => 0, 'skipped' => 'daily_cap'];
        }

        $channels = array_values(array_filter(ChannelConsent::CHANNELS, fn ($c) => $this->consent->granted($user, $c)));
        if ($channels === []) {
            return ['sent' => 0, 'skipped' => 'no_consent'];
        }

        $result = $this->engine->evaluate($user, true, false);
        $cards = array_values(array_filter([$result['primary'], ...$result['secondary']], fn ($c) => $c && in_array($c->priority, ['critical', 'important'], true)));
        if ($cards === []) {
            return ['sent' => 0, 'skipped' => 'nothing_to_say'];
        }
        usort($cards, fn ($a, $b) => (config('journey_engine.priorities')[$b->priority] ?? 0) <=> (config('journey_engine.priorities')[$a->priority] ?? 0));

        $repeat = max(1, (int) (float) Setting::getValue('journey.alert_repeat_hours', 24));
        foreach ($cards as $card) {
            if (! $card->exists) {
                continue;
            }
            $fresh = array_values(array_filter($channels, fn ($ch) => ! JourneyDelivery::where(['user_id' => $user->id, 'journey_card_id' => $card->id, 'channel' => $ch])
                ->where('sent_at', '>=', now()->subHours($repeat))->exists()));
            if ($fresh === []) {
                continue;
            }
            if (! $dry) {
                $user->notify(new JourneyAlertNotification($this->engine->render($card), $fresh));
                foreach ($fresh as $ch) {
                    JourneyDelivery::create(['user_id' => $user->id, 'journey_card_id' => $card->id, 'channel' => $ch, 'rule_key' => $card->rule_key, 'sent_at' => now()]);
                }
            }

            return ['sent' => count($fresh), 'skipped' => null];
        }

        return ['sent' => 0, 'skipped' => 'already_sent'];
    }
}
