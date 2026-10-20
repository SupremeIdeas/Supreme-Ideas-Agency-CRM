<?php

namespace App\Services\N2N\Messaging;

use App\Models\Contact;
use App\Models\N2N\Block;
use App\Models\N2N\Conversation;
use App\Models\User;

/**
 * Who may message whom (N2N, MASTER-ONLY): ONE policy in ONE place. Blocked or disallowed senders are not told: the send "succeeds" for them
 * and reaches nobody, so there is no oracle for who has blocked whom.
 */
class Privacy
{
    public static function isBlocked(int $senderId, int $recipientId): bool
    {
        return Block::query()->where('blocker_id', $recipientId)->where('blocked_id', $senderId)->exists();
    }

    /**
     * Connected = a follow-graph connection (Batch 9: mutual follow, or brand follow + follow-back), OR the recipient has the sender in their
     * linked contacts, OR they already have an accepted conversation.
     */
    public static function connected(int $a, int $b): bool
    {
        if (app(\App\Services\N2N\Connections\Connections::class)->connected($a, $b)) {
            return true;
        }
        if (Contact::query()->where('user_id', $b)->where('linked_user_id', $a)->exists() || Contact::query()->where('user_id', $a)->where('linked_user_id', $b)->exists()) {
            return true;
        }

        return Conversation::query()->where('pair_key', Conversation::pairKey($a, $b))->where('status', Conversation::ACTIVE)->whereNotNull('last_message_id')->exists();
    }

    /** @return 'deliver'|'request'|'drop' what happens to a first message from $sender to $recipient */
    public static function route(User $sender, User $recipient): string
    {
        if (self::isBlocked($sender->id, $recipient->id)) {
            return 'drop';
        }
        $who = (string) ($recipient->n2n_who_can_message ?: 'everyone');
        if ($who === 'nobody') {
            return 'drop';
        }
        $connected = self::connected($sender->id, $recipient->id);
        if ($who === 'connections' && ! $connected) {
            return 'drop';
        }
        // A brand that switched chat to "connections only" does not hear from strangers (people it follows back, contacts and existing chats still reach it).
        if (! $connected && self::ownsClosedBrand($recipient->id)) {
            return 'drop';
        }

        return $connected ? 'deliver' : 'request';
    }

    /** Does this person own a live brand whose chat is "connections only"? */
    public static function ownsClosedBrand(int $userId): bool
    {
        return \App\Models\BrandPartner::query()->listed()->where('owner_user_id', $userId)->where('chat_mode', 'connections')->exists();
    }

    /** May $viewer start a chat with this brand right now? (used to decide whether to show Message or "Connect to message") */
    public static function brandAllows(\App\Models\BrandPartner $brand, User $viewer): bool
    {
        $ownerId = (int) $brand->owner_user_id;
        if ($ownerId === 0 || $ownerId === (int) $viewer->id) {
            return false;
        }
        if (self::isBlocked($viewer->id, $ownerId)) {
            return false;
        }
        if (($brand->chat_mode ?? 'open') !== 'connections') {
            return true;
        }

        return self::connected($viewer->id, $ownerId);
    }
}
