<?php

namespace App\Services\N2N\Updates;

use App\Models\BrandPartner;
use App\Models\BrandSubscriptionPlan;
use App\Models\N2N\StatusHidden;
use App\Models\N2N\StatusMention;
use App\Models\N2N\StatusPost;
use App\Models\N2N\StatusReaction;
use App\Models\N2N\StatusView;
use App\Models\User;
use App\Services\N2N\Media\ImageSanitizer;
use App\Services\N2N\Media\MediaSniffer;
use App\Services\N2N\Media\NaaraPro;
use App\Services\N2N\Messaging\MessagingException;
use App\Support\Auditor;
use App\Support\Notifications\Notify;
use App\Support\N2N\N2NStatusSettings as S;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Naara Chat status (MASTER-ONLY): 24-hour text, photo, video and voice updates for Naara Pro members. Everything is decided here, on the server.
 * Storage safety: media goes through MediaStorage::privateDisk() (R2, Wasabi, else the private local disk with tight caps and no video), every post
 * expires after 24 hours, and the purge deletes the stored OBJECT before the row. A row is only dropped once its object is really gone.
 */
class StatusService
{
    public const FONTS = ['sans', 'serif', 'mono', 'rounded', 'script', 'bold'];

    public const UNLIMITED = -1;

    /** Most people one status can tag. */
    public const MAX_MENTIONS = 5;

    /** Whether this member may post at all right now (switch on, still Naara Pro). */
    public function canPost(User $user): bool
    {
        return S::enabled() && NaaraPro::active($user);
    }

    /** Posts per rolling 24 hours: the plan's own `status_per_day` capability (-1 = unlimited), else unlimited on the top paid plan, else the admin default. */
    public function limitFor(User $user): int
    {
        $brand = BrandPartner::query()->where('owner_user_id', $user->id)->with('subscription.plan')->first();
        $plan = $brand?->subscription?->plan;
        if ($plan !== null) {
            $cap = $plan->capabilities['status_per_day'] ?? null;
            if (is_numeric($cap)) {
                return (int) $cap < 0 ? self::UNLIMITED : (int) $cap;
            }
            $plans = BrandSubscriptionPlan::query()->where('is_active', true)->orderByDesc('price_usd_per_month')->get();
            if ($plans->count() > 1 && (int) $plans->first()->id === (int) $plan->id) {
                return self::UNLIMITED;
            }
        }

        return S::dailyLimit();
    }

    public function usedToday(User $user): int
    {
        return StatusPost::query()->where('user_id', $user->id)->where('created_at', '>', now()->subHours(S::HOURS))->count();
    }

    /** @throws MessagingException */
    private function guard(User $user, string $kind): void
    {
        if (! S::enabled()) {
            throw new MessagingException('status_off', 'Status is not available right now.');
        }
        if (! NaaraPro::active($user)) {
            throw new MessagingException('not_pro', 'Posting a status is a Naara Pro feature.');
        }
        if (! in_array($kind, S::allowedKinds(), true)) {
            throw new MessagingException('kind_off', $kind === 'video' ? 'Video status needs cloud storage and is not available right now.' : 'That kind of status is not available right now.');
        }
        $limit = $this->limitFor($user);
        if ($limit !== self::UNLIMITED && $this->usedToday($user) >= $limit) {
            throw new MessagingException('limit', "You have reached your limit of {$limit} status updates in 24 hours.");
        }
    }

    /** @throws MessagingException */
    /** @param list<int> $mentionIds */
    public function postText(User $user, string $text, ?string $bg, ?string $font, array $mentionIds = []): StatusPost
    {
        $this->guard($user, 'text');
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
        if ($text === '' || mb_strlen($text) > S::TEXT_MAX) {
            throw new MessagingException('invalid', 'Write something up to '.S::TEXT_MAX.' characters.');
        }

        $post = StatusPost::query()->create([
            'user_id' => $user->id, 'kind' => 'text', 'body' => $text, 'bg' => self::cleanColor($bg) ?? '#0A6E6E', 'font' => in_array($font, self::FONTS, true) ? $font : 'sans',
            'expires_at' => now()->addHours(S::HOURS),
        ]);
        $this->mention($post, $user, $mentionIds);

        return $post;
    }

