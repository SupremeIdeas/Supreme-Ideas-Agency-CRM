<?php

namespace App\Support;

use App\Models\ChangelogEntry;
use App\Models\User;

/**
 * Assembles a user's personal-data export (blueprint Section 26.2 / GDPR
 * portability). Whitelists only the user's OWN data with safe columns —
 * internal cost/profit fields are never included, and third-party PII (the
 * people this user referred) is masked so an export can't leak another user's
 * identity.
 */
class UserDataExporter
{
    public static function for(User $user): array
    {
        $user->loadMissing([
            'wallet',
            'walletTransactions',
            'esimOrders',
            'smsOrders',
            'virtualNumbers',
            'referralsMade.referred',
        ]);

        return [
            'exported_at' => now()->toIso8601String(),
            'notice' => 'This file contains the personal data NaaraSim holds about your account. '
                .'People you referred are shown anonymised to protect their privacy.',

            'account' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'country_code' => $user->country_code,
                'referral_code' => $user->referral_code,
                'kyc_status' => $user->kyc_status,
                'is_active' => $user->is_active,
                'registered_at' => optional($user->created_at)->toIso8601String(),
            ],

            'wallet' => $user->wallet ? [
                'ngn_balance' => $user->wallet->ngn_balance,
                'usd_balance' => $user->wallet->usd_balance,
                'total_deposits' => $user->wallet->total_deposits,
                'total_spent' => $user->wallet->total_spent,
            ] : null,

            'wallet_transactions' => $user->walletTransactions->map(fn ($t) => [
                'type' => $t->type,
                'amount' => $t->amount,
                'currency' => $t->currency,
                'balance_before' => $t->balance_before,
                'balance_after' => $t->balance_after,
                'reference' => $t->reference,
                'description' => $t->description,
                'status' => $t->status,
                'created_at' => optional($t->created_at)->toIso8601String(),
            ])->all(),

            'esim_orders' => $user->esimOrders->map(fn ($o) => [
                'provider' => $o->provider,
                'reference' => $o->provider_order_ref,
                'iccid' => $o->iccid,
                'status' => $o->status,
                'data_remaining_mb' => $o->data_remaining_mb,
                'price_paid' => $o->price_charged,
                'currency' => $o->currency,
                'activated_at' => optional($o->activated_at)->toIso8601String(),
                'expires_at' => optional($o->expires_at)->toIso8601String(),
                'created_at' => optional($o->created_at)->toIso8601String(),
            ])->all(),

            'sms_orders' => $user->smsOrders->map(fn ($o) => [
                'provider' => $o->provider,
                'service' => $o->service_name,
                'phone_number' => $o->phone_number,
                'otp_code' => $o->otp_code,
                'status' => $o->status,
                'price_paid' => $o->charged_to_user,
                'ordered_at' => optional($o->ordered_at)->toIso8601String(),
                'completed_at' => optional($o->completed_at)->toIso8601String(),
            ])->all(),

            'virtual_numbers' => $user->virtualNumbers->map(fn ($n) => [
                'provider' => $n->provider,
                'phone_number' => $n->phone_number,
                'capabilities' => $n->capabilities,
                'monthly_price' => $n->monthly_retail,
                'status' => $n->status,
                'active' => $n->active,
                'next_billing_date' => optional($n->next_billing_date)->toIso8601String(),
                'provisioned_at' => optional($n->provisioned_at)->toIso8601String(),
            ])->all(),

            // Third-party PII filter: never expose a referred user's name, email
            // or phone — only the reward relationship and an anonymised marker.
            'referrals_made' => $user->referralsMade->map(fn ($r) => [
                'referred' => self::maskReferred($r->referred),
                'reward_pct' => $r->reward_pct,
                'rewarded' => $r->rewarded,
                'rewarded_at' => optional($r->rewarded_at)->toIso8601String(),
                'created_at' => optional($r->created_at)->toIso8601String(),
            ])->all(),

            'n2n' => self::n2n($user),
            'business_call_recordings' => self::businessRecordings($user),

            // Contextual appendix, not personal data (content/billing/hot-menu
            // blueprint Phase E) — a snapshot of the platform's own public
            // release history at the moment of this export, for reference.
            'platform_version_history' => [
                'note' => "For reference only — not personal data. A snapshot of this platform's own \"What's New\" history at the time of your export.",
                'entries' => ChangelogEntry::latest('released_at')->latest('id')->limit(20)->get()->map(fn ($e) => [
                    'version' => $e->version,
                    'released_at' => optional($e->released_at)->toDateString(),
                    'summary' => $e->summary,
                ])->all(),
            ],
        ];
    }

    /**
     * Business Suite (MASTER-ONLY): metadata of recordings made on workspaces this person owns. No audio, no transcript text and no caller numbers
     * (those belong to the people who called). Absent where the module is.
     */
    private static function businessRecordings(User $user): ?array
    {
        if (! class_exists(\App\Models\CallRecording::class)) {
            return null;
        }

        return \App\Models\CallRecording::query()->where('user_id', $user->id)->latest('id')->limit(1000)->get()->map(fn ($r) => [
            'recorded_at' => optional($r->created_at)->toIso8601String(),
            'duration_seconds' => $r->duration_seconds,
            'status' => $r->status,
            'consent' => ['regime' => $r->consent['regime'] ?? null, 'caller_action' => $r->consent['action'] ?? null, 'announced_at' => $r->consent['announced_at'] ?? null],
            'expires_at' => optional($r->expires_at)->toIso8601String(),
        ])->all();
    }

    /** N2N (MASTER-ONLY) section: the person's own identity data only, never another user's. Absent where the module is. */
    private static function n2n(User $user): ?array
    {
        if (! class_exists(\App\Services\N2N\N2NErasure::class)) {
            return null;
        }
        $phone = \App\Models\N2N\UserPhone::query()->where('user_id', $user->id)->first();

        return [
            'naara_id' => $user->naara_id,
            'username' => $user->username,
            'hidden_from_search' => (bool) $user->n2n_hide_me,
            'discoverable_by_email' => (bool) $user->n2n_find_by_email,
            'linked_phone' => $phone ? ['number' => $phone->makeVisible('e164')->e164, 'verified_at' => optional($phone->verified_at)->toIso8601String(), 'discoverable' => $phone->discoverable] : null,
            'imported_contacts' => \App\Models\Contact::query()->where('user_id', $user->id)->whereNotNull('imported_at')->count(),
            // Their own messages only (never the other person's), with their reactions, scheduled messages and personal reaction bar.
            'messages_sent' => \App\Models\N2N\Message::query()->where('sender_id', $user->id)->where('suppressed', false)->orderByDesc('id')->limit(5000)->get(['id', 'conversation_id', 'type', 'body', 'created_at', 'deleted_for_all_at'])
                ->map(fn ($m) => ['id' => $m->id, 'conversation' => $m->conversation_id, 'type' => $m->type, 'body' => $m->deleted_for_all_at ? null : $m->body, 'at' => optional($m->created_at)->toIso8601String()])->all(),
            'reactions_given' => \App\Models\N2N\MessageReaction::query()->where('user_id', $user->id)->orderBy('id')->get(['message_id', 'code', 'created_at'])->map(fn ($r) => ['message' => $r->message_id, 'reaction' => $r->code, 'at' => optional($r->created_at)->toIso8601String()])->all(),
            'events' => \App\Models\N2N\EventRsvp::query()->where('user_id', $user->id)->join('n2n_events', 'n2n_events.id', '=', 'n2n_event_rsvps.event_id')->orderBy('n2n_events.starts_at')->get(['n2n_events.title', 'n2n_events.description', 'n2n_events.starts_at', 'n2n_events.timezone', 'n2n_events.creator_id', 'n2n_events.cancelled_at', 'n2n_event_rsvps.status'])->map(fn ($r) => ['title' => $r->title, 'description' => $r->description, 'starts_at' => optional($r->starts_at)->toIso8601String(), 'timezone' => $r->timezone, 'planned_by_you' => (int) $r->creator_id === $user->id, 'cancelled' => $r->cancelled_at !== null, 'your_answer' => $r->status])->all(),
            'followups' => \App\Models\N2N\Followup::query()->where('user_id', $user->id)->orderBy('due_at')->get(['title', 'due_at', 'starred', 'done_at'])->map(fn ($f) => ['title' => $f->title, 'due_at' => optional($f->due_at)->toIso8601String(), 'starred' => (bool) $f->starred, 'done_at' => optional($f->done_at)->toIso8601String()])->all(),
            'scheduled_messages' => \App\Models\N2N\ScheduledMessage::query()->where('sender_id', $user->id)->where('status', 'pending')->orderBy('send_at')->get(['conversation_id', 'body', 'send_at'])->map(fn ($s) => ['conversation' => $s->conversation_id, 'body' => $s->body, 'send_at' => optional($s->send_at)->toIso8601String()])->all(),
            'reaction_bar' => \App\Services\N2N\Messaging\MessageActions::quickBar($user),
        ];
    }

    /** JSON-encoded export, ready to write to disk. */
    public static function toJson(User $user): string
    {
        return json_encode(self::for($user), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /** A non-identifying marker for a referred (third-party) account. */
    private static function maskReferred(?User $referred): ?string
    {
        if ($referred === null) {
            return null;
        }

        $initial = mb_substr((string) $referred->name, 0, 1);

        return ($initial !== '' ? mb_strtoupper($initial) : '?').'••• (user #'.$referred->id.')';
    }
}
