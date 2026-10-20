<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A workspace's AI voice agent settings (MASTER-ONLY, Business Suite Prompt 17). Off until the owner or an admin switches it on. */
class AiVoiceAgentConfig extends Model
{
    protected $fillable = ['wallet_group_id', 'enabled', 'agent_name', 'persona', 'greeting', 'language', 'ring_group_id', 'business_hours_schedule_id', 'max_clarifications'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'max_clarifications' => 'integer'];
    }

    public function walletGroup(): BelongsTo
    {
        return $this->belongsTo(WalletGroup::class);
    }

    public function ringGroup(): BelongsTo
    {
        return $this->belongsTo(RingGroup::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(BusinessHoursSchedule::class, 'business_hours_schedule_id');
    }

    /** Spoken languages the platform's speech engines handle for this agent (BCP-47). */
    public const LANGUAGES = ['en-US' => 'English (US)', 'en-GB' => 'English (UK)', 'fr-FR' => 'French', 'es-ES' => 'Spanish', 'pt-BR' => 'Portuguese', 'de-DE' => 'German'];

    public static function forGroup(WalletGroup $group): self
    {
        return self::query()->where('wallet_group_id', $group->id)->first()
            ?? new self(['wallet_group_id' => $group->id, 'enabled' => false, 'agent_name' => 'Assistant', 'language' => 'en-US', 'max_clarifications' => 3]);
    }
}
