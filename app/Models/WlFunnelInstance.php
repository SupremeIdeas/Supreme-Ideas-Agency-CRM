<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** White Label purchase funnel instance (MASTER-ONLY). The immutable `public_code` is the visitor's reference; the current plan is stored separately. */
class WlFunnelInstance extends Model
{
    protected $fillable = ['public_code', 'plan_key_at_landing', 'origin', 'utm', 'visitor_id', 'user_id', 'status', 'draft_step', 'draft', 'selected_plan_key', 'hosting_choice', 'draft_saved_at', 'expires_at'];

    protected $casts = ['utm' => 'array', 'draft' => 'array', 'draft_saved_at' => 'datetime', 'expires_at' => 'datetime', 'draft_step' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(WlFunnelEvent::class, 'instance_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(WlInvoice::class, 'funnel_instance_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(WlFunnelManualPayment::class, 'funnel_instance_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(WlFunnelNote::class, 'funnel_instance_id');
    }

    /** The plan row this buyer is currently looking at (chosen plan, else the one they clicked on the landing page). */
    public function plan(): ?WhiteLabelLicensePlan
    {
        return WhiteLabelLicensePlan::where('key', $this->selected_plan_key ?: $this->plan_key_at_landing)->first();
    }
}
