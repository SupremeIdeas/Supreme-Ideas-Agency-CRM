<?php

namespace App\Services\N2N\Connections;

use App\Models\BrandPartner;
use App\Models\BrandPartnerFollower;
use App\Models\N2N\Block;
use App\Models\N2N\Follow;
use App\Models\N2N\Participant;
use App\Models\User;
use App\Notifications\N2NFollowNotice;
use App\Support\N2N\N2NSettings;
use Illuminate\Support\Facades\Cache;

/**
 * The follow graph and the ONE definition of "connected" (N2N Batch 9, MASTER-ONLY). Nothing here is cached, so an unfollow takes effect on
 * the very next request.
 *
 * Connected(a, b) is true when ANY of:
 *   1. a follows b AND b follows a (mutual member follow);
 *   2. a follows a brand that b owns (a Connect on the brand page) AND b follows a back;
 *   3. b follows a brand that a owns AND a follows b back.
 * Following a brand alone, or following someone who does not follow back, is NOT a connection.
 */
class Connections
{
    public function follows(int $a, int $b): bool
    {
        return Follow::query()->where('follower_id', $a)->where('followed_id', $b)->exists();
    }

    public function connected(int $a, int $b): bool
    {
        if ($a === $b) {
            return false;
        }
        $aFollowsB = $this->follows($a, $b);
        $bFollowsA = $this->follows($b, $a);
        if ($aFollowsB && $bFollowsA) {
            return true;
        }

        return ($bFollowsA && $this->followsBrandOf($a, $b)) || ($aFollowsB && $this->followsBrandOf($b, $a));
    }

    /** Does $userId follow (Connect on the page of) any brand owned by $ownerId? */
    public function followsBrandOf(int $userId, int $ownerId): bool
    {
        return BrandPartnerFollower::query()->where('user_id', $userId)
            ->whereIn('brand_partner_id', BrandPartner::query()->where('owner_user_id', $ownerId)->select('id'))->exists();
    }

    /** @return list<int> every user connected to $userId, in a handful of queries (used for lists and the Updates rings) */
    public function connectedIds(int $userId): array
    {
        $iFollow = Follow::query()->where('follower_id', $userId)->pluck('followed_id')->all();
        $followMe = Follow::query()->where('followed_id', $userId)->pluck('follower_id')->all();
        $mutual = array_intersect($iFollow, $followMe);

        // Rule 2: a follows a brand owned by b, and b follows a back.
        $ownersIFollowBrandOf = BrandPartner::query()->whereIn('id', BrandPartnerFollower::query()->where('user_id', $userId)->select('brand_partner_id'))->pluck('owner_user_id')->filter()->all();
        $viaBrandIFollow = array_intersect($ownersIFollowBrandOf, $followMe);
        // Rule 3: b follows a brand that I own, and I follow b back.
        $myBrandIds = BrandPartner::query()->where('owner_user_id', $userId)->pluck('id')->all();
        $followersOfMyBrand = $myBrandIds === [] ? [] : BrandPartnerFollower::query()->whereIn('brand_partner_id', $myBrandIds)->pluck('user_id')->all();
        $viaMyBrand = array_intersect($followersOfMyBrand, $iFollow);

        return array_values(array_unique(array_map('intval', array_merge($mutual, $viaBrandIFollow, $viaMyBrand))));
    }

    /**
     * Follow someone. Blocked either way, self-follows and over-the-daily-limit are refused quietly ("ok" without a row for the blocked
     * case, so the follow button is not an oracle for who blocked whom).
     *
     * @return array{ok: bool, created: bool, connected: bool, message: ?string}
     */
    public function follow(User $actor, User $target): array
    {
        if ($actor->id === $target->id || ! $target->is_active) {
            return ['ok' => false, 'created' => false, 'connected' => false, 'message' => 'You cannot follow this account.'];
        }
        if ($this->blockedEitherWay($actor->id, $target->id)) {
            return ['ok' => true, 'created' => false, 'connected' => false, 'message' => null];   // looks like it worked; it did nothing
        }
        if ($this->follows($actor->id, $target->id)) {
            return ['ok' => true, 'created' => false, 'connected' => $this->connected($actor->id, $target->id), 'message' => null];
        }
        $perDay = N2NSettings::int('follow_per_day', 100);
        if (Follow::query()->where('follower_id', $actor->id)->where('created_at', '>=', now()->subDay())->count() >= $perDay) {
            return ['ok' => false, 'created' => false, 'connected' => false, 'message' => 'You have followed a lot of people today. Please try again tomorrow.'];
        }
        Follow::query()->firstOrCreate(['follower_id' => $actor->id, 'followed_id' => $target->id], ['created_at' => now()]);
        $connected = $this->connected($actor->id, $target->id);
        $this->notify($actor, $target, $connected);

        return ['ok' => true, 'created' => true, 'connected' => $connected, 'message' => null];
    }

    public function unfollow(User $actor, User $target): void
    {
        Follow::query()->where('follower_id', $actor->id)->where('followed_id', $target->id)->delete();
    }

    /** Followers / following counts for the owner's own profile only. */
    public function counts(int $userId): array
    {
        return ['followers' => Follow::query()->where('followed_id', $userId)->count(), 'following' => Follow::query()->where('follower_id', $userId)->count()];
    }

    /** "X followed you", "X wants to connect" (the target already follows a brand of the actor) or "now connected". Skips blocked and muted. */
    private function notify(User $actor, User $target, bool $connected): void
    {
        if ($this->muted($actor->id, $target->id)) {
            return;
        }
        // One notice per pair per day: following, unfollowing and following again must not become a spam tool.
        if (! Cache::add('n2n:follownote:'.$actor->id.':'.$target->id, 1, now()->addDay())) {
            return;
        }
        $kind = $connected ? 'connected' : ($this->followsBrandOf($actor->id, $target->id) ? 'connect' : 'followed');
        $url = $actor->username ? url('/u/'.$actor->username) : ($actor->naara_id ? url('/u/'.$actor->naara_id) : url('/n2n/chat'));
        $target->notify(new N2NFollowNotice($kind, $actor->name ?: 'Someone', $url));
        if ($connected) {   // the person who followed back learns they are connected too
            $actor->notify(new N2NFollowNotice('connected', $target->name ?: 'Someone', $target->username ? url('/u/'.$target->username) : url('/n2n/chat')));
        }
    }

    private function muted(int $actorId, int $targetId): bool
    {
        return Participant::query()->where('user_id', $targetId)->whereNotNull('muted_until')->where('muted_until', '>', now())
            ->whereIn('conversation_id', Participant::query()->where('user_id', $actorId)->select('conversation_id'))->exists();
    }

    private function blockedEitherWay(int $a, int $b): bool
    {
        return Block::query()->where(fn ($q) => $q->where('blocker_id', $a)->where('blocked_id', $b))->orWhere(fn ($q) => $q->where('blocker_id', $b)->where('blocked_id', $a))->exists();
    }
}
