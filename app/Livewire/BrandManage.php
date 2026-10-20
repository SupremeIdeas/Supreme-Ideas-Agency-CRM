<?php

namespace App\Livewire;

use App\Models\BrandPartner;
use App\Models\BrandPartnerHandle;
use App\Models\BrandPartnerVideo;
use App\Models\BrandSubscriptionPlan;
use App\Models\SocialFollowClaim;
use App\Services\Brands\BrandSubscriptionService;
use App\Support\BrandCategories;
use App\Support\BrandHandleFormat;
use App\Support\MediaStorage;
use App\Support\SocialPlatforms;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Business-facing brand management + guided onboarding (BUILD-9 §5.1/§5.5). A
 * pending listing shows a "complete your setup" mode; once every required field
 * is present it activates. Handles/videos are capped to the current plan; the
 * one-time-claim/follow history is preserved (handles are added/removed
 * individually, never bulk-replaced). Change plan or cancel from here.
 */
#[Layout('components.layouts.customer')]
class BrandManage extends Component
{
    use WithFileUploads;

    public array $profile = ['brand_name' => '', 'category' => '', 'short_description' => '', 'background_color' => '#0A6E6E'];

    public $heroImage = null;

    public $logoImage = null;

    /** New-handle form. */
    public array $newHandle = ['platform' => 'instagram', 'handle_label' => '', 'handle_url' => ''];

    /** New-video form. */
    public array $newVideo = ['platform' => 'youtube', 'video_url' => ''];

    /** The readable Business Card address the owner is typing (Brand-Growth Phase 5, master only). */
    public string $cardSlug = '';

    /** Who can start a chat with this brand: 'open' or 'connections' (master only). */
    public string $chatMode = 'open';

    public ?string $flash = null;

    public ?string $error = null;

    private BrandPartner $brand;

    public function mount(BrandSubscriptionService $subs)
    {
        // Batch 8: the brand-owner dashboard is part of the Brand Hunt feature —
        // locked with it. Inert on the master (never locked there).
        abort_if(\App\Support\FeatureEntitlements::locked(\App\Support\FeatureLocks::F_BRAND_HUNT), 404);

        $brand = BrandPartner::where('owner_user_id', Auth::id())->first();
        if (! $brand) {
            return redirect()->route('brand.get-listed');
        }
        $this->brand = $brand;
        $this->cardSlug = (string) ($brand->card_slug ?? '');
        $this->chatMode = ($brand->chat_mode ?? 'open') === 'connections' ? 'connections' : 'open';
        $this->profile = [
            'brand_name' => $brand->brand_name,
            'category' => $brand->category ?: '',
            'short_description' => $brand->short_description ?: '',
            'background_color' => $brand->background_color ?: '#0A6E6E',
        ];
    }

    /** The setup wizard saved something: reload the form fields so the dashboard shows it. */
    #[On('brand-updated')]
    public function reloadFromWizard(): void
    {
        $brand = $this->brand();
        $this->cardSlug = (string) ($brand->card_slug ?? '');
        $this->profile = [
            'brand_name' => $brand->brand_name,
            'category' => $brand->category ?: '',
            'short_description' => $brand->short_description ?: '',
            'background_color' => $brand->background_color ?: '#0A6E6E',
        ];
    }

    private function brand(): BrandPartner
    {
        return BrandPartner::with(['plan', 'subscription', 'handles', 'videos'])
            ->where('owner_user_id', Auth::id())->firstOrFail();
    }

