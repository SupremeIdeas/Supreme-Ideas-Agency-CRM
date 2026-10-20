<?php

namespace App\Services\JourneyEngine;

use App\Models\JourneyEngine\JourneyCard;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * The decision engine (J1): signals -> candidate rules -> suppression -> scoring -> ONE primary card plus at most two secondary ones, each saved with the evidence that
 * justified it. Deterministic and AI-free. Protective alerts (critical/important) always outrank suggestions and are exempt from the suggestion caps; a suggestion needs
 * a matching intent, evidence, and room under the daily/weekly caps and the holdout.
 */
class CardEngine
{
    public function __construct(private readonly Signals $signals = new Signals, private readonly RuleCatalog $rules = new RuleCatalog, private readonly IntentService $intents = new IntentService) {}

    /** Stable per-user bucket: a fixed share of users never receive suggestions, so their effect can be measured later (J6). */
    public function inHoldout(User $user): bool
    {
        $pct = (float) Setting::getValue('journey.holdout_pct', 10);

        return $pct > 0 && (crc32('journey-holdout|'.$user->id) % 100) < $pct;
    }

    /**
     * @return array{primary: ?JourneyCard, secondary: list<JourneyCard>, attention: int}
     */
    public function evaluate(User $user, bool $record = true, bool $impression = true): array
    {
        $userIntents = $this->intents->for($user);
        $holdout = $this->inHoldout($user);
        $candidates = [];
        $firing = [];

        foreach ($this->rules->all() as $key => $rule) {
            $matches = in_array('*', $rule['intents'], true) || array_intersect($rule['intents'], $userIntents);
            if (! $matches) {
                continue;
            }
            foreach ((array) $rule['owns'] as $owned) {
                if ($this->signals->owns($user, $owned)) {
                    continue 2;
                }
            }
            $hit = $this->signals->{$rule['signal']}($user);
            if ($hit === null) {
                continue;
            }
            $priority = ! empty($rule['priority_overridden']) ? $rule['priority'] : ($hit['priority'] ?? $rule['priority']);
            $subject = (string) $hit['subject'];
            $firing[$key.'|'.$subject] = true;

            if ($priority === 'opportunity' && $holdout) {
                continue;
            }
            $card = JourneyCard::where(['user_id' => $user->id, 'rule_key' => $key, 'subject_key' => $subject])->first();
            if ($card && ! $this->allowed($card, $priority)) {
                continue;
            }
            if ((! $card || $card->state === JourneyCard::EXPIRED) && $priority === 'opportunity' && ! $this->roomForOpportunity($user)) {
                continue;
            }
            $candidates[] = ['key' => $key, 'rule' => $rule, 'priority' => $priority, 'subject' => $subject, 'evidence' => $hit['evidence'], 'card' => $card, 'score' => $this->score($priority, $card)];
        }

        // A card whose signal no longer fires has been resolved (data topped up, number renewed): retire it so it never reappears stale.
        if ($record) {
            JourneyCard::where('user_id', $user->id)->whereIn('state', [JourneyCard::SHOWN, JourneyCard::CLICKED])->get()
                ->each(function (JourneyCard $c) use ($firing) {
                    if (! isset($firing[$c->rule_key.'|'.$c->subject_key])) {
                        $c->forceFill(['state' => JourneyCard::EXPIRED])->save();
                    }
                });
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        $picked = [];
        $opportunities = 0;
        foreach ($candidates as $c) {
            if (count($picked) >= 3) {
                break;
            }
            if ($c['priority'] === 'opportunity') {
                if ($opportunities >= 1) {
                    continue;                       // at most one suggestion at a time
                }
                $opportunities++;
            }
            $picked[] = $c;
        }

        $cards = [];
        foreach ($picked as $c) {
            $cards[] = $record ? $this->persist($user, $c, $impression) : $this->preview($user, $c);
        }

        $attention = count(array_filter($picked, fn ($c) => in_array($c['priority'], ['critical', 'important'], true)));

        return ['primary' => $cards[0] ?? null, 'secondary' => array_slice($cards, 1), 'attention' => $attention];
    }

    private function allowed(JourneyCard $card, string $priority): bool
    {
        if ($card->snooze_until && $card->snooze_until->isFuture()) {
            return false;
        }
        if ($card->state === JourneyCard::DISMISSED) {
            if ($priority === 'critical') {
                return $card->updated_at->lt(now()->subDay());                       // a critical alert comes back tomorrow, never permanently muted
            }
            $days = $card->dismiss_count >= 2 ? (int) (float) Setting::getValue('journey.dismiss_suppress_days', 60) : (int) (float) Setting::getValue('journey.cooldown_days', 7);

            return $card->updated_at->lt(now()->subDays($days));
        }
        if (in_array($card->state, [JourneyCard::CLICKED, JourneyCard::CONVERTED], true) && $priority === 'opportunity') {
            return $card->updated_at->lt(now()->subDays((int) (float) Setting::getValue('journey.cooldown_days', 7)));
        }

        return true;
    }

    private function roomForOpportunity(User $user): bool
    {
        $base = JourneyCard::where('user_id', $user->id)->where('priority', 'opportunity')->whereNotNull('first_shown_at');
        $day = (clone $base)->where('first_shown_at', '>=', now()->startOfDay())->count();
        $week = (clone $base)->where('first_shown_at', '>=', now()->subDays(7))->count();

        return $day < (int) (float) Setting::getValue('journey.opportunity_per_day', 1) && $week < (int) (float) Setting::getValue('journey.opportunity_per_week', 3);
    }

    private function score(string $priority, ?JourneyCard $card): float
    {
        $base = (float) (config('journey_engine.priorities')[$priority] ?? 0);
        $fatigue = $card ? min(50, $card->shown_count * 2) : 0;

        return $base - $fatigue;
    }

    private function persist(User $user, array $c, bool $impression = true): JourneyCard
    {
        $card = $c['card'] ?? new JourneyCard(['user_id' => $user->id, 'rule_key' => $c['key'], 'subject_key' => $c['subject']]);
        $revive = $card->exists && in_array($card->state, [JourneyCard::EXPIRED, JourneyCard::DISMISSED, JourneyCard::CLICKED, JourneyCard::CONVERTED, JourneyCard::SNOOZED], true);
        $card->fill([
            'priority' => $c['priority'], 'evidence' => $c['evidence'],
            'state' => $revive ? JourneyCard::SHOWN : ($card->state ?: JourneyCard::SHOWN),
            // An evaluation for off-app delivery is not an impression: the user has not seen the card, so it must not count toward fatigue or the caps.
            'shown_count' => ($card->shown_count ?? 0) + ($impression ? 1 : 0), 'last_shown_at' => $impression ? now() : $card->last_shown_at,
            'first_shown_at' => $impression ? ($card->first_shown_at ?? now()) : $card->first_shown_at, 'snooze_until' => $revive ? null : $card->snooze_until,
        ])->save();

        return $card;
    }

    private function preview(User $user, array $c): JourneyCard
    {
        return new JourneyCard(['user_id' => $user->id, 'rule_key' => $c['key'], 'subject_key' => $c['subject'], 'priority' => $c['priority'], 'evidence' => $c['evidence'], 'state' => JourneyCard::SHOWN]);
    }

    // ---- user actions ----------------------------------------------------------------------------------------------------------------

    public function dismiss(JourneyCard $card): void
    {
        $card->forceFill(['state' => JourneyCard::DISMISSED, 'dismiss_count' => $card->dismiss_count + 1, 'acted_at' => now()])->save();
    }

    public function snooze(JourneyCard $card, int $hours = 24): void
    {
        $card->forceFill(['state' => JourneyCard::SNOOZED, 'snooze_until' => now()->addHours($hours), 'acted_at' => now()])->save();
    }

    public function click(JourneyCard $card): void
    {
        $card->forceFill(['state' => JourneyCard::CLICKED, 'acted_at' => now()])->save();
    }

    /** What a card says, with the evidence filled in. @return array{title: string, body: string, cta: string, why: string, url: string, priority: string} */
    public function render(JourneyCard $card): array
    {
        $rule = $this->rules->all()[$card->rule_key] ?? null;
        $copy = $rule['copy'] ?? $card->rule_key;
        $ev = (array) $card->evidence;

        $over = (array) ($rule['copy_override'] ?? []);
        // Studio copy is plain text with :placeholders for the evidence; it is escaped by the views like any other string.
        $say = fn (string $field) => isset($over[$field]) ? strtr($over[$field], collect($ev)->mapWithKeys(fn ($v, $k) => [':'.$k => (string) $v])->all()) : __("journey_engine.cards.{$copy}.{$field}", $ev);

        return [
            'title' => $say('title'),
            'body' => $say('body'),
            'cta' => $say('cta'),
            'why' => $say('why'),
            'url' => $rule && Route::has($rule['route']) ? route($rule['route']) : route('dashboard'),
            'priority' => $card->priority,
            'art' => self::art((string) $copy),
        ];
    }

    /** The drawn illustration that mirrors a card's message (public/images/illustrations). Null for a card with no drawing: the view then shows none, never an empty slot. */
    public static function art(string $copy): ?string
    {
        $file = match ($copy) {
            'brand_get_listed', 'brand_listing_paused', 'brand_setup_incomplete' => 'brand-directory',
            'esim_expiring', 'low_data', 'install_guide' => 'esim',
            'number_renewal', 'missed_calls' => 'numbers',
            'referral_after_success' => 'giftcard',
            default => null,
        };

        return $file ? asset('images/illustrations/'.$file.'.svg') : null;
    }
}
