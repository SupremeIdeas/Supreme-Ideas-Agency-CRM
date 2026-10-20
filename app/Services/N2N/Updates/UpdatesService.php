<?php

namespace App\Services\N2N\Updates;

use App\Models\BrandPartner;
use App\Models\BrandPartnerImage;
use App\Models\N2N\Block;
use App\Models\N2N\StatusHidden;
use App\Models\N2N\StatusPost;
use App\Models\N2N\StatusReaction;
use App\Models\N2N\StatusView;
use App\Models\N2N\UpdateReaction;
use App\Models\N2N\UpdateView;
use App\Models\User;
use App\Services\N2N\Connections\Connections;
use App\Services\N2N\Media\NaaraPro;
use App\Services\N2N\Messaging\MessageService;
use App\Services\N2N\Messaging\MessagingException;
use App\Support\N2N\N2NStatusSettings;

/**
 * Updates (N2N Batch 9, MASTER-ONLY): a Status-style ring per CONNECTED member who owns an ACTIVE Naara Pro brand, built from the brand's
 * gallery (no separate upload, no expiry). Visibility is computed ONLY here, on the server, from live data: connected (graph rule),
 * owner still Pro, brand live, brand's own "who can see my Updates" setting, not blocked either way. A viewer never receives the media of a
 * brand they may not see, and a Pro lapse or an unfollow removes the ring on the next request.
 */
class UpdatesService
{
    public const REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

    public function __construct(private readonly Connections $graph) {}

    /** @return list<int> brands this viewer is allowed to see Updates of (connected, still Pro, live, not blocked), before looking at what they posted */
    private function eligibleBrandIds(User $viewer): array
    {
        $owners = $this->graph->connectedIds($viewer->id);
        if ($owners === []) {
            return [];
        }
        $blocked = Block::query()->where('blocker_id', $viewer->id)->pluck('blocked_id')->merge(Block::query()->where('blocked_id', $viewer->id)->pluck('blocker_id'))->all();
        $ids = [];
        foreach (BrandPartner::query()->listed()->whereIn('owner_user_id', $owners)->where('n2n_updates_visibility', 'connections')->with('subscription')->get() as $b) {
            if (in_array($b->owner_user_id, $blocked, true) || ! NaaraPro::active((int) $b->owner_user_id)) {
                continue;
            }
            $ids[] = $b->id;
        }

        return $ids;
    }

    /** @return array<int,int> brand id => owner user id for brands with at least one live status post (empty while status is switched off) */
    private function statusOwners(array $brandIds, ?int $viewerId = null): array
    {
        if ($brandIds === [] || ! N2NStatusSettings::enabled()) {
            return [];
        }
        $owners = BrandPartner::query()->whereIn('id', $brandIds)->pluck('owner_user_id', 'id')->all();
        if ($viewerId !== null) {   // an owner can hide their status from chosen members: those members simply see no status
            $hidden = StatusHidden::query()->where('member_id', $viewerId)->pluck('owner_id')->all();
            $owners = array_filter($owners, fn ($uid) => ! in_array($uid, $hidden, true));
        }
        $live = StatusPost::query()->active()->whereIn('user_id', array_values($owners))->distinct()->pluck('user_id')->all();

        return array_filter($owners, fn ($uid) => in_array($uid, $live, true));
    }

    /** @return list<int> brand ids this viewer may see Updates of: allowed brands that have a live status or a gallery */
    public function visibleBrandIds(User $viewer): array
    {
        $ids = $this->eligibleBrandIds($viewer);
        if ($ids === []) {
            return [];
        }
        $gallery = BrandPartnerImage::query()->whereIn('brand_partner_id', $ids)->distinct()->pluck('brand_partner_id')->map(fn ($i) => (int) $i)->all();

        return array_values(array_unique(array_merge($gallery, array_keys($this->statusOwners($ids, $viewer->id)))));
    }

