<?php

namespace App\Services\WhiteLabel\Funnel;

/** The explicit, table-tested state machine of the White Label funnel (MASTER-ONLY). A transition not listed here is refused. */
final class WlFunnelStates
{
    public const LANDING = 'landing_clicked';

    public const REGISTERING = 'registering';

    public const REGISTERED = 'registered';

    public const PREVIEW_ACTIVE = 'preview_active';

    public const PREVIEW_EXPIRED = 'preview_expired';

    public const WIZARD = 'wizard_in_progress';

    public const DRAFT_SAVED = 'draft_saved';

    public const AWAITING_PAYMENT = 'awaiting_payment';

    public const PAYMENT_REVIEW = 'payment_pending_review';

    public const PAID = 'paid';

    public const LICENSE_ISSUED = 'license_issued';

    public const SETUP = 'setup_in_progress';

    public const LIVE = 'live';

    public const ABANDONED = 'abandoned';

    public const EXPIRED = 'expired';

    public const REJECTED = 'rejected';

    public const REFUNDED = 'refunded';

    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        self::LANDING => [self::REGISTERING, self::REGISTERED, self::ABANDONED, self::EXPIRED],
        self::REGISTERING => [self::REGISTERED, self::ABANDONED, self::EXPIRED],
        self::REGISTERED => [self::PREVIEW_ACTIVE, self::WIZARD, self::ABANDONED, self::EXPIRED],
        self::PREVIEW_ACTIVE => [self::PREVIEW_EXPIRED, self::WIZARD, self::ABANDONED, self::EXPIRED],
        self::PREVIEW_EXPIRED => [self::WIZARD, self::DRAFT_SAVED, self::ABANDONED, self::EXPIRED],
        self::WIZARD => [self::DRAFT_SAVED, self::AWAITING_PAYMENT, self::ABANDONED, self::EXPIRED],
        self::DRAFT_SAVED => [self::WIZARD, self::AWAITING_PAYMENT, self::ABANDONED, self::EXPIRED],
        self::AWAITING_PAYMENT => [self::PAYMENT_REVIEW, self::PAID, self::WIZARD, self::DRAFT_SAVED, self::ABANDONED, self::EXPIRED],
        self::PAYMENT_REVIEW => [self::PAID, self::REJECTED, self::AWAITING_PAYMENT],
        self::PAID => [self::LICENSE_ISSUED, self::REFUNDED],
        self::LICENSE_ISSUED => [self::SETUP, self::REFUNDED],
        self::SETUP => [self::LIVE, self::REFUNDED],
        self::LIVE => [self::REFUNDED],
        self::ABANDONED => [self::REGISTERED, self::WIZARD],   // coming back revives it
        self::EXPIRED => [],
        self::REJECTED => [self::AWAITING_PAYMENT],
        self::REFUNDED => [],
    ];

    public static function can(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::TRANSITIONS);
    }
}
