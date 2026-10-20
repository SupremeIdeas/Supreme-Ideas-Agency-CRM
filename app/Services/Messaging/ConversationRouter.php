<?php

namespace App\Services\Messaging;

use App\Models\User;
use App\Services\N2N\Identity\IdentityResolver;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Identity\PhoneNormalizer;
use App\Services\N2N\Identity\ResolveThrottled;

/**
 * The single decision point for "how does this message or call travel, and who pays" (Brand-Growth Phase 9, MASTER-ONLY). The UI never hard-codes a charge:
 * it asks the router and shows the answer. Charge matrix (enforced here, proven by ConversationRouterChargeMatrixTest):
 *
 *   Naara ID / @username target ........ N2N, paid from NaaraCredit. Never SMS, never wallet. (A Naara ID is never auto-sent by SMS.)
 *   Number that IS a Naara user ......... N2N by default (credit). SMS/voice by wallet only if the person explicitly chooses it.
 *   Number NOT on Naara, message ........ SMS from the person's own NaaraLine, MAIN WALLET, only after an explicit "send as SMS" opt-in.
 *   Number NOT on Naara, call ........... PSTN voice, MAIN WALLET, per-minute quote.
 *   Not enough money .................... blocked with a specific code. NEVER falls back to the other pot.
 *
 * Hard rules: wallet is never debited for N2N; NaaraCredit never pays for PSTN. This class only decides; it moves no money and sends nothing.
 */
class ConversationRouter
{
    public const BLOCKER_TEXT = [
        'unknown_target' => 'We could not find who that is.',
        'rate_limited' => 'Too many lookups just now. Try again in a minute.',
        'naara_id_never_sms' => 'A Naara ID can only be reached on Naara. It is never sent as an SMS.',
        'sms_opt_in_required' => 'Choose "Send as SMS" to message this number. It is charged to your wallet.',
        'no_line' => 'To send SMS you need a NaaraLine number. Get one first.',
        'pro_required' => 'Sending photos, voice notes and video needs Naara Pro.',
        'insufficient_credit' => 'Not enough NaaraCredit for this. Top up credit. Your wallet is not used for Naara chats.',
        'insufficient_wallet' => 'Not enough wallet balance for this. Add funds. Your NaaraCredit is not used for SMS or calls.',
        'number_blocked' => 'This number has been blocked and cannot be contacted.',
        'country_not_supported' => 'We do not support this destination yet.',
        'quote_unavailable' => 'We cannot price this right now. Try again shortly.',
        'self' => 'That is you.',
    ];

    public function __construct(private readonly IdentityResolver $resolver, private readonly ConversationRouterFacts $facts)
    {
    }

    /**
     * @param  'message'|'call'  $intent
     * @param  array{via_sms?:bool, via_pstn?:bool, media?:bool, ip?:string}  $opts  via_* are the person's explicit choices; media = photo/voice/video
     */
    public function resolve(User $actor, string $target, string $intent, array $opts = []): ConversationRouterPlan
    {
        $target = trim($target);
        $intent = $intent === 'call' ? 'call' : 'message';
        $explicitPstn = $intent === 'call' ? (bool) ($opts['via_pstn'] ?? false) : (bool) ($opts['via_sms'] ?? false);

        // 1. Who is it? A Naara ID / @username is a Naara target. A phone number is checked against opted-in, verified numbers only.
        $isNaaraHandle = NaaraId::normalise($target) !== null || str_starts_with($target, '@');
        $e164 = null;
        $region = null;
        $naara = null;
        try {
            if ($isNaaraHandle) {
                $r = $this->resolver->resolve($target, 'search', $actor, $opts['ip'] ?? null);
                $naara = $r->found ? $r : null;
                if (! $naara) {
                    return $this->plan(ConversationRouterPlan::NONE, ConversationRouterPlan::NONE, null, '', false, ['unknown_target'], $this->t(null, null, null));
                }
            } else {
                $parsed = PhoneNormalizer::normalise($target, $actor->country_code);
                if ($parsed === null) {
                    return $this->plan(ConversationRouterPlan::NONE, ConversationRouterPlan::NONE, null, '', false, ['unknown_target'], $this->t(null, null, null));
                }
                $e164 = $parsed['e164'];
                $region = $parsed['region'];
                $r = $this->resolver->resolve($e164, 'search', $actor, $opts['ip'] ?? null);   // opt-in + verified only; hit and miss look identical
                $naara = $r->found ? $r : null;
            }
        } catch (ResolveThrottled) {
            return $this->plan(ConversationRouterPlan::NONE, ConversationRouterPlan::NONE, null, '', false, ['rate_limited'], $this->t(null, null, $e164));
        }
        if ($naara && (int) $naara->userId === (int) $actor->id) {
            return $this->plan(ConversationRouterPlan::NONE, ConversationRouterPlan::NONE, null, '', false, ['self'], $this->t($naara->naaraId, $naara->name, $e164, true));
        }
        $target = $this->t($naara?->naaraId, $naara?->name, $e164, $naara !== null);

        // 2. A Naara handle can only be reached on Naara.
        if ($isNaaraHandle) {
            if ($explicitPstn) {
                return $this->plan(ConversationRouterPlan::NONE, ConversationRouterPlan::NONE, null, '', false, ['naara_id_never_sms'], $target);
            }

            return $this->n2n($actor, $intent, $opts, $target, false);
        }

        // 3. A number that is on Naara defaults to Naara; the person may still choose SMS/voice (wallet) explicitly.
        if ($naara !== null && ! $explicitPstn) {
            return $this->n2n($actor, $intent, $opts, $target, true);
        }

        // 4. PSTN, main wallet.
        return $this->pstn($actor, $intent, $e164, $region, $explicitPstn, $target, $naara !== null);
    }

