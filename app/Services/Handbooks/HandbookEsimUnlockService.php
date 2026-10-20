<?php

namespace App\Services\Handbooks;

use App\Models\EsimOrder;
use App\Models\Handbook;
use App\Models\HandbookEsimPick;
use App\Models\User;
use App\Support\Handbooks\HandbookSettings;
use App\Support\Notifications\Notify;

/**
 * A Naara eSIM unlocks handbooks (MASTER-ONLY), under the admin's rules (HandbookSettings: switch, coverage types, minimum eSIM price, picks per regional eSIM, flagship-only)
 * and each book's own switch (`unlock_via_esim`, `extra_unlock_countries`). A single-country plan unlocks that country's flagship at once; a regional or global plan earns
 * a number of picks from the covered countries. Merchant-widget orders never unlock (no Naara account). Idempotent per order.
 */
class HandbookEsimUnlockService
{
    public function __construct(private readonly HandbookUnlockService $unlock)
    {
    }

    /** @return array{mode:string, handbook:?Handbook} mode = unlocked | pick | none */
    public function onOrder(EsimOrder $order): array
    {
        $none = ['mode' => 'none', 'handbook' => null];
        if (! HandbookSettings::get('esim_unlock_enabled') || $order->merchant_client_id !== null || ! $order->user_id) {
            return $none;
        }
        $user = User::query()->find($order->user_id);
        $plan = $order->plan;
        if (! $user || ! $plan) {
            return $none;
        }
        if ((float) $order->price_charged < (float) HandbookSettings::get('esim_min_price_usd')) {
            return $none;
        }
        $codes = array_values(array_unique(array_filter(array_map(fn ($c) => strtoupper(trim((string) $c)), (array) $plan->countries), fn ($c) => preg_match('/^[A-Z]{2}$/', $c) === 1)));
        if ($codes === []) {
            return $none;
        }
        $coverage = in_array($plan->coverage_type, ['local', 'regional', 'global'], true) ? $plan->coverage_type : (count($codes) === 1 ? 'local' : 'regional');
        if (! in_array($coverage, (array) HandbookSettings::get('esim_unlock_coverage'), true)) {
            return $none;
        }
        if ($coverage === 'local' && count($codes) === 1) {
            $b = $this->candidates($codes)->orderBy('sort_order')->orderBy('id')->first();
            if (! $b) {
                return $none;
            }
            $ent = $this->unlock->unlock($user, $b, 'esim_order', (string) $order->id);
            if ($ent && $ent->wasRecentlyCreated) {
                Notify::event($user, 'handbook.unlocked', ['title' => $b->title], 'hb-esim-'.$order->id);
            }

            return ['mode' => 'unlocked', 'handbook' => $b];
        }
        $allowed = max(1, (int) HandbookSettings::get('esim_regional_picks'));
        if (! $this->candidates($codes)->exists()) {
            return $none;
        }
        $pick = HandbookEsimPick::query()->firstOrCreate(['esim_order_id' => $order->id], ['user_id' => $user->id, 'country_codes' => $codes, 'picks_allowed' => $allowed]);
        if ($pick->wasRecentlyCreated) {
            Notify::event($user, 'handbook.pick_ready', [], 'hb-pick-'.$order->id);
        }

        return ['mode' => 'pick', 'handbook' => null];
    }

    /** Published titles an eSIM covering these countries may unlock: the book's own switch is on, and it is a flagship (or any title when the admin turned flagship-only off). */
    public function candidates(array $codes)
    {
        return Handbook::query()->published()->where('unlock_via_esim', true)
            ->when(HandbookSettings::get('esim_unlock_flagship_only'), fn ($q) => $q->where('series_slot', 'S1'))
            ->where(function ($q) use ($codes) {
                $q->whereIn('country_code', $codes);
                foreach ($codes as $c) {
                    $q->orWhereJsonContains('extra_unlock_countries', $c);
                }
            });
    }

    /** The person chooses one of their free handbooks for a pending pick. @throws HandbookException */
    public function choose(User $user, int $pickId, int $handbookId): Handbook
    {
        $pick = HandbookEsimPick::query()->where('user_id', $user->id)->whereNull('picked_at')->whereKey($pickId)->first() ?? throw new HandbookException('That choice is no longer available.');
        $b = $this->candidates((array) $pick->country_codes)->whereKey($handbookId)->first() ?? throw new HandbookException('Pick one of the handbooks offered for your eSIM.');
        $chosen = (array) $pick->chosen_ids;
        if (in_array($b->id, $chosen, true)) {
            throw new HandbookException('You already picked that one.');
        }
        $this->unlock->unlock($user, $b, 'esim_order', (string) $pick->esim_order_id);
        $chosen[] = $b->id;
        $pick->forceFill(['chosen_ids' => $chosen, 'handbook_id' => $pick->handbook_id ?? $b->id, 'picked_at' => count($chosen) >= (int) $pick->picks_allowed ? now() : null])->save();
        Notify::event($user, 'handbook.unlocked', ['title' => $b->title], 'hb-pick-done-'.$pick->id.'-'.$b->id);

        return $b;
    }
}
