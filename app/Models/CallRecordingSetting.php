<?php

namespace App\Models;

use App\Support\Business\BusinessSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A workspace's recording choices (MASTER-ONLY, Business Suite Prompt 15). Everything defaults to off. */
class CallRecordingSetting extends Model
{
    protected $fillable = ['wallet_group_id', 'recording_enabled', 'intelligence_enabled', 'retention_days', 'legal_hold_at', 'sensitive_numbers'];

    protected function casts(): array
    {
        return [
            'recording_enabled' => 'boolean',
            'intelligence_enabled' => 'boolean',
            'retention_days' => 'integer',
            'legal_hold_at' => 'datetime',
        ];
    }

    /** @return list<string> E.164 numbers this workspace never records */
    public function sensitiveList(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->sensitive_numbers))));
    }

    public function walletGroup(): BelongsTo
    {
        return $this->belongsTo(WalletGroup::class);
    }

    /** The settings row for a workspace, with the platform default retention when none is stored yet. Not saved until the owner saves. */
    public static function forGroup(WalletGroup $group): self
    {
        return self::query()->where('wallet_group_id', $group->id)->first()
            ?? new self(['wallet_group_id' => $group->id, 'retention_days' => BusinessSettings::recordingDefaultRetention()]);
    }

    /** Retention in force: the workspace's choice, never above the platform ceiling. */
    public function effectiveRetentionDays(): int
    {
        return max(1, min((int) $this->retention_days ?: BusinessSettings::recordingDefaultRetention(), BusinessSettings::recordingRetentionCeiling()));
    }
}
