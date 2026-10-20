<?php

namespace App\Services\N2N\Brands;

use App\Jobs\N2N\FetchThumbnailJob;
use App\Models\BrandPartner;
use App\Models\N2N\BrandAlbum;
use App\Models\N2N\BrandAlbumLink;
use App\Models\N2N\BrandEmbed;
use App\Models\N2N\BrandMusicLink;
use App\Models\N2N\BrandSocialLink;
use App\Support\Auditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Everything a brand owner (or admin) can add to a profile beyond the core listing (N2N Batch 8, MASTER-ONLY). Each method validates on the
 * SERVER and enforces limits inside a transaction with the brand row locked, so two parallel requests cannot both slip past a cap.
 * Callers are responsible for proving the actor owns $brand (the Livewire component loads it by owner_user_id, never by a posted id).
 */
class BrandExtras
{
    /** Owner asks for the music profile. Nothing is unlocked until an admin confirms. */
    public function requestMusic(BrandPartner $brand): void
    {
        $brand->forceFill(['profile_type' => 'music', 'music_requested_at' => $brand->music_requested_at ?? now()])->save();
    }

    public function confirmMusic(BrandPartner $brand, int $adminId): void
    {
        $brand->forceFill(['profile_type' => 'music', 'music_verified_at' => now(), 'music_verified_by' => $adminId])->save();
        Auditor::log('n2n.brand.music_confirmed', BrandPartner::class, $brand->id);
    }

    public function revokeMusic(BrandPartner $brand, int $adminId): void
    {
        $brand->forceFill(['profile_type' => 'general', 'music_verified_at' => null, 'music_verified_by' => null])->save();
        Auditor::log('n2n.brand.music_revoked', BrandPartner::class, $brand->id, ['by' => $adminId]);
    }

    /** @throws BrandLimitException */
    public function addMusicLink(BrandPartner $brand, string $platform, string $url): BrandMusicLink
    {
        $clean = LinkValidator::validate($platform, $url);

        return DB::transaction(function () use ($brand, $platform, $clean) {
            $b = BrandPartner::query()->lockForUpdate()->findOrFail($brand->id);
            BrandLimits::assertRoom($b, 'music_links', BrandMusicLink::query()->where('brand_partner_id', $b->id)->count(), 'music links');

            return BrandMusicLink::query()->create(['brand_partner_id' => $b->id, 'platform' => $platform, 'url' => $clean, 'sort_order' => (int) BrandMusicLink::query()->where('brand_partner_id', $b->id)->max('sort_order') + 1]);
        });
    }

    public function removeMusicLink(BrandPartner $brand, int $id): void
    {
        BrandMusicLink::query()->where('brand_partner_id', $brand->id)->whereKey($id)->delete();
    }

    /** @throws BrandLimitException */
    public function addEmbed(BrandPartner $brand, string $pasted): BrandEmbed
    {
        $p = EmbedParser::parse($pasted);

        return DB::transaction(function () use ($brand, $p) {
            $b = BrandPartner::query()->lockForUpdate()->findOrFail($brand->id);
            BrandLimits::assertRoom($b, 'embeds', BrandEmbed::query()->where('brand_partner_id', $b->id)->count(), 'embedded players');
            if (BrandEmbed::query()->where('brand_partner_id', $b->id)->where('provider', $p['provider'])->where('provider_id', $p['id'])->exists()) {
                throw new BrandLimitException('duplicate', 'That player is already on your profile.');
            }
            $e = BrandEmbed::query()->create(['brand_partner_id' => $b->id, 'provider' => $p['provider'], 'provider_id' => $p['id'], 'kind' => $p['kind'], 'sort_order' => (int) BrandEmbed::query()->where('brand_partner_id', $b->id)->max('sort_order') + 1]);
            if ($p['kind'] === 'video') {
                FetchThumbnailJob::dispatch($p['provider'], $p['id'])->afterCommit();
            }

            return $e;
        });
    }

    public function removeEmbed(BrandPartner $brand, int $id): void
    {
        BrandEmbed::query()->where('brand_partner_id', $brand->id)->whereKey($id)->delete();
    }

    /** @throws BrandLimitException */
    public function addSocialLink(BrandPartner $brand, string $platform, string $url): BrandSocialLink
    {
        $clean = LinkValidator::validate($platform, $url);

        return DB::transaction(function () use ($brand, $platform, $clean) {
            $b = BrandPartner::query()->lockForUpdate()->findOrFail($brand->id);
            if (BrandSocialLink::query()->where('brand_partner_id', $b->id)->count() >= 12) {
                throw new BrandLimitException('limit', 'You can show at most 12 social links.');
            }

            return BrandSocialLink::query()->create(['brand_partner_id' => $b->id, 'platform' => $platform, 'url' => $clean, 'sort_order' => (int) BrandSocialLink::query()->where('brand_partner_id', $b->id)->max('sort_order') + 1]);
        });
    }

