<?php

namespace App\Services\N2N\Brands;

use App\Models\BrandPartner;
use App\Models\BrandPartnerImage;
use App\Models\BrandPartnerVideo;
use App\Support\N2N\N2NSettings;
use Illuminate\Support\Facades\DB;

/**
 * Server-side cap on gallery images and YouTube/Vimeo videos (N2N Batch 8, MASTER-ONLY). It sits on the model `creating` event, so the 6th
 * (general) / 11th (music) image is refused no matter which form, API or script tried to add it. Counting happens under a row lock on the
 * brand inside the saving transaction. Does nothing while N2N is off, so existing installs behave exactly as before.
 * Existing plan-based video limits (BrandManage) still apply on top of this.
 */
class BrandMediaLimitObserver
{
    /**
     * Second line of defence against two parallel adds both passing the pre-check: after the insert, if the brand is now OVER its cap the
     * row that tipped it over is removed again and the caller gets the same refusal.
     *
     * @throws BrandLimitException
     */
    public function created(BrandPartnerImage|BrandPartnerVideo $row): void
    {
        if (! N2NSettings::enabled()) {
            return;
        }
        $brand = BrandPartner::query()->find($row->brand_partner_id);
        if ($brand === null) {
            return;
        }
        if ($row instanceof BrandPartnerImage) {
            $over = BrandPartnerImage::query()->where('brand_partner_id', $brand->id)->count() > BrandLimits::for($brand, 'images') && BrandPartnerImage::query()->where('brand_partner_id', $brand->id)->orderBy('id')->skip(BrandLimits::for($brand, 'images'))->first()?->id === $row->id;
        } else {
            $platform = in_array($row->platform, ['youtube', 'vimeo'], true) ? $row->platform : 'youtube';
            $q = BrandPartnerVideo::query()->where('brand_partner_id', $brand->id)->where('platform', $platform);
            $over = (clone $q)->count() > BrandLimits::for($brand, $platform) && (clone $q)->orderBy('id')->skip(BrandLimits::for($brand, $platform))->first()?->id === $row->id;
        }
        if ($over) {
            $row->newQuery()->whereKey($row->getKey())->delete();
            throw new BrandLimitException('limit', 'Your profile has reached its limit for this kind of item.');
        }
    }

    /** @throws BrandLimitException */
    public function creating(BrandPartnerImage|BrandPartnerVideo $row): void
    {
        if (! N2NSettings::enabled()) {
            return;
        }
        DB::transaction(function () use ($row) {
            $brand = BrandPartner::query()->lockForUpdate()->find($row->brand_partner_id);
            if ($brand === null) {
                return;
            }
            if ($row instanceof BrandPartnerImage) {
                BrandLimits::assertRoom($brand, 'images', BrandPartnerImage::query()->where('brand_partner_id', $brand->id)->count(), 'gallery images');

                return;
            }
            $platform = in_array($row->platform, ['youtube', 'vimeo'], true) ? $row->platform : 'youtube';
            BrandLimits::assertRoom($brand, $platform, BrandPartnerVideo::query()->where('brand_partner_id', $brand->id)->where('platform', $platform)->count(), ucfirst($platform).' videos');
        });
    }
}