    private function n2n(User $actor, string $intent, array $opts, array $target, bool $viaNumber): ConversationRouterPlan
    {
        $blockers = [];
        $price = $intent === 'call' ? $this->facts->n2nCallCreditsPerMinute() : $this->facts->n2nMessageCredits();
        if (($opts['media'] ?? false) && $intent === 'message' && ! $this->facts->isPro($actor)) {
            $blockers[] = 'pro_required';
        }
        if ($this->facts->creditBalance($actor) + 1e-9 < $price) {
            $blockers[] = 'insufficient_credit';   // never "fall back" to the wallet
        }

        return $this->plan(ConversationRouterPlan::N2N, ConversationRouterPlan::CREDIT, $price, $intent === 'call' ? 'minute' : 'message', false, $blockers, $target,
            $viaNumber ? 'This number is on Naara: chatting there is free of SMS charges.' : null);
    }

    private function pstn(User $actor, string $intent, ?string $e164, ?string $region, bool $explicit, array $target, bool $onNaara): ConversationRouterPlan
    {
        $call = $intent === 'call';
        $channel = $call ? ConversationRouterPlan::PSTN_VOICE : ConversationRouterPlan::PSTN_SMS;
        $blockers = [];
        $price = null;
        if (! $this->facts->countryAllowed($region)) {
            $blockers[] = 'country_not_supported';
        }
        if ($e164 !== null && $this->facts->numberBlocked($e164)) {
            $blockers[] = 'number_blocked';
        }
        if ($blockers === []) {
            if ($call) {
                $price = $this->facts->voiceUsdPerMinute($actor, (string) $e164);
            } else {
                $sms = $this->facts->smsLineAndPrice($actor, (string) $e164);
                if ($sms === null) {
                    $blockers[] = 'no_line';
                } else {
                    $price = $sms['usd'];
                }
            }
            if ($price === null && ! in_array('no_line', $blockers, true)) {
                $blockers[] = 'quote_unavailable';
            }
            if ($price !== null && $this->facts->walletUsd($actor) + 1e-9 < $price) {
                $blockers[] = 'insufficient_wallet';   // never "fall back" to NaaraCredit
            }
        }
        // A text to a number is only ever sent as SMS after the person opts in for that conversation (and sees the price first).
        $needsChoice = ! $call && ! $explicit;
        if ($needsChoice && ! in_array('sms_opt_in_required', $blockers, true) && $blockers === []) {
            $blockers[] = 'sms_opt_in_required';
        }

        return $this->plan($channel, ConversationRouterPlan::WALLET, $price, $call ? 'minute' : 'message', $needsChoice, $blockers, $target,
            $onNaara ? 'This number is on Naara. You chose to use SMS/voice, so your wallet is charged.' : ($call ? null : 'Not on Naara. Invite them to chat free, or send as SMS from your wallet.'));
    }

    /** @return array{naara_id:?string,name:?string,e164:?string,on_naara:bool} */
    private function t(?string $naaraId, ?string $name, ?string $e164, bool $on = false): array
    {
        return ['naara_id' => $naaraId, 'name' => $name, 'e164' => $e164, 'on_naara' => $on || $naaraId !== null];
    }

    private function plan(string $channel, string $payer, ?float $price, string $unit, bool $choice, array $blockers, array $target, ?string $suggestion = null): ConversationRouterPlan
    {
        return new ConversationRouterPlan($channel, $payer, $price, $unit, $choice, array_values(array_unique($blockers)), $target, $suggestion);
    }
}
