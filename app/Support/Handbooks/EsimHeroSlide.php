<?php

namespace App\Support\Handbooks;

use App\Models\Handbook;
use Illuminate\Support\Facades\Cache;

/**
 * MASTER-ONLY. The Travel Handbooks slide of the eSIM hero carousel: its own headline, description and a link to the store, shown only while
 * the handbook store and its eSIM-banner switch (Admin > Handbooks > Store and unlock settings) are on and at least one handbook is published.
 */
final class EsimHeroSlide
{
    /** @return array{covers:\Illuminate\Support\Collection,title:string,description:string,eyebrow:string,cta:string,href:string}|null */
    public static function data(): ?array
    {
        try {
            if (! HandbookSettings::get('store_enabled') || ! HandbookSettings::get('esim_banner_enabled')) {
                return null;
            }
            $covers = Cache::remember('handbooks.esim_banner', 300, fn () => Handbook::query()->published()->orderByDesc('is_featured')->orderBy('sort_order')->limit(3)->get());
            if ($covers->isEmpty()) {
                return null;
            }
            $free = (bool) HandbookSettings::get('esim_unlock_enabled');

            return [
                'covers' => $covers,
                'eyebrow' => 'Travel Handbooks',
                'title' => $free ? 'Your eSIM comes with a free travel handbook' : 'Know before you go: travel handbooks for every destination',
                'description' => 'Visas, housing, money and local rules. Read it offline on your phone.',
                'cta' => 'Explore handbooks',
                'href' => route('handbooks.store'),
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
