<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One provider's API version-watch row (master-only Updater surface): what we are pinned to versus what the provider says now. */
class ProviderApiWatch extends Model
{
    protected $guarded = [];

    protected $casts = ['newer_versions' => 'array', 'acknowledged_at' => 'datetime', 'last_checked_at' => 'datetime', 'last_changed_at' => 'datetime'];

    /** A finding the admin has not yet seen: something other than "ok", whose fingerprint is not the one already acknowledged. */
    public function needsAttention(): bool
    {
        return in_array($this->status, ['newer_available', 'deprecated', 'changed'], true) && $this->signature !== $this->acknowledged_signature;
    }
}