    public function saveProfile(BrandSubscriptionService $subs): void
    {
        $data = $this->validate([
            'profile.brand_name' => 'required|string|max:120',
            // Under the soft gate (version 2) the category is recommended, not required.
            'profile.category' => (int) $this->brand()->gate_version >= \App\Services\Brands\BrandVisibility::GATE_V2 ? 'nullable|string|max:80' : 'required|string|max:80',
            'profile.short_description' => 'nullable|string|max:500',
            'profile.background_color' => 'required|regex:/^#[0-9a-fA-F]{6}$/',
            'heroImage' => 'nullable|image|mimes:webp,jpg,jpeg,png|max:2048',
            'logoImage' => 'nullable|image|mimes:webp,jpg,jpeg,png|max:1024',
        ])['profile'];

        abort_unless($data['category'] === null || $data['category'] === '' || BrandCategories::isValid($data['category']), 422);
        $brand = $this->brand();
        if ($this->heroImage) {
            $brand->hero_image_path = MediaStorage::storePublic($this->heroImage, 'brand-hero');
            $this->heroImage = null;
        }
        if ($this->logoImage) {
            $brand->logo_path = MediaStorage::storePublic($this->logoImage, 'brand-logo');
            $this->logoImage = null;
        }
        $brand->fill([
            'brand_name' => $data['brand_name'],
            'category' => $data['category'] ?: null,
            'short_description' => $data['short_description'],
            'background_color' => $data['background_color'],
        ])->save();

        $activated = $subs->activateIfComplete($brand);
        $this->flash = $activated ? 'Your listing is now live in the directory!' : 'Saved.';
    }

    /** Who may start a chat with the brand: everyone, or only people it is connected with (follow each other). Master only. */
    public function saveChatMode(): void
    {
        abort_unless(class_exists(\App\Services\N2N\Brands\BrandVisitService::class), 404);
        $this->validate(['chatMode' => 'required|in:open,connections']);
        $this->brand()->forceFill(['chat_mode' => $this->chatMode])->save();
        $this->flash = $this->chatMode === 'connections' ? 'Chat is now for connections only. Strangers cannot message this brand.' : 'Anyone can message this brand.';
    }

