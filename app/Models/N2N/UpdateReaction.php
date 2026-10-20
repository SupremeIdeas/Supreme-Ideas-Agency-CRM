<?php

namespace App\Models\N2N;

use Illuminate\Database\Eloquent\Model;

/** One emoji reaction to one Update item (N2N Batch 9, MASTER-ONLY). */
class UpdateReaction extends Model
{
    public $timestamps = false;

    protected $table = 'n2n_update_reactions';

    protected $fillable = ['viewer_id', 'brand_partner_id', 'item_id', 'emoji', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
