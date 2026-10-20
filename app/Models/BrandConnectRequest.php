<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One person's connection to a brand and the owner's decision on it (MASTER-ONLY). */
class BrandConnectRequest extends Model
{
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    protected $fillable = ['brand_partner_id', 'user_id', 'status', 'intro_status', 'decided_at', 'decided_by', 'conversation_id'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function brandPartner(): BelongsTo
    {
        return $this->belongsTo(BrandPartner::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
