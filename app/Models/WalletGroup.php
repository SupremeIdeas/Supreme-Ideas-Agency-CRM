<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Shared-wallet / family plan primitive (Prompt 11 §3), generalized by
 * Business Suite Prompt 13 §1 into the Business Suite's "Organization"
 * concept rather than a duplicate table — `kind` is the only thing that
 * changes: `family` keeps Prompt 11's simple owner-sets-caps shape,
 * `business` additionally surfaces the owner/admin/agent/viewer roles (see
 * App\Services\Permissions\WorkspaceRoleService) and a `business_email`
 * verification trust signal. A group's spending always debits the OWNER's
 * own UserWallet — this table holds no balance of its own, only who may
 * spend from the owner's wallet and up to what cap.
 */
class WalletGroup extends Model
{
    public const KIND_FAMILY = 'family';

    public const KIND_BUSINESS = 'business';

    protected $fillable = [
        'owner_user_id',
        'name',
        'kind',
        'business_email',
        'business_email_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'business_email_verified_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(WalletGroupMember::class);
    }

    public function isBusiness(): bool
    {
        return $this->kind === self::KIND_BUSINESS;
    }

    /** Trust signal only (Prompt 13 §3.3) — never a hard gate on workspace creation or use. */
    public function needsBusinessEmailVerification(): bool
    {
        if (! $this->business_email || $this->business_email_verified_at) {
            return false;
        }

        return in_array(
            strtolower(Str::after($this->business_email, '@')),
            ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'icloud.com', 'aol.com', 'live.com', 'msn.com', 'proton.me', 'protonmail.com'],
            true,
        );
    }
}