    public function removeSocialLink(BrandPartner $brand, int $id): void
    {
        BrandSocialLink::query()->where('brand_partner_id', $brand->id)->whereKey($id)->delete();
    }

    /** @throws BrandLimitException */
    public function createAlbum(BrandPartner $brand, string $title, ?string $description = null): BrandAlbum
    {
        $title = trim(strip_tags($title));
        if ($title === '' || mb_strlen($title) > 120) {
            throw new BrandLimitException('title', 'Give the album a title (up to 120 characters).');
        }
        if (BrandLimits::tier($brand) !== 'music') {
            throw new BrandLimitException('limit', 'Albums are part of the music profile.');
        }

        return DB::transaction(function () use ($brand, $title, $description) {
            $b = BrandPartner::query()->lockForUpdate()->findOrFail($brand->id);
            BrandLimits::assertRoom($b, 'albums', BrandAlbum::query()->where('brand_partner_id', $b->id)->count(), 'albums');
            $base = Str::slug($title) ?: 'album';
            $slug = $base;
            for ($i = 2; BrandAlbum::query()->where('brand_partner_id', $b->id)->where('slug', $slug)->exists(); $i++) {
                $slug = $base.'-'.$i;
            }

            return BrandAlbum::query()->create(['brand_partner_id' => $b->id, 'title' => $title, 'slug' => $slug, 'description' => $description !== null ? Str::limit(trim(strip_tags($description)), 600, '') : null]);
        });
    }

    /** @throws BrandLimitException */
    public function addAlbumLink(BrandAlbum $album, string $platform, string $url): BrandAlbumLink
    {
        $clean = LinkValidator::validate($platform, $url);
        if (BrandAlbumLink::query()->where('album_id', $album->id)->count() >= 25) {
            throw new BrandLimitException('limit', 'An album can list at most 25 links.');
        }

        return BrandAlbumLink::query()->create(['album_id' => $album->id, 'platform' => $platform, 'url' => $clean, 'sort_order' => (int) BrandAlbumLink::query()->where('album_id', $album->id)->max('sort_order') + 1]);
    }

    public function deleteAlbum(BrandPartner $brand, int $albumId): void
    {
        BrandAlbum::query()->where('brand_partner_id', $brand->id)->whereKey($albumId)->delete();   // links cascade
    }

