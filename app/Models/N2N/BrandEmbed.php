<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** A video or audio embed stored as provider + id ONLY (N2N, MASTER-ONLY). We render our own sandboxed iframe; raw embed HTML is never kept. */
class BrandEmbed extends Model
{
    public $timestamps = false;

    protected $table = 'n2n_brand_embeds';

    protected $fillable = ['brand_partner_id', 'provider', 'provider_id', 'kind', 'sort_order', 'hidden_at'];

    protected function casts(): array
    {
        return ['hidden_at' => 'datetime'];
    }
}