    /** @param list<int> $mentionIds @throws MessagingException */
    public function postMedia(User $user, UploadedFile $file, ?string $hintKind, ?string $caption, ?int $seconds = null, array $mentionIds = []): StatusPost
    {
        $raw = (string) file_get_contents($file->getRealPath());
        $bytes = strlen($raw);
        $sn = MediaSniffer::sniff(substr($raw, 0, 64), $hintKind === 'voice' ? 'voice' : null);
        $kind = $sn['kind'] ?? '';
        if (! in_array($kind, ['image', 'video', 'voice'], true) || ($hintKind !== null && $hintKind !== $kind && ! ($hintKind === 'image' && $kind === 'image'))) {
            throw new MessagingException('type_not_allowed', 'That file type is not allowed for a status.');
        }
        $this->guard($user, $kind);
        if ($bytes > S::maxBytes($kind)) {
            throw new MessagingException('too_large', 'That file is too large for a status. The limit is '.intdiv(S::maxBytes($kind), 1048576).' MB.');
        }
        if (in_array($kind, ['video', 'voice'], true) && $seconds !== null && $seconds > S::maxSeconds($kind)) {
            throw new MessagingException('too_long', 'That is too long. The limit is '.S::maxSeconds($kind).' seconds.');
        }
        if ($kind === 'image') {
            $clean = $sn['mime'] === 'image/gif' ? ImageSanitizer::cleanGif($raw) : ImageSanitizer::clean($raw, $sn['mime']);
            if ($clean === null) {
                throw new MessagingException('type_not_allowed', 'That image could not be read.');
            }
            [$raw, $sn['mime'], $sn['ext']] = [$clean['bytes'], $clean['mime'], $clean['ext']];
            $bytes = strlen($raw);
        }
        $disk = S::disk();
        $key = 'n2n/status/'.now()->format('Y/m').'/'.Str::random(40).'.'.$sn['ext'];
        Storage::disk($disk)->put($key, $raw, ['visibility' => 'private']);

        $post = StatusPost::query()->create([
            'user_id' => $user->id, 'kind' => $kind, 'body' => $caption !== null ? mb_substr(trim(strip_tags($caption)), 0, 200) ?: null : null, 'disk' => $disk, 'storage_key' => $key,
            'mime' => $sn['mime'], 'bytes' => $bytes, 'duration_s' => $seconds !== null ? min($seconds, S::maxSeconds($kind)) : null, 'expires_at' => now()->addHours(S::HOURS),
        ]);
        $this->mention($post, $user, $mentionIds);

        return $post;
    }

