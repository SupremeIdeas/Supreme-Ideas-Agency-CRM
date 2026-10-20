<?php

namespace App\Services\N2N\Messaging;

use App\Models\N2N\Report;
use App\Models\User;
use App\Support\Auditor;

/**
 * Staff handling of member reports and the chat restriction (N2N, MASTER-ONLY). A restriction only stops SENDING (messages, forwards, scheduled sends, calls);
 * the member can still read, react and be reached, and can still export or erase their data. Nothing here is automatic: reports never restrict anyone by
 * themselves (mass-reporting must not be a weapon), a person with several distinct reporters is only flagged for a human to look at.
 */
class Moderation
{
    public const ACTIONS = ['dismissed', 'warned', 'restricted_24h', 'restricted_7d', 'restricted_permanent'];

    /** A person reported by this many DIFFERENT members inside a day is flagged "needs attention" (never auto-actioned). */
    public const ATTENTION_REPORTERS = 3;

    /** Admins and super admins always; staff only with the `n2n.moderate` scope an admin gave them (and can take away). */
    public static function canModerate(?User $user): bool
    {
        return $user !== null && ($user->hasAnyRole(['super_admin', 'admin']) || ($user->hasRole('staff') && $user->can('n2n.moderate')));
    }

    public static function isAdmin(?User $user): bool
    {
        return $user !== null && $user->hasAnyRole(['super_admin', 'admin']);
    }

    public static function restricted(?User $user): bool
    {
        return $user !== null && $user->n2n_restricted_until !== null && $user->n2n_restricted_until->isFuture();
    }

    /** @throws MessagingException */
    public static function assertCanSend(User $user): void
    {
        if (self::restricted($user)) {
            throw new MessagingException('restricted', 'Your chat access is paused. You can still read your messages. Contact support if you think this is a mistake.');
        }
    }

    public function resolve(Report $report, User $staff, string $action, ?string $note = null): Report
    {
        if (! in_array($action, self::ACTIONS, true)) {
            throw new MessagingException('invalid', 'Choose one of the listed actions.');
        }
        if (! self::canModerate($staff)) {
            throw new MessagingException('not_participant', 'You cannot moderate chat reports.');
        }
        $target = User::query()->find($report->reported_id);
        if ($target !== null && str_starts_with($action, 'restricted')) {
            // Least privilege: staff pause chat access for up to 7 days; a permanent restriction, and any action on another staff member or an admin, belongs to an admin.
            if (! self::isAdmin($staff) && ($action === 'restricted_permanent' || $target->hasAnyRole(['super_admin', 'admin', 'staff']))) {
                throw new MessagingException('not_participant', 'That action needs an admin.');
            }
            if ($target->hasAnyRole(['super_admin', 'admin'])) {
                throw new MessagingException('invalid', 'An admin account cannot be restricted from chat here.');
            }
            $until = match ($action) {
                'restricted_24h' => now()->addDay(),
                'restricted_7d' => now()->addDays(7),
                default => now()->addYears(50),
            };
            $target->forceFill(['n2n_restricted_until' => $until, 'n2n_restricted_reason' => mb_substr('Report #'.$report->id.' ('.$report->reason.')', 0, 120)])->saveQuietly();
        }
        $report->forceFill([
            'status' => $action === 'dismissed' ? 'dismissed' : 'actioned', 'action' => $action, 'reviewed_by' => $staff->id, 'reviewed_at' => now(),
            'staff_note' => $note !== null ? mb_substr(trim($note), 0, 500) : null,
        ])->save();
        Auditor::log('n2n.report_resolved', User::class, $report->reported_id, ['report' => $report->id, 'action' => $action, 'by' => $staff->id]);

        return $report;
    }

    public function lift(User $member, User $staff): void
    {
        if (! self::isAdmin($staff) && ! (self::canModerate($staff) && ! $member->hasAnyRole(['staff']) && $member->n2n_restricted_until !== null && $member->n2n_restricted_until->lt(now()->addDays(8)))) {
            throw new MessagingException('not_participant', 'Only an admin can lift this restriction.');
        }
        $member->forceFill(['n2n_restricted_until' => null, 'n2n_restricted_reason' => null])->saveQuietly();
        Auditor::log('n2n.restriction_lifted', User::class, $member->id, ['by' => $staff->id]);
    }

    /** How many different members reported this person in the last day. */
    public static function recentReporters(int $reportedId): int
    {
        return Report::query()->where('reported_id', $reportedId)->where('created_at', '>=', now()->subDay())->distinct()->count('reporter_id');
    }

    /**
     * Give or take away the moderation scope of a staff member. Admins and super admins only; never touches an admin account and never grants anything else.
     */
    public function setModerator(User $actor, User $staff, bool $on): void
    {
        if (! self::isAdmin($actor)) {
            throw new MessagingException('not_participant', 'Only an admin can change who moderates.');
        }
        if (! $staff->hasRole('staff') || $staff->hasAnyRole(['super_admin', 'admin'])) {
            throw new MessagingException('invalid', 'Choose a staff member.');
        }
        $on ? $staff->givePermissionTo('n2n.moderate') : $staff->revokePermissionTo('n2n.moderate');
        Auditor::log($on ? 'n2n.moderator_granted' : 'n2n.moderator_revoked', User::class, $staff->id, ['by' => $actor->id]);
    }
}
