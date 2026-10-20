<?php

namespace App\Services\N2N;

use App\Models\Contact;
use App\Models\N2N\PhoneVerification;
use App\Models\N2N\UserPhone;
use App\Models\User;

/**
 * N2N's part of account erasure (MASTER-ONLY, docs/ACCOUNT-ERASURE.md): unlink the verified phone, null every contact link that pointed
 * at the person, release the username. The Naara ID stays attached to the anonymised row so it can never be re-issued.
 * Each later N2N batch that stores user content adds its own cleanup here.
 */
class N2NErasure
{
    public static function erase(User $user): void
    {
        $numbers = UserPhone::query()->where('user_id', $user->id)->pluck('e164')->all();
        UserPhone::query()->where('user_id', $user->id)->delete();
        PhoneVerification::query()->where('user_id', $user->id)->delete();
        Contact::query()->where('linked_user_id', $user->id)->update(['linked_user_id' => null, 'linked_at' => null]);
        app(\App\Services\N2N\Profiles\ProfileService::class)->eraseAll($user);   // profile picture objects are deleted from storage
        $user->forceFill(['username' => null, 'username_changed_at' => null, 'n2n_hide_me' => true, 'n2n_find_by_email' => false, 'n2n_profile_visibility' => 'private', 'n2n_profile_reveal' => null, 'n2n_gesture_prefs' => null])->saveQuietly();
        // Their uploaded media objects are deleted from storage (the message text stays in the other person's conversation, like any chat).
        $media = app(\App\Services\N2N\Media\MediaService::class);
        foreach (\App\Models\N2N\Attachment::query()->where('uploader_id', $user->id)->where('status', '!=', 'deleted')->get() as $a) {
            $media->deleteObjects($a);
        }
        // Anything queued under their name must never fire from an anonymised account; their reactions, hidden messages, stars and reaction bar are theirs alone.
        \App\Services\N2N\Messaging\LiveLocations::eraseFor($user);
        \App\Services\N2N\Messaging\Planner::eraseFor($user);
        \App\Services\N2N\Messaging\Followups::eraseFor($user);
        \Illuminate\Support\Facades\DB::table('n2n_chat_notice_seen')->where('user_id', $user->id)->delete();
        $queued = \App\Models\N2N\ScheduledMessage::query()->where('sender_id', $user->id)->whereIn('status', ['pending', 'sending', 'failed'])->get();
        foreach ($queued as $q) {
            app(\App\Services\N2N\Messaging\ScheduledSend::class)->releaseAttachments($q);   // held uploads go with the queue
        }
        \App\Models\N2N\ScheduledMessage::query()->where('sender_id', $user->id)->whereIn('status', ['pending', 'sending', 'failed'])->update(['status' => \App\Models\N2N\ScheduledMessage::CANCELLED, 'body' => '', 'attachment_ids' => null]);
        \App\Models\N2N\MessageReaction::query()->where('user_id', $user->id)->delete();
        \App\Models\N2N\PollVote::query()->where('user_id', $user->id)->delete();
        \App\Models\N2N\MessageHide::query()->where('user_id', $user->id)->delete();
        \App\Models\N2N\Star::query()->where('user_id', $user->id)->delete();
        \App\Models\N2N\Message::query()->where('sender_id', $user->id)->whereNotNull('edit_log')->update(['edit_log' => null]);   // earlier versions of edited messages go; the current text stays like any chat
        $user->forceFill(['n2n_reaction_bar' => null, 'n2n_lock_hash' => null, 'n2n_lock_fails' => 0, 'n2n_lock_until' => null])->saveQuietly();
        // Their relationship graph and activity trail: who they follow and who follows them, which Updates they viewed or reacted to, invites they sent, presence.
        \App\Models\N2N\Follow::query()->where('follower_id', $user->id)->orWhere('followed_id', $user->id)->delete();
        app(\App\Services\N2N\Updates\StatusService::class)->eraseFor($user);   // their 24-hour status posts and stored objects go now
        \App\Models\N2N\CallRating::query()->where('user_id', $user->id)->delete();   // their after-call star answers
        \App\Models\N2N\StatusView::query()->where('viewer_id', $user->id)->delete();
        \App\Models\N2N\StatusReaction::query()->where('viewer_id', $user->id)->delete();
        \App\Models\N2N\StatusMention::query()->where('user_id', $user->id)->delete();
        \App\Models\N2N\StatusHidden::query()->where('owner_id', $user->id)->orWhere('member_id', $user->id)->delete();
        \App\Models\N2N\UpdateView::query()->where('viewer_id', $user->id)->delete();
        \App\Models\N2N\BrandProfileVisit::query()->where('visitor_user_id', $user->id)->delete();
        \App\Models\N2N\UpdateReaction::query()->where('viewer_id', $user->id)->delete();
        \App\Models\N2N\Invite::query()->where('inviter_id', $user->id)->delete();
        \App\Models\N2N\Presence::query()->where('user_id', $user->id)->delete();
        \App\Models\N2N\Participant::query()->where('user_id', $user->id)->update(['theme_json' => null, 'pinned_at' => null, 'pinned_message_id' => null, 'muted_until' => null, 'marked_unread_at' => null, 'locked_at' => null]);
        // Pending gifts the person sent go back to them now (retained as a financial record until the retention purge); nothing stays in escrow.
        \App\Models\N2N\CreditGift::query()->where('sender_id', $user->id)->where('status', 'pending')->update(['expires_at' => now()->subSecond()]);
        app(\App\Services\N2N\Credit\CreditGifts::class)->expireDue();
        foreach ($numbers as $e164) {
            \App\Jobs\N2N\LinkContactsJob::dispatch(null, $e164);
        }
    }

    /**
     * N2N's retained financial records for the legal-hold view (MASTER-ONLY): the usage-credit ledger and gifts the person sent. These
     * are money records kept for the retention window like wallet transactions; they carry no message content and no third-party contact
     * details (a gift stores only a keyed hash and a masked display).
     */
    public static function legalHoldRecords(User $user): array
    {
        return [
            'n2n_usage_ledger' => \App\Models\N2N\UsageLedger::query()->where('user_id', $user->id)->orderBy('id')->get(),
            'n2n_credit_gifts_sent' => \App\Models\N2N\CreditGift::query()->where('sender_id', $user->id)->orderBy('id')->get(),
        ];
    }
}
