<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A brand partner shown on the Brand Partner Hunt directory (BUILD-6 §C.4,
 * extended by BUILD-9). owner_user_id NULL = admin-placed/featured; set = a
 * self-service business listing with its own subscription + billing.
 */
class BrandPartner extends Model
{
    // Listing lifecycle (BUILD-9 §5).
    public const STATUS_PENDING = 'pending_setup';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused_billing';

    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'owner_user_id', 'brand_name', 'short_description', 'category',
        'listing_status', 'is_featured', 'fallback_image', 'hero_image_path',
        'background_color', 'priority_score', 'current_plan_id', 'sort_order', 'is_active',
        'naara_followers_count', 'pro_granted_at', 'pro_grant_expires_at', 'pro_granted_by', 'pro_grant_note',
        'published_at', 'gate_version', 'logo_path', 'chat_mode',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'priority_score' => 'integer',
        'sort_order' => 'integer',
        'naara_followers_count' => 'integer',
        'pro_granted_at' => 'datetime',
        'pro_grant_expires_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    /** Custom links (Brand-Growth Phase 2). Master only: the model and its table do not exist in a white-label install. */
    public function links(): HasMany
    {
        return $this->hasMany(\App\Models\BrandLink::class);
    }

    public function handles(): HasMany
    {
        return $this->hasMany(BrandPartnerHandle::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(BrandPartnerVideo::class);
    }

    /** Featured-image gallery shown on the Brand Profile page. */
    public function images(): HasMany
    {
        return $this->hasMany(BrandPartnerImage::class);
    }

    /** Users who "Connected" to this brand on Naara (owner request, 2026-09-22). */
    public function followers(): HasMany
    {
        return $this->hasMany(BrandPartnerFollower::class);
    }

    public function isFollowedBy(?User $user): bool
    {
        return $user && $this->followers()->where('user_id', $user->id)->exists();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(BrandSubscriptionPlan::class, 'current_plan_id');
    }

    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BrandSubscription::class);
    }

    /** The latest subscription record (billing state). */
    public function subscription(): HasOne
    {
        return $this->hasOne(BrandSubscription::class)->latestOfMany();
    }

    /** Complimentary Pro granted by an admin (no payment). */
    public function hasProGrant(): bool
    {
        return $this->pro_granted_at !== null && ($this->pro_grant_expires_at === null || $this->pro_grant_expires_at->isFuture());
    }

    /**
     * Naara Pro verified: an active paid listing subscription, a running free trial, or an admin grant. Derived on purpose — never a stored flag — so cancelling or
     * lapsing can't leave a stale badge. Eager-load `subscription` wherever many brands are shown.
     */
    public function isNaaraPro(): bool
    {
        return $this->hasProGrant() || in_array($this->subscription?->status, [BrandSubscription::ACTIVE, BrandSubscription::TRIALING], true);
    }

    public function isSelfService(): bool
    {
        return $this->owner_user_id !== null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Publicly visible in the directory — a live listing only. */
    public function scopeListed(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('listing_status', self::STATUS_ACTIVE);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Directory sort (BUILD-9 §8.2): admin-featured first, then self-service by
     * priority_score desc, then longest-standing subscriber first.
     */
    public function scopeDirectoryOrder(Builder $query): Builder
    {
        return $query->orderByDesc('is_featured')
            ->orderByDesc('priority_score')
            ->orderBy('id');
    }
}
