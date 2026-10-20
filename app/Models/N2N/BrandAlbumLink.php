<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** One streaming link of an album (N2N, MASTER-ONLY). */
class BrandAlbumLink extends Model
{
    public $timestamps = false;

    protected $table = 'n2n_brand_album_links';

    protected $fillable = ['album_id', 'platform', 'url', 'sort_order'];
}