    /**
     * Tag people in a post. Only members the owner is connected with, who are not hidden from the owner's status, and whose "@Name" is still in the text,
     * can be tagged (so nobody is notified about a post they could not see, and a mention the owner deleted from the text is dropped). At most 5.
     * Each tagged member gets one notification. Never throws into the post itself.
     *
     * @param  list<int>  $ids
     */
    private function mention(StatusPost $post, User $owner, array $ids): void
    {
        try {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            if ($ids === [] || ! S::mentionsEnabled()) {
                return;
            }
            $audience = $this->audience($owner);
            $hidden = $this->hiddenFrom($owner);
            $body = mb_strtolower((string) $post->body);
            $n = 0;
            foreach ($ids as $id) {
                $name = $audience[$id] ?? null;
                if ($name === null || in_array($id, $hidden, true) || ! str_contains($body, '@'.mb_strtolower($name)) || $n >= self::MAX_MENTIONS) {
                    continue;
                }
                StatusMention::query()->firstOrCreate(['status_id' => $post->id, 'user_id' => $id], ['created_at' => now()]);
                if ($member = User::query()->find($id)) {
                    Notify::event($member, 'n2n.status_mention', ['name' => $owner->name], dedupe: 'status-mention-'.$post->id);
                }
                $n++;
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** @return list<array{id:int,name:string}> who a post tagged */
    public function mentionsOf(int $statusId): array
    {
        $ids = StatusMention::query()->where('status_id', $statusId)->pluck('user_id');

        return $ids->isEmpty() ? [] : User::query()->whereIn('id', $ids)->get(['id', 'name'])->map(fn ($u) => ['id' => (int) $u->id, 'name' => (string) $u->name])->all();
    }

    /** The owner removes one of their own posts right now (object first). */
    public function delete(User $user, int $id): bool
    {
        $post = StatusPost::query()->where('user_id', $user->id)->find($id);

        return $post !== null && $this->destroy($post);
    }

    /** Delete the stored object, then the row. A failed object delete keeps the row (flagged) so the next purge retries, never leaving an orphan object. */
    public function destroy(StatusPost $post): bool
    {
        if ($post->storage_key) {
            try {
                $store = Storage::disk($post->disk);
                if ($store->exists($post->storage_key)) {
                    $store->delete($post->storage_key);
                }
                if ($store->exists($post->storage_key)) {
                    throw new \RuntimeException('object still present');
                }
            } catch (\Throwable $e) {
                $post->forceFill(['purge_failed_at' => now()])->save();
                report($e);

                return false;
            }
        }
        $post->delete();

        return true;
    }

    /** Expired posts, oldest first, in batches. @return array{deleted:int, failed:int} */
    public function purgeExpired(int $batch = 200): array
    {
        $deleted = $failed = 0;
        StatusPost::query()->where('expires_at', '<=', now())->orderBy('id')->limit($batch)->get()->each(function (StatusPost $p) use (&$deleted, &$failed) {
            $this->destroy($p) ? $deleted++ : $failed++;
        });
        if ($deleted + $failed > 0) {
            Auditor::log('n2n.status_purged', StatusPost::class, null, ['deleted' => $deleted, 'failed' => $failed]);
        }

        return ['deleted' => $deleted, 'failed' => $failed];
    }

    /** Account erasure: every post and object of this member, immediately. */
    public function eraseFor(User $user): void
    {
        StatusPost::query()->where('user_id', $user->id)->get()->each(fn (StatusPost $p) => $this->destroy($p));
    }

    /** Who opened one of the owner's live posts and how they reacted. @return list<array{name:string,naara_id:?string,at:int,emoji:?string}> */
    public function viewers(User $owner, int $statusId): array
    {
        $post = StatusPost::query()->active()->where('user_id', $owner->id)->find($statusId);
        if ($post === null) {
            return [];
        }
        $emoji = StatusReaction::query()->where('status_id', $statusId)->pluck('emoji', 'viewer_id');

        return StatusView::query()->where('status_id', $statusId)->orderByDesc('viewed_at')->limit(300)->get()->map(function (StatusView $v) use ($emoji) {
            $u = User::query()->find($v->viewer_id);

            return ['name' => (string) ($u?->name ?? 'Member'), 'naara_id' => $u?->naara_id, 'at' => $v->viewed_at->timestamp, 'emoji' => $emoji[$v->viewer_id] ?? null];
        })->all();
    }

    /** @return array<int,string> id => name of the members this owner is connected with (the only people who could ever see their status) */
    public function audience(User $owner): array
    {
        $ids = app(\App\Services\N2N\Connections\Connections::class)->connectedIds($owner->id);

        return $ids === [] ? [] : User::query()->whereIn('id', $ids)->orderBy('name')->limit(500)->pluck('name', 'id')->all();
    }

    /** @return list<int> */
    public function hiddenFrom(User $owner): array
    {
        return StatusHidden::query()->where('owner_id', $owner->id)->pluck('member_id')->map(fn ($i) => (int) $i)->all();
    }

    /** Replace the list of members the owner hides their status from. Only connected members can be on it; the member is never told. @param list<int> $memberIds */
    public function setHiddenFrom(User $owner, array $memberIds): void
    {
        $allowed = array_keys($this->audience($owner));
        $keep = array_values(array_intersect(array_map('intval', $memberIds), $allowed));
        StatusHidden::query()->where('owner_id', $owner->id)->whereNotIn('member_id', $keep ?: [0])->delete();
        foreach ($keep as $id) {
            StatusHidden::query()->firstOrCreate(['owner_id' => $owner->id, 'member_id' => $id]);
        }
    }

    public function markViewed(User $viewer, int $statusId): void
    {
        StatusView::query()->firstOrCreate(['status_id' => $statusId, 'viewer_id' => $viewer->id], ['viewed_at' => now()]);
    }

    public static function cleanColor(?string $c): ?string
    {
        return $c !== null && preg_match('/^#[0-9a-fA-F]{6}$/', $c) === 1 ? strtoupper($c) : null;
    }
}
