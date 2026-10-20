<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** What a viewer has already seen of a brand's Updates (N2N Batch 9, MASTER-ONLY). */
class UpdateView extends Model
{
    public $timestamps = false;

    protected $table = 'n2n_update_views';

    protected $fillable = ['viewer_id', 'brand_partner_id', 'last_seen_at', 'last_item_id'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }
}
