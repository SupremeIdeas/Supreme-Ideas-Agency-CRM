<?php

namespace App\Models\N2N;

use App\Models\BrandPartner;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One visitor's day on a brand profile (MASTER-ONLY). */
class BrandProfileVisit extends Model
{
    public $timestamps = false;

    protected $table = 'n2n_brand_profile_visits';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['first_at' => 'datetime', 'last_at' => 'datetime', 'hits' => 'integer'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(BrandPartner::class, 'brand_partner_id');
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'visitor_user_id');
    }
}
