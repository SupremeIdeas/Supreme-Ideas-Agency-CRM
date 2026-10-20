<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The billing record for a self-service brand listing (BUILD-9 §2.3).
 */
class BrandSubscription extends Model
{
    public const ACTIVE = 'active';

    public const PAST_DUE = 'past_due';

    public const CANCELLED = 'cancelled';

    /** Free Naara Pro trial (Brand-Growth Phase 4). Never billed by the monthly sweep; converted by brand-trials:convert. */
    public const TRIALING = 'trialing';

    protected $fillable = [
        'brand_partner_id', 'plan_id', 'status', 'started_at', 'cancelled_at',
        'next_billing_at', 'last_charged_at', 'grace_reminders_sent',
        'trial_started_at', 'trial_ends_at', 'convert_to_plan_id', 'locked_price_usd', 'auto_convert', 'notice_ack_at', 'converted_at',
        'unfunded_since', 'next_retry_at', 'retry_count', 'reminders_sent', 'end_reason', 'email_key', 'phone_key',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'next_billing_at' => 'datetime',
        'last_charged_at' => 'datetime',
        'grace_reminders_sent' => 'integer',
        'trial_started_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'notice_ack_at' => 'datetime',
        'converted_at' => 'datetime',
        'unfunded_since' => 'datetime',
        'next_retry_at' => 'datetime',
        'auto_convert' => 'boolean',
        'retry_count' => 'integer',
        'reminders_sent' => 'integer',
    ];

    public function brandPartner(): BelongsTo
    {
        return $this->belongsTo(BrandPartner::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(BrandSubscriptionPlan::class, 'plan_id');
    }

    public function convertTo(): BelongsTo
    {
        return $this->belongsTo(BrandSubscriptionPlan::class, 'convert_to_plan_id');
    }
}