    /**
     * The LATEST row: a brand with a live 24-hour status shows its status; a brand with none falls back to its gallery so the row stays alive.
     *
     * @return list<array{brand_id: int, name: string, cover: string, count: int, latest_at: int, seen: bool, source: string, bg: ?string}> unseen first, then most recent
     */
    public function rings(User $viewer): array
    {
        $ids = $this->visibleBrandIds($viewer);
        if ($ids === []) {
            return [];
        }
        $views = UpdateView::query()->where('viewer_id', $viewer->id)->whereIn('brand_partner_id', $ids)->get()->keyBy('brand_partner_id');
        $statusOwners = $this->statusOwners($ids, $viewer->id);
        $rings = [];
        foreach (BrandPartner::query()->whereIn('id', $ids)->get() as $b) {
            $imgs = BrandPartnerImage::query()->where('brand_partner_id', $b->id)->orderBy('sort_order')->orderBy('id')->get();
            $cover = (string) ($b->logo_path ?: ($imgs->first()->image_path ?? ''));
            if (isset($statusOwners[$b->id])) {
                $posts = StatusPost::query()->active()->where('user_id', $statusOwners[$b->id])->orderBy('id')->get();
                $seenIds = StatusView::query()->where('viewer_id', $viewer->id)->whereIn('status_id', $posts->pluck('id'))->pluck('status_id')->all();
                $rings[] = ['brand_id' => $b->id, 'name' => $b->brand_name, 'cover' => $cover, 'count' => $posts->count(), 'latest_at' => (int) $posts->max(fn ($p) => $p->created_at->timestamp),
                    'seen' => count($seenIds) >= $posts->count(), 'source' => 'status', 'bg' => $posts->first()->bg];

                continue;
            }
            $latest = $imgs->max(fn ($i) => $i->updated_at?->timestamp ?? $i->created_at?->timestamp ?? 0);
            $v = $views[$b->id] ?? null;
            $rings[] = ['brand_id' => $b->id, 'name' => $b->brand_name, 'cover' => (string) $imgs->first()->image_path, 'count' => $imgs->count(), 'latest_at' => (int) $latest,
                'seen' => $v !== null && $v->last_seen_at->timestamp >= (int) $latest, 'source' => 'gallery', 'bg' => null];
        }
        usort($rings, fn ($a, $b) => [$a['seen'], -$a['latest_at']] <=> [$b['seen'], -$b['latest_at']]);

        return $rings;
    }

    /** Highlights: every allowed brand's profile gallery, always shown, whether or not they posted a status today. @return list<array{brand_id:int,name:string,cover:string,count:int}> */
    public function highlights(User $viewer): array
    {
        $ids = $this->eligibleBrandIds($viewer);
        if ($ids === []) {
            return [];
        }
        $counts = BrandPartnerImage::query()->whereIn('brand_partner_id', $ids)->selectRaw('brand_partner_id, count(*) n')->groupBy('brand_partner_id')->pluck('n', 'brand_partner_id');
        $out = [];
        foreach (BrandPartner::query()->whereIn('id', $counts->keys())->get() as $b) {
            $first = BrandPartnerImage::query()->where('brand_partner_id', $b->id)->orderBy('sort_order')->orderBy('id')->first();
            $out[] = ['brand_id' => $b->id, 'name' => $b->brand_name, 'cover' => (string) $first->image_path, 'count' => (int) $counts[$b->id]];
        }

        return $out;
    }

