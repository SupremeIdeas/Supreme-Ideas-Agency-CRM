<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A brand's custom link (Brand-Growth Phase 2, MASTER-ONLY). Only `approved` links are ever shown to visitors. */
class BrandLink extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const SUSPENDED = 'suspended';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['review_requested' => 'boolean', 'last_checked_at' => 'datetime'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(BrandPartner::class, 'brand_partner_id');
    }

    public function scopeApproved($q)
    {
        return $q->where('status', self::APPROVED);
    }
}
