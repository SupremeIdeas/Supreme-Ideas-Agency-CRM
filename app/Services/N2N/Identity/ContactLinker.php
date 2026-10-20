<?php

namespace App\Services\N2N\Identity;

use App\Models\Contact;
use App\Models\N2N\UserPhone;

/**
 * Links address-book contacts to registered users (N2N, MASTER-ONLY). A contact is "known" when its number belongs to a VERIFIED,
 * DISCOVERABLE phone of someone who has not hidden themselves. Re-run whenever a number is verified, removed or its discovery changes.
 */
class ContactLinker
{
    /** Re-evaluate every contact of one owner (after an import, or a fresh e164 backfill). */
    public function linkOwner(int $ownerId): int
    {
        $n = 0;
        Contact::query()->where('user_id', $ownerId)->orderBy('id')->chunkById(500, function ($contacts) use (&$n) {
            $targets = $this->targets($contacts->pluck('e164')->filter()->unique()->all());
            foreach ($contacts as $c) {
                $target = $c->e164 !== null ? ($targets[$c->e164] ?? null) : null;
                if ($target === $c->user_id) {
                    $target = null;   // never link someone to themselves
                }
                $n += $this->apply($c, $target);
            }
        });

        return $n;
    }

    /** Re-evaluate every contact, across all owners, that holds one number (a number was verified, removed or hidden). */
    public function relinkNumber(string $e164): int
    {
        $n = 0;
        $target = $this->targets([$e164])[$e164] ?? null;
        Contact::query()->where('e164', $e164)->orderBy('id')->chunkById(500, function ($contacts) use (&$n, $target) {
            foreach ($contacts as $c) {
                $n += $this->apply($c, $target === $c->user_id ? null : $target);
            }
        });
        // Contacts that still point at a user who no longer owns any matching number.
        Contact::query()->whereNotNull('linked_user_id')->where('e164', $e164)->whereNot('linked_user_id', $target ?? 0)->update(['linked_user_id' => null, 'linked_at' => null]);

        return $n;
    }

    /** @param list<string> $numbers @return array<string,int> e164 => user id of its verified, discoverable, visible owner */
    private function targets(array $numbers): array
    {
        if ($numbers === []) {
            return [];
        }

        return UserPhone::query()->whereIn('e164', $numbers)->where('discoverable', true)
            ->whereHas('user', fn ($q) => $q->where('is_active', true)->where('n2n_hide_me', false)->whereNull('anonymized_at'))
            ->pluck('user_id', 'e164')->map(fn ($v) => (int) $v)->all();
    }

    private function apply(Contact $c, ?int $target): int
    {
        if ((int) $c->linked_user_id === (int) $target) {
            return 0;
        }
        $c->forceFill(['linked_user_id' => $target, 'linked_at' => $target ? now() : null])->saveQuietly();

        return 1;
    }
}