    /**
     * The items of one brand's Updates, or null when this viewer may not see them (identical to "no such brand"). $source: 'status' (live 24-hour posts),
     * 'gallery' (the profile gallery, i.e. Highlights) or 'auto' (status when there is one, else gallery).
     */
    public function items(User $viewer, int $brandId, string $source = 'auto'): ?array
    {
        if (! in_array($brandId, $this->visibleBrandIds($viewer), true)) {
            return null;
        }
        $brand = BrandPartner::query()->find($brandId);
        $statusOwner = $this->statusOwners([$brandId], $viewer->id)[$brandId] ?? null;
        if ($source === 'status' || ($source === 'auto' && $statusOwner !== null)) {
            if ($statusOwner === null) {
                return [];
            }

            $myReactions = StatusReaction::query()->where('viewer_id', $viewer->id)->pluck('emoji', 'status_id');
            $tags = \App\Models\N2N\StatusMention::query()->whereIn('status_id', StatusPost::query()->active()->where('user_id', $statusOwner)->pluck('id'))->get()->groupBy('status_id');

            return StatusPost::query()->active()->where('user_id', $statusOwner)->orderBy('id')->get()->map(fn (StatusPost $p) => [
                'id' => $p->id, 'source' => 'status', 'type' => $p->kind, 'url' => $p->storage_key ? route('n2n.status.media', $p->id) : '', 'caption' => $p->kind === 'text' ? null : $p->body,
                'body' => $p->kind === 'text' ? $p->body : null, 'bg' => $p->bg, 'font' => $p->font, 'seconds' => $p->duration_s, 'posted_at' => $p->created_at->timestamp, 'reaction' => $myReactions[$p->id] ?? null,
                'mentioned_you' => isset($tags[$p->id]) && $tags[$p->id]->contains('user_id', $viewer->id), 'mentions' => isset($tags[$p->id]) ? $tags[$p->id]->count() : 0,
            ])->all();
        }
        $mine = UpdateReaction::query()->where('viewer_id', $viewer->id)->where('brand_partner_id', $brandId)->pluck('emoji', 'item_id');

        return BrandPartnerImage::query()->where('brand_partner_id', $brandId)->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn ($i) => ['id' => $i->id, 'source' => 'gallery', 'type' => 'image', 'url' => $i->image_path, 'caption' => $i->caption, 'body' => null, 'bg' => null, 'font' => null, 'seconds' => null, 'posted_at' => null, 'reaction' => $mine[$i->id] ?? null])->all();
    }

    public function markSeen(User $viewer, int $brandId, ?int $itemId = null): void
    {
        if (! in_array($brandId, $this->visibleBrandIds($viewer), true)) {
            return;
        }
        UpdateView::query()->updateOrCreate(['viewer_id' => $viewer->id, 'brand_partner_id' => $brandId], ['last_seen_at' => now(), 'last_item_id' => $itemId]);
    }

    /** Replace/clear the viewer's reaction on an item. One per viewer per item; free, rate-limited by the unique row. @return bool whether it was accepted */
    /** React to a live status post (one per viewer, replaceable, free). Only a viewer who may see it can react. */
    public function reactToStatus(User $viewer, int $brandId, int $statusId, ?string $emoji): bool
    {
        $items = $this->items($viewer, $brandId, 'status');
        if ($items === null || ! collect($items)->contains('id', $statusId)) {
            return false;
        }
        if ($emoji === null || $emoji === '') {
            StatusReaction::query()->where(['status_id' => $statusId, 'viewer_id' => $viewer->id])->delete();

            return true;
        }
        if (! in_array($emoji, self::REACTIONS, true)) {
            return false;
        }
        StatusReaction::query()->updateOrCreate(['status_id' => $statusId, 'viewer_id' => $viewer->id], ['emoji' => $emoji, 'created_at' => now()]);

        return true;
    }

    public function react(User $viewer, int $brandId, int $itemId, ?string $emoji): bool
    {
        if (! in_array($brandId, $this->visibleBrandIds($viewer), true) || ! BrandPartnerImage::query()->where('brand_partner_id', $brandId)->whereKey($itemId)->exists()) {
            return false;
        }
        if ($emoji === null || $emoji === '') {
            UpdateReaction::query()->where('viewer_id', $viewer->id)->where('item_id', $itemId)->delete();

            return true;
        }
        if (! in_array($emoji, self::REACTIONS, true)) {
            return false;
        }
        UpdateReaction::query()->updateOrCreate(['viewer_id' => $viewer->id, 'item_id' => $itemId], ['brand_partner_id' => $brandId, 'emoji' => $emoji, 'created_at' => now()]);

        return true;
    }

    /**
     * Reply to an item: a normal N2N message (normal price, normal request rules) to the brand owner, carrying the quoted context. It lands
     * in the viewer<->owner conversation, never anywhere else.
     *
     * @throws MessagingException
     */
    public function reply(User $viewer, int $brandId, int $itemId, string $text, string $clientId, ?string $ip = null, string $source = 'auto'): array
    {
        $items = $this->items($viewer, $brandId, $source);
        $item = $items === null ? null : collect($items)->firstWhere('id', $itemId);
        $brand = $item === null ? null : BrandPartner::query()->find($brandId);
        if ($brand === null || $brand->owner_user_id === null) {
            throw new MessagingException('not_found', 'That update is no longer available.');
        }
        $owner = User::query()->find($brand->owner_user_id);

        return app(MessageService::class)->send($viewer, ['to' => (string) $owner->naara_id], $clientId, $text, $ip, [], [
            'kind' => 'update', 'brand_id' => $brandId, 'brand' => $brand->brand_name, 'item_id' => $itemId, 'source' => $item['source'] ?? 'gallery', 'type' => $item['type'] ?? 'image', 'media' => in_array($item['type'] ?? 'image', ['image', 'video'], true) && ($item['url'] ?? '') !== '' ? (($item['source'] ?? '') === 'status' ? route('n2n.status.media', $item['id'], false) : (string) $item['url']) : null,   // a status link is stored host-relative so a domain change never breaks old cards
            'swatch' => ($item['type'] ?? '') === 'text' ? ['bg' => $item['bg'] ?? null, 'text' => mb_substr((string) ($item['body'] ?? ''), 0, 80)] : null, 'caption' => ($item['caption'] ?? $item['body']) ? mb_substr((string) ($item['caption'] ?? $item['body']), 0, 120) : null,
        ]);
    }

    /** Reaction counts per item for the brand owner's own dashboard. */
    public function reactionCounts(int $brandId): array
    {
        return UpdateReaction::query()->where('brand_partner_id', $brandId)->selectRaw('item_id, emoji, count(*) as n')->groupBy('item_id', 'emoji')->get()->groupBy('item_id')->map(fn ($g) => $g->pluck('n', 'emoji')->all())->all();
    }
}
