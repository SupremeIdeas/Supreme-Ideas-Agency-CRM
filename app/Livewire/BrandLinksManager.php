<?php

namespace App\Livewire;

use App\Models\BrandLink;
use App\Models\BrandPartner;
use App\Services\BrandLinks\BrandLinkService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/** The owner's "Business links" card inside Brand management (Brand-Growth Phase 2, MASTER-ONLY): add through the policy, see why a link was refused, ask for a review. */
class BrandLinksManager extends Component
{
    public string $label = '';

    public string $url = '';

    public ?string $flash = null;

    /** @var list<string> */
    public array $problems = [];

    public bool $canReview = false;

    public ?int $reviewFor = null;

    private function brand(): BrandPartner
    {
        return BrandPartner::where('owner_user_id', Auth::id())->firstOrFail();
    }

    public function add(BrandLinkService $svc): void
    {
        $this->flash = null;
        $this->problems = [];
        $this->canReview = false;
        $this->validate(['label' => ['required', 'string', 'max:40'], 'url' => ['required', 'string', 'max:500']]);
        $key = 'brand-link-add:'.Auth::id();
        if (RateLimiter::tooManyAttempts($key, 10)) {   // every attempt fetches a website: a person adds a handful, not hundreds
            $this->problems = ['You have tried a lot of links in a short time. Please wait a few minutes.'];

            return;
        }
        RateLimiter::hit($key, 600);
        try {
            $res = $svc->add($this->brand(), $this->label, $this->url);
        } catch (\DomainException $e) {
            $this->problems = [$e->getMessage()];

            return;
        }
        /** @var \App\Services\BrandLinks\LinkVerdict $v */
        $v = $res['verdict'];
        if ($v->status === 'rejected') {
            $this->problems = $v->failures();
            $this->canReview = $v->canRequestReview && $res['link'] !== null;
            $this->reviewFor = $res['link']?->id;

            return;
        }
        $this->flash = $v->status === 'approved' ? 'Link added. It now shows on your profile.' : 'Link added. A person will check it before it appears.';
        $this->reset('label', 'url');
    }

    public function review(int $id, BrandLinkService $svc): void
    {
        $link = $this->brand()->links()->findOrFail($id);
        $svc->requestReview($link);
        $this->flash = 'Thanks. We will review it.';
        $this->problems = [];
        $this->canReview = false;
        $this->reset('label', 'url');
    }

    public function remove(int $id): void
    {
        $this->brand()->links()->whereKey($id)->delete();
    }

    public function render()
    {
        return view('livewire.brand-links-manager', ['links' => $this->brand()->links()->orderBy('sort_order')->get(), 'limit' => BrandLinkService::limit()]);
    }
}
