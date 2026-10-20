<?php

namespace App\Livewire\N2N;

use App\Models\BrandPartner;
use App\Models\BrandPartnerFollower;
use App\Models\Contact;
use App\Models\N2N\Conversation;
use App\Models\N2N\Follow;
use App\Models\N2N\Participant;
use App\Models\User;
use App\Services\N2N\Connections\Connections;
use App\Services\N2N\Connections\Invites;
use App\Services\N2N\Identity\IdentityResolver;
use App\Services\N2N\Identity\ResolveThrottled;
use App\Services\N2N\Brands\BrandFollowNudges;
use App\Services\N2N\Messaging\Privacy;
use App\Services\Social\BrandFollowService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * People (N2N Batch 9, MASTER-ONLY): connections, people who followed you, contacts already on Naara, previous chats, and "add by username /
 * Naara ID / phone / email". An input that does not resolve becomes an Invite (share sheet, email or SMS, optionally with a credit gift).
 */
#[Layout('components.layouts.n2n')]
class People extends Component
{
    public string $q = '';

    public ?array $hit = null;

    public bool $miss = false;

    public string $inviteCredits = '';

    public ?string $message = null;

    public ?string $error = null;

    /** 'people' | 'brands' (a link such as /n2n/people?tab=brands opens straight on the brands tab) */
    #[Url(as: 'tab')]
    public string $tab = 'people';

    /** brands inner tab: 'discover' | 'following' | 'rewards' */
    public string $brandTab = 'discover';

    public string $brandQ = '';

    public string $category = '';

    public function updatedQ(IdentityResolver $resolver): void
    {
        $this->reset('hit', 'miss', 'message', 'error');
        if (strlen(trim($this->q)) < 3) {
            return;
        }
        try {
            $r = $resolver->resolve($this->q, 'search', Auth::user(), request()->ip());
        } catch (ResolveThrottled $e) {
            $this->error = $e->getMessage();

            return;
        }
        if ($r->found && $r->userId !== Auth::id()) {
            $this->hit = ['id' => $r->userId, 'name' => $r->name, 'username' => $r->username, 'naara_id' => $r->naaraId];
        } else {
            $this->miss = true;   // a miss and "that is you" look the same
        }
    }

    public function follow(int $userId, Connections $graph): void
    {
        $this->reset('message', 'error');
        $target = User::query()->find($userId);
        $res = $target ? $graph->follow(Auth::user(), $target) : ['ok' => false, 'message' => 'You cannot follow this account.'];
        $res['ok'] ? $this->message = ($res['connected'] ?? false) ? 'You are now connected.' : 'Followed.' : $this->error = $res['message'];
    }

    public function unfollow(int $userId, Connections $graph): void
    {
        $target = User::query()->find($userId);
        if ($target) {
            $graph->unfollow(Auth::user(), $target);
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'brands' ? 'brands' : 'people';
    }

    public function setBrandTab(string $tab): void
    {
        $this->brandTab = in_array($tab, ['discover', 'following', 'rewards'], true) ? $tab : 'discover';
    }

    /** Connect / disconnect a brand from the directory inside chat (the same follow as the brand page, plus the "follow their pages" nudge). */
    public function toggleBrand(int $brandId, BrandFollowService $svc, BrandFollowNudges $nudges): void
    {
        $this->reset('message', 'error');
        $brand = BrandPartner::listed()->whereKey($brandId)->first();
        if (! $brand || (int) $brand->owner_user_id === (int) Auth::id()) {
            return;
        }
        $res = $svc->toggle(Auth::user(), $brand);
        if (class_exists(\App\Services\Brands\ConnectionIntentService::class)) {
            app(\App\Services\Brands\ConnectionIntentService::class)->settle(Auth::user(), $brand, (bool) $res['following']);
        }
        if ($res['following']) {
            $nudges->afterConnect(Auth::user(), $brand);
            $this->message = 'Connected with '.$brand->brand_name.'. Follow their pages to earn credits.';
        }
    }

    /**
     * One tap from the directory: connect and open the chat in the same move (only when that brand accepts messages from this person).
     * A brand that keeps chat for connections only still connects; the person just stays here.
     */
    public function connectAndMessage(int $brandId, BrandFollowService $svc, BrandFollowNudges $nudges)
    {
        $this->reset('message', 'error');
        $brand = BrandPartner::listed()->with('owner:id,naara_id')->whereKey($brandId)->first();
        if (! $brand || (int) $brand->owner_user_id === (int) Auth::id()) {
            return null;
        }
        $already = BrandPartnerFollower::query()->where('user_id', Auth::id())->where('brand_partner_id', $brand->id)->exists();
        if (! $already) {
            $this->toggleBrand($brandId, $svc, $nudges);
        }
        if ($brand->owner && $brand->owner->naara_id && Privacy::brandAllows($brand, Auth::user())) {
            return $this->redirect(route('n2n.chat', ['to' => $brand->owner->naara_id]), navigate: true);
        }

        return null;
    }

    /** The quick-connect strip: popular brands this person is not connected with yet (no search, no filter, Discover view only). */
    private function quickConnect(array $cards): array
    {
        if ($this->brandTab !== 'discover' || trim($this->brandQ) !== '' || $this->category !== '') {
            return [];
        }

        return array_slice(array_values(array_filter($cards, fn ($c) => ! $c['following'])), 0, 8);
    }

    /** @return list<array<string,mixed>> */
    private function brandCards(BrandFollowNudges $nudges): array
    {
        $me = Auth::user();
        $q = BrandPartner::listed()->directoryOrder()->with(['owner:id,name,naara_id', 'handles' => fn ($h) => $h->active()])
            ->where(fn ($w) => $w->whereNull('owner_user_id')->orWhere('owner_user_id', '!=', $me->id));
        if (trim($this->brandQ) !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($this->brandQ)).'%';
            $q->where(fn ($w) => $w->where('brand_name', 'like', $like)->orWhere('short_description', 'like', $like)->orWhere('category', 'like', $like));
        }
        if ($this->category !== '') {
            $q->where('category', $this->category);
        }
        $brands = $q->limit(80)->get();
        $following = BrandPartnerFollower::query()->where('user_id', $me->id)->whereIn('brand_partner_id', $brands->pluck('id'))->pluck('brand_partner_id')->flip();
        $cards = [];
        foreach ($brands as $b) {
            $left = $nudges->remaining($b, $me);
            $isF = isset($following[$b->id]);
            $cards[] = [
                'id' => $b->id, 'name' => $b->brand_name, 'about' => $b->short_description, 'category' => $b->category, 'logo' => $b->logo_path ?: $b->fallback_image,
                'followers' => (int) $b->naara_followers_count, 'following' => $isF, 'pages_left' => $left['count'], 'credits_left' => $left['credits'],
                'can_message' => $b->owner && Privacy::brandAllows($b, $me), 'naara_id' => $b->owner?->naara_id, 'chat_closed' => ($b->chat_mode ?? 'open') === 'connections',
            ];
        }

        return $cards;
    }

