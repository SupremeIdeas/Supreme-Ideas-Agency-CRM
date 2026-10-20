<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A merchant's Naara Widgets credential (MASTER-ONLY, see docs/build-specs/NAARA-MERCHANT-WIDGETS-BLUEPRINT.md §4.1/§5).
 * `public_key` is deliberately public (it sits in the merchant's page); it only IDENTIFIES a key. Access is gated server-side by the
 * domain allowlist, product scopes, rate limits and the live `usable()` check below.
 */
class MerchantWidgetKey extends Model
{
    public const ACTIVE = 'active';

    public const REVOKED = 'revoked';

    public const SCOPES = ['esim', 'number', 'giftcard'];

    protected $fillable = [
        'merchant_id', 'label', 'public_key', 'secret_hash', 'allowed_domains', 'product_scopes', 'theme',
        'catalogue_rate_per_min', 'checkout_rate_per_min', 'status', 'revoked_at', 'revoked_reason', 'last_seen_at',
    ];

    protected $hidden = ['secret_hash'];

    protected function casts(): array
    {
        return [
            'allowed_domains' => 'array',
            'product_scopes' => 'array',
            'theme' => 'array',
            'revoked_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(MerchantWidgetOrder::class);
    }

    /** Read-through on every request (never cached): the key is active AND its merchant is still active. */
    public function usable(): bool
    {
        return $this->status === self::ACTIVE && $this->merchant !== null && $this->merchant->isActive();
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->product_scopes ?? [], true);
    }

    /** @return list<string> exact lower-case hosts */
    public function allowedDomains(): array
    {
        return array_values(array_filter(array_map(fn ($d) => strtolower(trim((string) $d)), (array) $this->allowed_domains)));
    }

    public function allowsHost(string $host): bool
    {
        return in_array(strtolower($host), $this->allowedDomains(), true);
    }
}