    /**
     * Create or update an album in the artist studio: title, description, release date, cover, distributor and its smart link, and the streaming links (replaced as a set).
     * Everything is validated on the server inside a transaction with the brand locked (so two requests cannot both pass the album cap). `publish` makes it live and
     * needs a cover plus at least one way to listen; a draft needs only a title.
     *
     * @param  array{title?: string, description?: ?string, release_date?: ?string, distributor?: ?string, smart_url?: ?string, links?: list<array{platform: string, url: string}>, publish?: bool}  $d
     *
     * @throws BrandLimitException
     */
    public function saveAlbum(BrandPartner $brand, ?int $albumId, array $d, ?\Illuminate\Http\UploadedFile $cover = null): BrandAlbum
    {
        if (BrandLimits::tier($brand) !== 'music') {
            throw new BrandLimitException('limit', 'Albums are part of the music profile.');
        }
        $title = trim(strip_tags((string) ($d['title'] ?? '')));
        if ($title === '' || mb_strlen($title) > 120) {
            throw new BrandLimitException('title', 'Give the album a title (up to 120 characters).');
        }
        $desc = isset($d['description']) && trim((string) $d['description']) !== '' ? Str::limit(trim(strip_tags((string) $d['description'])), 600, '') : null;
        $distributor = ($d['distributor'] ?? '') !== '' ? (string) $d['distributor'] : null;
        if ($distributor !== null && ! isset(config('n2n_music_distributors.distributors')[$distributor])) {
            throw new BrandLimitException('distributor', 'Choose a distributor from the list.');
        }
        $smart = ($d['smart_url'] ?? '') !== '' ? LinkValidator::validateSmartLink($distributor, (string) $d['smart_url']) : null;
        $release = null;
        if (($d['release_date'] ?? '') !== '') {
            try {
                $release = \Illuminate\Support\Carbon::parse((string) $d['release_date'])->startOfDay();
            } catch (\Throwable) {
                throw new BrandLimitException('date', 'Enter the release date as a real date.');
            }
            if ($release->gt(now()->addYears(2)) || $release->lt(\Illuminate\Support\Carbon::create(1950))) {
                throw new BrandLimitException('date', 'That release date does not look right.');
            }
        }
        $links = [];
        foreach ((array) ($d['links'] ?? []) as $row) {
            $platform = (string) ($row['platform'] ?? '');
            $url = trim((string) ($row['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $links[] = ['platform' => $platform, 'url' => LinkValidator::validate($platform, $url)];
        }
        if (count($links) > 25) {
            throw new BrandLimitException('limit', 'An album can list at most 25 links.');
        }
        $coverPath = null;
        if ($cover !== null) {
            $info = @getimagesize($cover->getRealPath());
            if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || $cover->getSize() > 5 * 1048576) {
                throw new BrandLimitException('cover', 'The cover must be a JPG, PNG or WebP image up to 5 MB.');
            }
            if (min($info[0], $info[1]) < 400) {
                throw new BrandLimitException('cover', 'The cover is too small. Use at least 400 x 400 pixels (3000 x 3000 is ideal).');
            }
            $coverPath = \App\Support\MediaStorage::storePublic($cover, 'brand-album-cover');
        }

        return DB::transaction(function () use ($brand, $albumId, $title, $desc, $distributor, $smart, $release, $links, $coverPath, $d) {
            $b = BrandPartner::query()->lockForUpdate()->findOrFail($brand->id);
            $album = $albumId !== null ? BrandAlbum::query()->where('brand_partner_id', $b->id)->findOrFail($albumId) : null;
            if ($album === null) {
                $cap = BrandLimits::albumCap($b);
                if (BrandAlbum::query()->where('brand_partner_id', $b->id)->count() >= $cap) {
                    throw new BrandLimitException('limit', "Your profile allows at most {$cap} albums. Remove one, or upgrade to the highest plan for unlimited albums.");
                }
                $base = Str::slug($title) ?: 'album';
                $slug = $base;
                for ($i = 2; BrandAlbum::query()->where('brand_partner_id', $b->id)->where('slug', $slug)->exists(); $i++) {
                    $slug = $base.'-'.$i;
                }
                $album = new BrandAlbum(['brand_partner_id' => $b->id, 'slug' => $slug, 'sort_order' => (int) BrandAlbum::query()->where('brand_partner_id', $b->id)->max('sort_order') + 1]);
            }
            $album->fill(['title' => $title, 'description' => $desc, 'distributor' => $distributor, 'smart_url' => $smart, 'release_date' => $release?->toDateString()]);
            if ($coverPath !== null) {
                $album->cover_path = $coverPath;
            }
            if (! empty($d['publish'])) {
                if (blank($album->cover_path)) {
                    throw new BrandLimitException('cover', 'Add a cover before you publish.');
                }
                if ($smart === null && $links === []) {
                    throw new BrandLimitException('links', 'Add your distributor smart link or at least one streaming link before you publish.');
                }
                $album->published_at ??= now();
            } else {
                $album->published_at = null;
            }
            $album->save();
            $album->links()->delete();
            foreach ($links as $i => $l) {
                BrandAlbumLink::query()->create(['album_id' => $album->id, 'platform' => $l['platform'], 'url' => $l['url'], 'sort_order' => $i]);
            }

            return $album->fresh('links');
        });
    }

    /** Move an album one place earlier or later in the carousel (the artist's own order). */
    public function moveAlbum(BrandPartner $brand, int $albumId, int $direction): void
    {
        DB::transaction(function () use ($brand, $albumId, $direction) {
            $all = BrandAlbum::query()->where('brand_partner_id', $brand->id)->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get()->values();
            $i = $all->search(fn ($a) => $a->id === $albumId);
            $j = $i === false ? false : $i + ($direction < 0 ? -1 : 1);
            if ($i === false || $j < 0 || $j >= $all->count()) {
                return;
            }
            $all = $all->all();
            [$all[$i], $all[$j]] = [$all[$j], $all[$i]];
            foreach ($all as $n => $a) {
                BrandAlbum::query()->whereKey($a->id)->update(['sort_order' => $n]);
            }
        });
    }

    /** Moderation (report -> hide): an admin hides or restores one item without deleting the owner's data. */
    public function setHidden(string $type, int $id, bool $hidden, int $adminId): bool
    {
        $model = ['music' => BrandMusicLink::class, 'embed' => BrandEmbed::class, 'social' => BrandSocialLink::class, 'album' => BrandAlbum::class][$type] ?? null;
        $row = $model ? $model::query()->find($id) : null;
        if ($row === null) {
            return false;
        }
        $row->forceFill(['hidden_at' => $hidden ? now() : null])->save();
        Auditor::log($hidden ? 'n2n.brand.item_hidden' : 'n2n.brand.item_restored', $model, $id, ['type' => $type, 'by' => $adminId]);

        return true;
    }
}
