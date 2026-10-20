<?php

namespace App\Livewire;

use App\Models\BrandPartner;
use App\Models\BrandPartnerHandle;
use App\Models\BrandPartnerVideo;
use App\Models\SocialFollowHandle;
use App\Services\Social\BrandFollowService;
use App\Services\Social\SocialFollowService;
use App\Services\Social\VideoWatchService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Brand Partner Hunt (BUILD-6 §C.4) — the platform's own "Follow us" handles plus
 * a scroll-through of brand partners, each a one-time follow-to-earn claim. The
 * reward is a surprise (never shown before the follow) and the grant is
 * server-side + one-time (SocialFollowService); a claimed button is re-checked
 * server-side on every render, never trusted from the client.
 */
#[Layout('components.layouts.customer')]
class BrandHunt extends Component
{
    public ?string $flash = null;

    /** 'discover' = brands still to follow; 'followed' = brands whose every handle is claimed and that the person has connected with. */
    public string $tab = 'discover';

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'followed' ? 'followed' : 'discover';
        $this->category = null;
    }

    public function booted(): void
    {
        // Batch 8: Brand Hunt / Brand Directory is a tier-locked feature — a
        // basic white-label fork has it hidden (404) until it pays up. Inert on
        // the master (never locked there).
        abort_if(\App\Support\FeatureEntitlements::locked(\App\Support\FeatureLocks::F_BRAND_HUNT), 404);
    }

    /** Claim a platform handle follow (self-confirmed tap → server grant). */
    public function followHandle(int $id, SocialFollowService $svc): void
    {
        $handle = SocialFollowHandle::active()->find($id);
        if (! $handle) {
            return;
        }
        $result = $svc->claim(Auth::user()->fresh(), $handle);
        $this->grant($result, $handle->handle_label);
        $this->resetIfNotStarted($result, 'platform', $handle->id);
    }

    /** Claim a brand-partner handle follow. */
    public function followBrandHandle(int $id, SocialFollowService $svc): void
    {
        $handle = BrandPartnerHandle::active()->find($id);
        if (! $handle) {
            return;
        }
        $result = $svc->claim(Auth::user()->fresh(), $handle);
        $this->grant($result, $handle->handle_label, $handle->brandPartner?->brand_name);
        $this->resetIfNotStarted($result, 'brand', $handle->id);
        $this->announceIfComplete($handle->brand_partner_id);
    }

    /** The server has no record of Follow being pressed (a restored page from another session, a cleared clock): put the button back to Follow so the person can start properly. */
    private function resetIfNotStarted(array $result, string $kind, int $id): void
    {
        if (($result['denied']['code'] ?? null) === 'not_started') {
            $this->dispatch('follow-reset', kind: $kind, id: $id);
        }
    }

    /** "Connect" toggle from the directory card — same plain follow as the profile page. */
    public function toggleConnect(int $brandId, BrandFollowService $svc): void
    {
        $brand = BrandPartner::listed()->find($brandId);
        if (! $brand) {
            return;
        }
        $svc->toggle(Auth::user(), $brand);
        $this->announceIfComplete($brand->id);
    }

    /** Every active handle claimed AND connected: the brand has done its job in Discover and moves to Followed. */
    private function isBrandComplete(int $brandId, ?array $claimed = null): bool
    {
        $user = Auth::user();
        $connected = \App\Models\BrandPartnerFollower::where('user_id', $user->id)->where('brand_partner_id', $brandId)->exists();
        if (! $connected) {
            return false;
        }
        $claimed ??= app(SocialFollowService::class)->claimedBrandHandleIds($user);
        $ids = BrandPartnerHandle::active()->where('brand_partner_id', $brandId)->pluck('id');

        return $ids->every(fn ($id) => isset($claimed[(int) $id]));
    }

    private function announceIfComplete(int $brandId): void
    {
        if ($this->isBrandComplete($brandId)) {
            $name = BrandPartner::whereKey($brandId)->value('brand_name');
            $this->dispatch('nx-toast', type: 'success', message: "{$name} is all done and moved to your Followed tab.");
        }
    }

    /** The person pressed Follow: start the return-gate clock (master only). */
    public function followStarted(string $kind, int $id): void
    {
        if (! class_exists(\App\Services\Social\FollowRewardGate::class)) {
            return;
        }
        $handle = $kind === 'brand' ? BrandPartnerHandle::active()->find($id) : SocialFollowHandle::active()->find($id);
        if ($handle) {
            app(\App\Services\Social\FollowRewardGate::class)->begin(Auth::user(), $handle);
        }
    }

    private function grant(array $result, string $label, ?string $brand = null): void
    {
        if (! empty($result['denied'])) {
            $this->flash = $result['denied']['message'];
            $this->dispatch('nx-toast', type: 'info', message: $this->flash);

            return;
        }
        if (! empty($result['capped'])) {
            $this->flash = \App\Support\DailyCreditCap::MESSAGE;
            $this->dispatch('nx-toast', type: 'info', message: $this->flash);

            return;
        }
        if ($result['already']) {
            $this->flash = 'You already claimed this one.';

            return;
        }
        if ($result['earned'] > 0) {
            $where = $brand ? "{$brand} on {$label}" : $label;
            $this->flash = "+{$result['earned']} NaaraCredits for following {$where}!";
            $this->dispatch('nx-toast', variant: 'hero', type: 'success',
                title: 'Reward unlocked', message: "+{$result['earned']} NaaraCredits for following {$where}. Added to your balance.", sound: 'reward.default');
            $this->dispatch('reward-claimed');
        } else {
            $this->flash = "Thanks for following {$label}!";
        }
    }

        /** Filter to a single category (null = all). */
    public ?string $category = null;

    public function setCategory(?string $category): void
    {
        $this->category = $category ?: null;
    }

    /** Begin a video view session — returns the signed heartbeat token to JS. */
    public function videoStart(int $videoId): ?array
    {
        $video = BrandPartnerVideo::whereHas('brandPartner', fn ($q) => $q->listed())->find($videoId);
        if (! $video) {
            return null;
        }

        return app(VideoWatchService::class)->start(Auth::user()->fresh(), $video);
    }

    /** Process a playback heartbeat; grants the watch credit once ≥60s confirmed. */
    public function videoHeartbeat(string $token, float $currentTime): array
    {
        $r = app(VideoWatchService::class)->heartbeat(Auth::user()->fresh(), $token, $currentTime);
        if (! empty($r['capped'])) {
            $this->flash = \App\Support\DailyCreditCap::MESSAGE;
        } elseif ($r['claimed'] && $r['earned'] > 0) {
            $this->flash = "+{$r['earned']} NaaraCredits for watching!";
            $this->dispatch('nx-toast', variant: 'hero', type: 'success', title: 'Reward unlocked', message: "+{$r['earned']} NaaraCredits added.", sound: 'reward.default');
            $this->dispatch('reward-claimed');
        }

        return $r;
    }

    public function render()
    {
        $svc = app(SocialFollowService::class);
        $user = Auth::user();

        $brandQuery = BrandPartner::listed()->directoryOrder()
            ->with(['handles' => fn ($q) => $q->active()->ordered(), 'videos' => fn ($q) => $q->ordered(), 'subscription']);
        $allBrands = $brandQuery->get();
        // The platform's own handles are rewarded once, in the "Follow Naara" band. A listing that repeats them (Naara's own) shows no second set of Follow buttons.
        $official = \App\Models\BrandPartnerHandle::officialUrlSet();
        $allBrands->each(function ($b) use ($official) {
            $keep = $b->handles->reject(fn ($h) => $h->isOfficial($official))->values();
            $b->official_hidden = $b->handles->count() - $keep->count();
            $b->setRelation('handles', $keep);
        });
        $claimedBrandHandles = $svc->claimedBrandHandleIds($user);
        $connectedIds = \App\Models\BrandPartnerFollower::where('user_id', $user->id)->whereIn('brand_partner_id', $allBrands->pluck('id'))->pluck('brand_partner_id')->flip();
        [$followedBrands, $brands] = $allBrands->partition(fn ($b) => isset($connectedIds[$b->id]) && $b->handles->every(fn ($h) => isset($claimedBrandHandles[$h->id])));
        $followedBrands = $followedBrands->values();
        if ($this->category) {
            $brands = $brands->where('category', $this->category)->values();
        }

        // A real directory, not a flat scroll: the admin-featured band stands
        // apart, and everything else groups by category (BUILD-9 §8.1) —
        // preserving directoryOrder()'s priority sort within each group.
        $featured = $brands->where('is_featured', true)->values();
        $rest = $brands->where('is_featured', false);
        $byCategory = $rest->groupBy(fn ($b) => $b->category ?: 'Other');

        return view('livewire.brand-hunt', [
            'platformHandles' => SocialFollowHandle::active()->ordered()->get(),
            'featuredBrands' => $featured,
            'brandsByCategory' => $byCategory,
            // Categories that actually have listed brands, for the filter bar.
            'categories' => $allBrands->diff($followedBrands)->pluck('category')->filter()->unique()->sort()->values(),
            'claimedHandles' => $svc->claimedHandleIds($user),
            'claimedBrandHandles' => $claimedBrandHandles,
            'followedBrands' => $followedBrands,
            'dailyRemaining' => \App\Support\DailyCreditCap::remaining($user),
            'totalBrandCount' => $allBrands->count() - $followedBrands->count(),
            // "Connect" state (owner request, 2026-09-22) — one query for every
            // card on the page, not one per card.
            'followingBrandIds' => \App\Models\BrandPartnerFollower::where('user_id', $user->id)
                ->whereIn('brand_partner_id', $allBrands->pluck('id'))
                ->pluck('brand_partner_id')->flip(),
        ]);
    }
}
