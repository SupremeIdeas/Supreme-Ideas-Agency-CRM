<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One invitee's membership in a WalletGroup (Prompt 11 §3, extended by
 * Business Suite Prompt 13 §1/§5). A row exists the moment the owner invites
 * someone; `accepted_at` null means the invite is still pending — a pending
 * member may never spend from the owner's wallet. A null spend cap means
 * uncapped for that currency.
 *
 * `user_id` is nullable to support inviting an email with no NaaraSim
 * account yet (Prompt 13 §5.2): `invited_email` holds the address until
 * that person registers and WalletGroupService::attachRegisteredInvite()
 * links the row to their new `User`. `role` is a denormalized display copy
 * of the actual Spatie team-scoped role assignment (the authorization check
 * itself always goes through WorkspaceRoleService, never this column) — it
 * only carries meaning for `kind = business` groups.
 */
class WalletGroupMember extends Model
{
    public const STATUS_INVITED = 'invited';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REMOVED = 'removed';

    protected $fillable = [
        'wallet_group_id',
        'user_id',
        'invited_email',
        'role',
        'invited_by_user_id',
        'status',
        'spend_cap_usd',
        'spend_cap_ngn',
        'invited_at',
        'accepted_at',
        'toast_shown_at',
    ];

    protected function casts(): array
    {
        return [
            'spend_cap_usd' => 'decimal:4',
            'spend_cap_ngn' => 'decimal:4',
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
            'toast_shown_at' => 'datetime',
        ];
    }

    public function walletGroup(): BelongsTo
    {
        return $this->belongsTo(WalletGroup::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function isActive(): bool
    {
        return $this->accepted_at !== null;
    }

    /** True while this row represents an invite to an email with no NaaraSim account yet. */
    public function awaitingRegistration(): bool
    {
        return $this->user_id === null && $this->invited_email !== null;
    }

    /** The cap for a currency, or null (uncapped) — throws on an unsupported one. */
    public function capFor(string $currency): ?float
    {
        return match (strtoupper($currency)) {
            'USD' => $this->spend_cap_usd !== null ? (float) $this->spend_cap_usd : null,
            'NGN' => $this->spend_cap_ngn !== null ? (float) $this->spend_cap_ngn : null,
            default => throw new \InvalidArgumentException("Unsupported wallet-group currency [{$currency}]."),
        };
    }
}