    public function invite(Invites $invites): void
    {
        $this->reset('message', 'error');
        $credits = (float) $this->inviteCredits;
        $res = $invites->invite(Auth::user(), $this->q, max(0, $credits), 'inv-'.bin2hex(random_bytes(6)), request()->ip());
        in_array($res['status'], ['sent', 'gift'], true) ? $this->message = $res['message'] : $this->error = $res['message'];
        if (in_array($res['status'], ['sent', 'gift'], true)) {
            $this->reset('q', 'miss', 'inviteCredits');
        }
    }

    /** @param list<int> $ids */
    private function people(array $ids, Connections $graph): array
    {
        $me = Auth::id();
        $following = Follow::query()->where('follower_id', $me)->whereIn('followed_id', $ids)->pluck('followed_id')->all();

        return User::query()->whereIn('id', $ids)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'username', 'naara_id'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'username' => $u->username, 'naara_id' => $u->naara_id, 'handle' => $u->username ?: $u->naara_id, 'following' => in_array($u->id, $following, true), 'connected' => $graph->connected($me, $u->id)])->all();
    }

    public function render(Connections $graph, Invites $invites, BrandFollowNudges $nudges)
    {
        $cards = $this->tab === 'brands' ? $this->brandCards($nudges) : [];
        $quick = $this->quickConnect($cards);
        $cards = match ($this->brandTab) {
            'following' => array_values(array_filter($cards, fn ($c) => $c['following'])),
            'rewards' => array_values(array_filter($cards, fn ($c) => $c['following'] && $c['pages_left'] > 0)),
            default => $cards,
        };
        $me = Auth::id();
        $connectionIds = $graph->connectedIds($me);
        $followedMe = Follow::query()->where('followed_id', $me)->pluck('follower_id')->diff($connectionIds)->diff(Follow::query()->where('follower_id', $me)->pluck('followed_id'))->values()->all();
        $contactIds = Contact::query()->where('user_id', $me)->whereNotNull('linked_user_id')->pluck('linked_user_id')->unique()->diff($connectionIds)->values()->all();
        $recentIds = Participant::query()->whereIn('conversation_id', Participant::query()->where('user_id', $me)->select('conversation_id'))->where('user_id', '!=', $me)
            ->whereIn('conversation_id', Conversation::query()->where('status', Conversation::ACTIVE)->select('id'))->pluck('user_id')->unique()->diff($connectionIds)->diff($contactIds)->take(30)->values()->all();

        return view('livewire.n2n.people', [
            'connections' => $this->people($connectionIds, $graph),
            'requests' => $this->people(array_map('intval', $followedMe), $graph),
            'contacts' => $this->people(array_map('intval', $contactIds), $graph),
            'recent' => $this->people(array_map('intval', $recentIds), $graph),
            'inviteLink' => $invites->link(Auth::user()),
            'brandCards' => $cards,
            'quickBrands' => $quick,
            'brandCategories' => $this->tab === 'brands' ? BrandPartner::listed()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')->all() : [],
        ]);
    }
}
