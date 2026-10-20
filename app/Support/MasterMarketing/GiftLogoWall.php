<?php

namespace App\Support\MasterMarketing;

use App\Models\GiftCardBrandLogo;
use App\Services\GiftCards\GiftCardLogoWizard;
use App\Support\GiftPopularity;
use Illuminate\Support\Facades\Cache;

/**
 * MASTER-ONLY. The Naara Gift hero's live wall: the real logos of the most bought gift-card brands, in columns that drift up and down in
 * turn (4 columns on desktop, 3 on a phone). Built only from stored, approved, real logos (never a monogram) of brands that are actually for
 * sale, so the hero can never showcase something the store does not carry.
 */
final class GiftLogoWall
{
    public const MIN_TILES = 12;

    /** @return list<array{name:string,logo:string}> */
    public static function tiles(int $limit = 32): array
    {
        return Cache::remember('gift.logo_wall.'.$limit, 1800, function () use ($limit) {
            $keys = GiftPopularity::liveKeys($limit + 8);
            if ($keys === []) {
                return [];
            }
            $wizard = app(GiftCardLogoWizard::class);
            $rows = GiftCardBrandLogo::query()->whereIn('brand_key', $keys)->where('status', GiftCardBrandLogo::APPROVED)->whereNotNull('path')
                ->where('source', '!=', 'monogram')->get()->keyBy('brand_key');
            $out = [];
            foreach ($keys as $k) {
                if (! isset($rows[$k])) {
                    continue;
                }
                $vector = str_ends_with((string) $rows[$k]->path, '.svg');
                if ($vector && $rows[$k]->source !== 'simpleicons') {
                    continue;   // a generated vector is a monogram placeholder, never showcased
                }
                $out[] = ['name' => (string) $rows[$k]->brand_name, 'logo' => $wizard->url($rows[$k])];
            }

            return array_slice($out, 0, $limit);
        });
    }

    public static function available(): bool
    {
        return count(self::tiles()) >= self::MIN_TILES;
    }

    /**
     * Split the tiles round-robin into $cols columns (so every column mixes big and small brands).
     *
     * @return list<list<array{name:string,logo:string}>>
     */
    public static function columns(int $cols = 4): array
    {
        $out = array_fill(0, $cols, []);
        foreach (self::tiles() as $i => $t) {
            $out[$i % $cols][] = $t;
        }

        return $out;
    }
}
