<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** A DISPLAY-ONLY social handle shown on a brand profile (N2N, MASTER-ONLY). Separate from brand_partner_handles, which carry Follow-Hunt rewards. */
class BrandSocialLink extends Model
{
    public $timestamps = false;

    protected $table = 'n2n_brand_social_links';

    protected $fillable = ['brand_partner_id', 'platform', 'url', 'sort_order', 'hidden_at'];

    protected function casts(): array
    {
        return ['hidden_at' => 'datetime'];
    }
}
