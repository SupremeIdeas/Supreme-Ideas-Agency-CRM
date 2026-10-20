<?php

namespace App\Services\N2N\Brands;

use App\Models\BrandPartner;
use App\Models\BrandPartnerVideo;
use App\Models\N2N\BrandAlbum;
use App\Models\N2N\BrandEmbed;
use App\Models\N2N\BrandMusicLink;
use App\Models\N2N\BrandSocialLink;
use App\Models\N2N\Thumbnail;
use App\Services\N2N\Media\NaaraPro;
use App\Support\N2N\N2NSettings;

/**
 * What the public brand profile shows beyond the core listing (N2N Batch 8, MASTER-ONLY). Hidden (moderated) items never appear. Videos and
 * embeds carry a cached-thumbnail URL only: the player iframe is built client-side on tap from provider + id, so the page itself makes no
 * third-party request. Message / Call appear only for a Naara Pro owner and only if chat is on.
 */
class BrandPublicExtras
{
    /** @return array<string, mixed>|null null when N2N is off (the page then looks exactly as before) */
    public static function for(BrandPartner $brand): ?array
    {
        if (! N2NSettings::enabled()) {
            return null;
        }
        $videos = BrandPartnerVideo::query()->where('brand_partner_id', $brand->id)->ordered()->get()->map(function ($v) {
            $p = $v->embedUrl() ? ['provider' => $v->platform, 'id' => EmbedParser::fromUrl($v->video_url)['id'] ?? null] : null;

            return $p && $p['id'] ? self::card($p['provider'], $p['id'], 'video') : null;
        })->filter()->values();
        $embeds = BrandEmbed::query()->where('brand_partner_id', $brand->id)->whereNull('hidden_at')->orderBy('sort_order')->get()
            ->map(fn ($e) => self::card($e->provider, $e->provider_id, $e->kind))->filter()->values();
        $owner = $brand->owner_user_id;

        return [
            'videos' => $videos,
            'embeds' => $embeds,
            'music' => BrandMusicLink::query()->where('brand_partner_id', $brand->id)->whereNull('hidden_at')->orderBy('sort_order')->get(),
            'social' => BrandSocialLink::query()->where('brand_partner_id', $brand->id)->whereNull('hidden_at')->orderBy('sort_order')->get(),
            'albums' => BrandAlbum::query()->where('brand_partner_id', $brand->id)->live()->orderBy('sort_order')->orderBy('id')->get(),
            'platforms' => LinkValidator::platforms(),
            'canMessage' => $owner !== null && NaaraPro::active($owner),
            'ownerHandle' => $owner ? \App\Models\User::query()->whereKey($owner)->value('naara_id') : null,
        ];
    }

    private static function card(string $provider, string $id, string $kind): ?array
    {
        $frame = EmbedRenderer::iframe(['provider' => $provider, 'id' => $id]);
        if ($frame === null) {
            return null;
        }
        $thumb = $kind === 'video' && Thumbnail::query()->where('provider', $provider)->where('provider_id', $id)->where('status', 'ok')->exists()
            ? route('n2n.thumb', ['provider' => $provider, 'id' => $id]) : null;

        return ['provider' => $provider, 'id' => $id, 'kind' => $kind, 'thumb' => $thumb, 'src' => $frame['src'], 'sandbox' => $frame['sandbox'], 'allow' => $frame['allow'], 'height' => $frame['height']];
    }
}
