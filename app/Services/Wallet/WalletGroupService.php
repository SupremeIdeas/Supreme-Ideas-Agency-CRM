<?php

namespace App\Services\Wallet;

use App\Exceptions\SpendCapExceededException;
use App\Models\User;
use App\Models\WalletGroup;
use App\Models\WalletGroupMember;
use App\Models\WalletTransaction;
use App\Notifications\WalletGroupInvitedNotification;
use App\Services\Permissions\WorkspaceRoleService;
use App\Support\Auditor;
use App\Support\Business\BusinessSettings;
use Closure;

/**
 * Shared-wallet / family plan primitive (Prompt 11 §3): "a shared eSIM data
 * allowance for a group," scoped as the smallest version that solves that,
 * not a full multi-tenant account system.
 *
 * A group's spending is never a pooled balance of its own — every purchase
 * still debits the OWNER's real UserWallet, through WalletService's
 * completely unmodified public methods (debit/charge). This service's only
 * job is: resolve who's spending against whose wallet, enforce the spend
 * cap the owner set for that member (a cumulative all-time cap per
 * currency — this prompt specifies no reset period, so none is invented),
 * and stamp `spent_by_user_id` via WalletService's existing `$meta` array so
 * every group purchase is attributed to both the spender and the wallet it
 * came from. A user with no group membership is never touched by any of
 * this — every method here requires an explicit WalletGroupMember.
 */
class WalletGroupService
{
    public function __construct(private WalletService $wallet, private WorkspaceRoleService $roles)
    {
    }

    /**
     * The owner's own shared plan, created on first use. One per owner PER
     * KIND — `kind` is part of the match criteria (Business Suite Prompt 13
     * §1) so an owner's family plan and business workspace never collide
     * into the same row; the schema allows more than one of a kind, but
     * nothing here needs that complexity yet.
     */
    public function findOrCreateOwnGroup(User $owner, string $kind = WalletGroup::KIND_FAMILY): WalletGroup
    {
        return WalletGroup::firstOrCreate(
            ['owner_user_id' => $owner->id, 'kind' => $kind],
            ['name' => $this->defaultGroupName($owner, $kind)],
        );
    }

    private function defaultGroupName(User $owner, string $kind): string
    {
        if ($kind === WalletGroup::KIND_BUSINESS) {
            return $owner->name ? "{$owner->name}'s business workspace" : 'Business workspace';
        }

        return $owner->name ? "{$owner->name}'s shared plan" : 'Shared plan';
    }

    /**
     * Invite an existing NaaraSim user to spend from the owner's wallet, up
     * to the given per-currency caps (null = uncapped). Re-inviting someone
     * already invited refreshes their caps rather than creating a duplicate
     * row (the unique wallet_group_id+user_id constraint would reject it
     * anyway) — an already-accepted member simply gets their cap updated,
     * they are not asked to re-accept.
     */
    public function invite(User $owner, User $invitee, ?float $capUsd, ?float $capNgn): WalletGroupMember
    {
        if ($owner->id === $invitee->id) {
            throw new \InvalidArgumentException('You cannot invite yourself to your own shared plan.');
        }

        $group = $this->findOrCreateOwnGroup($owner);

        $member = WalletGroupMember::updateOrCreate(
            ['wallet_group_id' => $group->id, 'user_id' => $invitee->id],
            [
                'spend_cap_usd' => $capUsd,
                'spend_cap_ngn' => $capNgn,
                'invited_at' => now(),
            ],
        );

        Auditor::log('wallet_group.invited', WalletGroupMember::class, $member->id, [
            'owner_user_id' => $owner->id, 'invitee_user_id' => $invitee->id,
        ]);

        $invitee->notify(new WalletGroupInvitedNotification($owner->name ?: 'A Naara user'));

        return $member;
    }