    /** Set or clear the readable card address (master only). The Naara ID and short-code addresses keep working either way. */
    public function saveCardSlug(): void
    {
        if (! class_exists(\App\Services\Brands\BusinessCardService::class)) {
            abort(404);
        }
        $this->error = null;
        try {
            app(\App\Services\Brands\BusinessCardService::class)->setSlug($this->brand(), $this->cardSlug);
            $this->cardSlug = strtolower(trim($this->cardSlug));
            $this->flash = $this->cardSlug === '' ? 'Readable address removed. Your Naara ID address still works.' : 'Card address saved.';
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    /** Press Publish (Brand-Growth Phase 3): only when the content gate passes; nothing is visible publicly before this. */
    public function publish(): void
    {
        $this->error = null;
        try {
            $brand = \App\Services\Brands\BrandVisibility::publish($this->brand());
        } catch (\DomainException $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->flash = \App\Services\Brands\BrandVisibility::message($brand->fresh(['subscription']));
    }

    public function addHandle(): void
    {
        $this->error = null;
        $brand = $this->brand();
        $limit = (int) ($brand->plan->handles_included ?? 1);
        if ($brand->handles->count() >= $limit) {
            $this->error = "Your plan allows {$limit} ".\Illuminate\Support\Str::plural('handle', $limit).'. Upgrade to add more.';

            return;
        }
        $this->validate([
            'newHandle.platform' => 'required|in:'.implode(',', SocialPlatforms::keys()),
            'newHandle.handle_label' => 'required|string|max:80',
            'newHandle.handle_url' => 'required|url|max:512',
        ]);
        if (! BrandHandleFormat::matches($this->newHandle['platform'], $this->newHandle['handle_url'])) {
            $this->addError('newHandle.handle_url', 'That doesn’t look like a valid '.SocialPlatforms::label($this->newHandle['platform']).' URL. '.BrandHandleFormat::hint($this->newHandle['platform']));

            return;
        }
        BrandPartnerHandle::create([
            'brand_partner_id' => $brand->id,
            'platform' => $this->newHandle['platform'],
            'handle_label' => $this->newHandle['handle_label'],
            'handle_url' => $this->newHandle['handle_url'],
            'credit_reward' => 2, // default surprise reward; admin can tune
            'verification' => 'self',
            'is_active' => true,
            'sort_order' => (int) $brand->handles()->max('sort_order') + 1,
        ]);
        $this->newHandle = ['platform' => 'instagram', 'handle_label' => '', 'handle_url' => ''];
        $this->flash = 'Handle added.';
    }

    public function removeHandle(int $id): void
    {
        BrandPartnerHandle::where('brand_partner_id', $this->brand()->id)->whereKey($id)->delete();
    }

    public function addVideo(): void
    {
        $this->error = null;
        $brand = $this->brand();
        $limit = (int) ($brand->plan->video_previews_allowed ?? 0);
        if ($limit <= 0) {
            $this->error = 'Your plan doesn’t include video previews.';

            return;
        }
        if ($brand->videos->count() >= $limit) {
            $this->error = "Your plan allows {$limit} video ".\Illuminate\Support\Str::plural('preview', $limit).'.';

            return;
        }
        $this->validate([
            'newVideo.platform' => 'required|in:youtube,vimeo',
            'newVideo.video_url' => 'required|url|max:512',
        ]);
        BrandPartnerVideo::create([
            'brand_partner_id' => $brand->id,
            'video_url' => $this->newVideo['video_url'],
            'platform' => $this->newVideo['platform'],
            'sort_order' => (int) $brand->videos()->max('sort_order') + 1,
        ]);
        $this->newVideo = ['platform' => 'youtube', 'video_url' => ''];
        $this->flash = 'Video added.';
    }

    public function removeVideo(int $id): void
    {
        BrandPartnerVideo::where('brand_partner_id', $this->brand()->id)->whereKey($id)->delete();
    }

    public function changePlan(int $planId, BrandSubscriptionService $subs)
    {
        $this->error = null;
        $plan = BrandSubscriptionPlan::active()->find($planId);
        $brand = $this->brand();
        if (! $plan) {
            return;
        }
        // §5.4: never silently drop content on a downgrade — ask them to trim first.
        if ($brand->handles->count() > $plan->handles_included) {
            $this->error = "That plan allows {$plan->handles_included} handles — remove ".($brand->handles->count() - $plan->handles_included).' first, then switch.';

            return null;
        }
        if ($brand->videos->count() > $plan->video_previews_allowed) {
            $this->error = "That plan allows {$plan->video_previews_allowed} videos — remove some first, then switch.";

            return null;
        }
        try {
            $subs->subscribe(Auth::user(), $plan);
        } catch (\App\Exceptions\InsufficientBalanceException) {
            $this->error = 'Top up your wallet to switch plans (first month of the new plan is charged now).';

            return null;
        }
        $this->flash = 'Plan changed to '.$plan->name.'.';

        return $this->redirect(route('brand.manage'), navigate: true);
    }

    public function cancel(BrandSubscriptionService $subs): void
    {
        $subs->cancel($this->brand());
        $this->flash = 'Your listing has been cancelled. You can resubscribe anytime.';
    }

    // ---- Naara Pro free trial (master only: every method is a 404 where the module is absent) ----------------------------------------

    private function trialSub(): \App\Models\BrandSubscription
    {
        abort_unless(class_exists(\App\Services\Brands\BrandTrialService::class), 404);
        $sub = $this->brand()->subscription;
        abort_if(! $sub || $sub->trial_started_at === null, 404);

        return $sub;
    }

    public function trialConvertNow(): void
    {
        $sub = $this->trialSub();
        $ok = app(\App\Services\Brands\BrandTrialService::class)->convertNow($sub);
        $ok ? $this->flash = 'Welcome to Naara Pro. Your first payment went through.' : $this->error = 'Your wallet could not cover the plan yet. Add funds, then try again.';
    }

    public function trialReactivate(): void
    {
        try {
            $ok = app(\App\Services\Brands\BrandTrialService::class)->reactivate($this->trialSub());
        } catch (\DomainException $e) {
            $this->error = $e->getMessage();

            return;
        }
        $ok ? $this->flash = 'Naara Pro is back on.' : $this->error = 'Your wallet still cannot cover the plan. Add funds, then press Reactivate.';
    }

    public function trialCancel(): void
    {
        app(\App\Services\Brands\BrandTrialService::class)->cancel($this->trialSub());
        $this->flash = 'Your trial is cancelled. You were not charged.';
    }

    public function trialAuto(bool $on): void
    {
        app(\App\Services\Brands\BrandTrialService::class)->setAutoConvert($this->trialSub(), $on);
        $this->flash = $on ? 'Your plan will start automatically when the trial ends.' : 'Auto-start is off. The trial will end without a charge.';
    }

    public function render()
    {
        $brand = $this->brand();
        $sub = $brand->subscription;
        $guarantee = (int) ($brand->plan->guaranteed_followers_per_handle_per_month ?? 0);
        $cycleStart = $sub ? Carbon::parse($sub->last_charged_at ?? $sub->started_at) : now()->startOfMonth();

        // Real delivery this cycle, per handle (the transparency Frank wanted).
        $delivery = $brand->handles->map(function ($h) use ($cycleStart, $guarantee) {
            $actual = SocialFollowClaim::where('brand_partner_handle_id', $h->id)
                ->where('claimed_at', '>=', $cycleStart)->count();

            return ['handle' => $h, 'actual' => $actual, 'guaranteed' => $guarantee];
        });

        $trial = null;
        if (class_exists(\App\Services\Brands\BrandTrialService::class) && $sub && $sub->trial_started_at !== null && $sub->converted_at === null
            && in_array($sub->status, [\App\Models\BrandSubscription::TRIALING, \App\Models\BrandSubscription::PAST_DUE], true)) {
            $price = (float) ($sub->locked_price_usd ?? 0);
            $trial = ['sub' => $sub, 'unfunded' => $sub->status === \App\Models\BrandSubscription::PAST_DUE, 'price' => $price,
                'plan' => ($sub->convertTo ?? $sub->plan)?->name ?? 'Naara Pro',
                'short' => $price > 0 && (float) ($brand->owner?->wallet?->usd_balance ?? 0) < $price];
        }

        $card = null;
        if (class_exists(\App\Services\Brands\BusinessCardService::class)) {
            $cards = app(\App\Services\Brands\BusinessCardService::class);
            $cardUrl = $cards->url($brand);
            if ($cardUrl) {
                $live = BrandPartner::listed()->whereKey($brand->id)->exists();
                $card = ['url' => $cardUrl, 'live' => $live, 'code' => $cards->code($brand), 'qr' => $live ? $cards->svg($cardUrl.'?s=qr', 220) : null, 'stats' => $live ? $cards->stats($brand) : null,
                    'short' => $live ? $cards->shortUrl($brand) : null, 'snippets' => $live ? $cards->snippets($brand) : [], 'idUrl' => route('business-card.show', $cards->code($brand))];
            }
        }

        $visits = class_exists(\App\Services\N2N\Brands\BrandVisitService::class) ? app(\App\Services\N2N\Brands\BrandVisitService::class) : null;

        return view('livewire.brand-manage', [
            'visitStats' => $visits?->stats($brand),
            'visitors' => $visits?->recentMembers($brand, 15) ?? [],
            'card' => $card,
            'trial' => $trial,
            'brand' => $brand,
            'subscription' => $sub,
            'plans' => BrandSubscriptionPlan::active()->ordered()->get(),
            'platforms' => SocialPlatforms::all(),
            'categories' => BrandCategories::all(),
            'delivery' => $delivery,
            'setupComplete' => app(BrandSubscriptionService::class)->setupComplete($brand),
            'visibility' => [
                'requirements' => \App\Services\Brands\BrandVisibility::requirements($brand, \App\Services\Brands\BrandVisibility::GATE_V2),   // always the new checklist (grandfathered brands see it as a nudge)
                'progress' => ['done' => count(array_filter(\App\Services\Brands\BrandVisibility::requirements($brand, 2), fn ($r) => $r['required'] && $r['met'])), 'total' => count(array_filter(\App\Services\Brands\BrandVisibility::requirements($brand, 2), fn ($r) => $r['required']))],
                'state' => \App\Services\Brands\BrandVisibility::contentState($brand),
                'reason' => \App\Services\Brands\BrandVisibility::reason($brand),
                'message' => \App\Services\Brands\BrandVisibility::message($brand),
                'grandfathered' => (int) $brand->gate_version < \App\Services\Brands\BrandVisibility::GATE_V2,
                'canPublish' => $brand->published_at === null && \App\Services\Brands\BrandVisibility::passes($brand),
            ],
        ]);
    }
}
