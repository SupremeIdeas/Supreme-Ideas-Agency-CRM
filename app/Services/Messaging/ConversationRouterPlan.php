<?php

namespace App\Services\Messaging;

/** The Conversation Router's answer (Brand-Growth Phase 9, MASTER-ONLY): which channel, who pays, how much, and what is in the way. Never carries provider cost. */
final class ConversationRouterPlan
{
    public const N2N = 'n2n';

    public const PSTN_SMS = 'pstn_sms';

    public const PSTN_VOICE = 'pstn_voice';

    public const NONE = 'none';

    public const CREDIT = 'credit';

    public const WALLET = 'wallet';

    public const PLATFORM = 'platform';

    /**
     * @param  list<string>  $blockers  machine codes, see ConversationRouter::BLOCKER_TEXT
     * @param  array{naara_id:?string, name:?string, e164:?string, on_naara:bool}  $target
     */
    public function __construct(
        public readonly string $channel,
        public readonly string $payer,
        public readonly ?float $price,          // credits for N2N, USD for PSTN; per message or per minute (see unit)
        public readonly string $unit,           // message | minute | ''
        public readonly bool $requiresUserChoice,
        public readonly array $blockers,
        public readonly array $target,
        public readonly ?string $suggestion = null,
    ) {
    }

    public function allowed(): bool
    {
        return $this->blockers === [] && ! $this->requiresUserChoice && $this->channel !== self::NONE;
    }

    public function toArray(): array
    {
        return [
            'channel' => $this->channel, 'payer' => $this->payer, 'price' => $this->price, 'unit' => $this->unit,
            'requires_user_choice' => $this->requiresUserChoice, 'blockers' => $this->blockers, 'target' => $this->target,
            'suggestion' => $this->suggestion, 'allowed' => $this->allowed(),
        ];
    }
}