    /**
     * Invite a seat into an owner's BUSINESS workspace by email (Prompt 13
     * §5) — unlike `invite()` above (Prompt 11 §3, family plans, existing
     * users only), this accepts an email with no NaaraSim account yet: the
     * row is created with `user_id` null and `invited_email` set, and
     * later linked by `attachRegisteredInvite()` once that person registers
     * through the same Fortify flow as any other signup.
     */
    public function inviteSeat(User $owner, User $inviter, string $email, string $role, ?float $capUsd, ?float $capNgn): WalletGroupMember
    {
        $email = strtolower(trim($email));

        if (strcasecmp($owner->email, $email) === 0) {
            throw new \InvalidArgumentException('You cannot invite yourself to your own workspace.');
        }

        $group = $this->findOrCreateOwnGroup($owner, WalletGroup::KIND_BUSINESS);
        $existing = User::where('email', $email)->first();

        // Admin ceiling (Business Suite > Workspace limits). A re-invite of someone already holding a seat refreshes it and never counts as a new one.
        $alreadyHasSeat = $group->members()
            ->where(fn ($q) => $q->where('invited_email', $email)->when($existing, fn ($q2) => $q2->orWhere('user_id', $existing->id)))
            ->exists();
        $seatLimit = app(\App\Services\Business\BusinessAccess::class)->limit($group, 'seats');
        if (! $alreadyHasSeat && $group->members()->count() >= $seatLimit) {
            throw new \InvalidArgumentException('This workspace has reached its limit of '.$seatLimit.' seats.');
        }

        $attributes = [
            'spend_cap_usd' => $capUsd,
            'spend_cap_ngn' => $capNgn,
            'invited_at' => now(),
            'invited_by_user_id' => $inviter->id,
            'role' => $role,
            'status' => WalletGroupMember::STATUS_INVITED,
        ];

        $member = $existing
            ? WalletGroupMember::updateOrCreate(
                ['wallet_group_id' => $group->id, 'user_id' => $existing->id],
                [...$attributes, 'invited_email' => null],
            )
            : WalletGroupMember::updateOrCreate(
                ['wallet_group_id' => $group->id, 'invited_email' => $email],
                [...$attributes, 'user_id' => null],
            );

        Auditor::log('wallet_group.seat_invited', WalletGroupMember::class, $member->id, [
            'owner_user_id' => $owner->id, 'wallet_group_id' => $group->id, 'role' => $role,
            'awaiting_registration' => ! $existing,
        ]);

        if ($existing) {
            $existing->notify(new WalletGroupInvitedNotification($owner->name ?: 'A Naara business'));
        }

        return $member;
    }

    /**
     * Called once a new account finishes registering (Prompt 13 §5.2) — any
     * pending seat invites addressed to their email are linked to the new
     * `User` and land them directly in that workspace, no separate accept
     * step, since registering through the invite link is itself the
     * acceptance.
     */
    public function attachRegisteredInvite(User $newUser): void
    {
        WalletGroupMember::whereNull('user_id')
            ->where('invited_email', strtolower($newUser->email))
            ->get()
            ->each(function (WalletGroupMember $member) use ($newUser) {
                $member->forceFill([
                    'user_id' => $newUser->id,
                    'invited_email' => null,
                    'accepted_at' => now(),
                    'status' => WalletGroupMember::STATUS_ACTIVE,
                ])->save();

                if ($member->role) {
                    $this->roles->assignRole($member->walletGroup, $newUser, $member->role);
                }

                Auditor::log('wallet_group.seat_registered', WalletGroupMember::class, $member->id, [
                    'wallet_group_id' => $member->wallet_group_id, 'user_id' => $newUser->id,
                ]);
            });
    }

    /** The invitee accepts — only now can they actually spend from the owner's wallet. */
    public function accept(WalletGroupMember $member): void
    {
        if ($member->isActive()) {
            return;
        }

        $member->forceFill(['accepted_at' => now(), 'status' => WalletGroupMember::STATUS_ACTIVE])->save();
        Auditor::log('wallet_group.accepted', WalletGroupMember::class, $member->id);
    }

    /** The invitee declines, or the owner withdraws an invite — either way the
     *  row is simply removed; nothing was ever spent against it. */
    public function decline(WalletGroupMember $member): void
    {
        Auditor::log('wallet_group.declined', WalletGroupMember::class, $member->id, [
            'wallet_group_id' => $member->wallet_group_id, 'user_id' => $member->user_id,
        ]);
        $member->delete();
    }

