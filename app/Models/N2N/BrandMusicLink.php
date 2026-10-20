<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** A music-platform link on a brand profile (N2N, MASTER-ONLY). The host was validated against config/n2n_music_platforms.php at write time. */
class BrandMusicLink extends Model
{
    public $timestamps = false;

    protected $table = 'n2n_brand_music_links';

    protected $fillable = ['brand_partner_id', 'platform', 'url', 'sort_order', 'hidden_at'];

    protected function casts(): array
    {
        return ['hidden_at' => 'datetime'];
    }
}