    /** The owner removes a member (pending or active) from their shared plan. */
    public function remove(User $owner, WalletGroupMember $member): void
    {
        abort_unless($member->walletGroup->owner_user_id === $owner->id, 403, 'You do not own this shared plan.');

        Auditor::log('wallet_group.removed', WalletGroupMember::class, $member->id, [
            'wallet_group_id' => $member->wallet_group_id, 'user_id' => $member->user_id,
        ]);
        $member->delete();
    }

    /** The owner changes an existing member's cap (null = uncapped). */
    public function updateCap(User $owner, WalletGroupMember $member, ?float $capUsd, ?float $capNgn): void
    {
        abort_unless($member->walletGroup->owner_user_id === $owner->id, 403, 'You do not own this shared plan.');

        $member->forceFill(['spend_cap_usd' => $capUsd, 'spend_cap_ngn' => $capNgn])->save();
        Auditor::log('wallet_group.cap_updated', WalletGroupMember::class, $member->id, [
            'spend_cap_usd' => $capUsd, 'spend_cap_ngn' => $capNgn,
        ]);
    }

    /**
     * Cumulative all-time debits attributed to this member against this
     * group's wallet, minus any refunds attributed back to them — a refund
     * must free up their cap again, not permanently count against it.
     */
    public function totalSpent(WalletGroupMember $member, string $currency): float
    {
        $currency = strtoupper($currency);
        $ownerId = $member->walletGroup->owner_user_id;

        $debited = (float) WalletTransaction::query()
            ->where('user_id', $ownerId)
            ->where('spent_by_user_id', $member->user_id)
            ->where('type', 'debit')
            ->where('currency', $currency)
            ->sum('amount');

        $refunded = (float) WalletTransaction::query()
            ->where('user_id', $ownerId)
            ->where('spent_by_user_id', $member->user_id)
            ->where('type', 'refund')
            ->where('currency', $currency)
            ->sum('amount');

        return round($debited - $refunded, 4);
    }

    /** Throws SpendCapExceededException if this purchase would exceed the member's cap. */
    public function assertCanSpend(WalletGroupMember $member, float $amount, string $currency): void
    {
        $cap = $member->capFor($currency);
        if ($cap === null) {
            return; // uncapped
        }

        $alreadySpent = $this->totalSpent($member, $currency);
        if (round($alreadySpent + $amount, 4) > $cap) {
            throw new SpendCapExceededException($member->user_id, strtoupper($currency), $amount, $alreadySpent, $cap);
        }
    }

    /**
     * Charge-then-deliver from the group owner's wallet on this member's
     * behalf — the group-plan equivalent of WalletService::charge(), reusing
     * it completely unmodified: the owner is passed as the wallet-holder,
     * and `spent_by_user_id` reaches the ledger purely through the existing
     * `$meta` array. Requires an ACCEPTED membership; a pending invite can
     * never spend.
     *
     * @template T
     *
     * @param  Closure(WalletTransaction): T  $deliver
     * @return T
     */
    public function chargeFromGroup(WalletGroupMember $member, float $amount, string $currency, Closure $deliver, array $meta = []): mixed
    {
        abort_unless($member->isActive(), 403, 'This shared-plan invite has not been accepted yet.');
        $this->assertCanSpend($member, $amount, $currency);

        return $this->wallet->charge($member->walletGroup->owner, $amount, $currency, $deliver, [
            ...$meta,
            'spent_by_user_id' => $member->user_id,
        ]);
    }

    /** Refund a group-plan purchase back to the OWNER's wallet, still
     *  attributed to the member who originally spent it. */
    public function refundToGroup(WalletGroupMember $member, float $amount, string $currency, array $meta = []): WalletTransaction
    {
        return $this->wallet->refund($member->walletGroup->owner, $amount, $currency, [
            ...$meta,
            'spent_by_user_id' => $member->user_id,
        ]);
    }
}
