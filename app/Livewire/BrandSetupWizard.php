<?php

namespace App\Livewire;

use App\Models\BrandPartner;
use App\Models\BrandPartnerHandle;
use App\Models\BrandPartnerVideo;
use App\Services\Brands\BrandSubscriptionService;
use App\Services\Brands\BrandVisibility;
use App\Support\BrandCategories;
use App\Support\BrandHandleFormat;
use App\Support\MediaStorage;
use App\Support\SocialPlatforms;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Brand listing setup wizard: a resumable pop-up on the brand dashboard that walks a business through identity, look, social handles, an optional video, its
 * Business Card address and publishing, one short step at a time. Every step saves as the person moves on, reuses the same rules as the dashboard forms (plan
 * caps, URL format, the publish gate) and never touches money. It opens itself once for a brand that is not published yet, and "Continue setup" resumes it.
 */
class BrandSetupWizard extends Component
{
    use WithFileUploads;

    public const SEEN = 'brand_setup_wizard_seen';

    public bool $open = false;

    public string $step = 'identity';

    public string $brandName = '';

    public string $category = '';

    public string $description = '';

    public string $color = '#0A6E6E';

    public $logoImage = null;

    public $heroImage = null;

    /** @var array{platform:string, handle_label:string, handle_url:string} */
    public array $newHandle = ['platform' => 'instagram', 'handle_label' => '', 'handle_url' => ''];

    public string $videoUrl = '';

    public string $videoPlatform = 'youtube';

    public string $cardSlug = '';

    public string $linkLabel = '';

    public string $linkUrl = '';

    /** @var list<string> */
    public array $linkProblems = [];

    /** The plan the person has picked on the plan step (for a trial: the plan their wallet is charged for when it ends). */
    public ?int $pickPlanId = null;

    public bool $trialAccept = false;

    public ?string $ok = null;

    public ?string $error = null;

    private function brand(): BrandPartner
    {
        return BrandPartner::with(['plan', 'subscription', 'handles', 'videos'])->where('owner_user_id', auth()->id())->firstOrFail();
    }

    public function mount(): void
    {
        $brand = BrandPartner::where('owner_user_id', auth()->id())->first();
        if (! $brand) {
            return;
        }
        $this->fillFrom($brand);
        // First visit with an unpublished listing: greet with the wizard once per session (a dismissal is remembered).
        if ($brand->published_at === null && ! session()->get(self::SEEN)) {
            $this->launch();
        }
    }

    private function fillFrom(BrandPartner $b): void
    {
        $this->brandName = (string) $b->brand_name;
        $this->category = (string) $b->category;
        $this->description = (string) $b->short_description;
        $this->color = $b->background_color ?: '#0A6E6E';
        $this->cardSlug = (string) ($b->card_slug ?? '');
    }

    /** @return list<array{key:string,title:string,optional:bool,done:bool,art:string,enables:string}> */
    public function steps(): array
    {
        $b = $this->brand();
        $handles = $b->handles->where('is_active', true)->count();
        $steps = [
            ['key' => 'identity', 'title' => 'Your brand', 'optional' => false, 'done' => filled($b->brand_name) && filled(trim((string) $b->short_description)), 'art' => 'br-identity',
                'enables' => 'The name, category and one-line description people see first in the directory.'],
            ['key' => 'look', 'title' => 'Logo and cover', 'optional' => false, 'done' => filled($b->logo_path) && (filled($b->hero_image_path) || filled($b->fallback_image)), 'art' => 'br-look',
                'enables' => 'A round logo and a wide cover photo. Listings with both get noticed far more.'],
            ['key' => 'handles', 'title' => 'Social handles', 'optional' => false, 'done' => $handles >= 2, 'art' => 'br-handles',
                'enables' => 'The exact profiles people follow. Each real follow can earn them credit, and counts toward your monthly guarantee.'],
        ];
        if (class_exists(\App\Services\BrandLinks\BrandLinkService::class)) {
            $steps[] = ['key' => 'links', 'title' => 'Website link', 'optional' => true, 'done' => $b->links()->approved()->exists(), 'art' => 'br-links',
                'enables' => 'Your website or booking page on your profile. Every link is checked first so visitors only meet safe pages.'];
        }
        if (($b->plan->video_previews_allowed ?? 0) > 0) {
            $steps[] = ['key' => 'video', 'title' => 'Video preview', 'optional' => true, 'done' => $b->videos->isNotEmpty(), 'art' => 'br-video',
                'enables' => 'A short YouTube or Vimeo clip that plays right on your profile.'];
        }
        if (class_exists(\App\Services\Brands\BusinessCardService::class)) {
            $steps[] = ['key' => 'card', 'title' => 'Business Card', 'optional' => true, 'done' => filled($b->card_slug), 'art' => 'br-card',
                'enables' => 'A readable link and QR code for your printed material and social bios.'];
        }
        if (Route::has('n2n.brand.manage') && class_exists(\App\Livewire\N2N\BrandProfileManage::class)) {
            $steps[] = ['key' => 'chat', 'title' => 'Naara chat profile', 'optional' => true, 'done' => false, 'art' => 'br-chat',
                'enables' => 'How you look inside Naara chat: your albums, links and the card people see when they message you.'];
        }
        if (class_exists(\App\Services\Brands\BrandConnectService::class) && \App\Services\Brands\BrandConnectService::enabled() && Route::has('brand.connections')) {
            $steps[] = ['key' => 'connect', 'title' => 'Connections', 'optional' => true, 'done' => false, 'art' => 'br-connect',
                'enables' => 'Lets followers start a conversation with you, and lets you accept or decline each one.'];
        }
        $sub = $b->subscription;
        $steps[] = ['key' => 'plan', 'title' => 'Your plan', 'optional' => false, 'done' => $sub !== null && in_array($sub->status, ['active', 'trialing'], true), 'art' => 'br-plan',
            'enables' => 'Your plan sets how many handles and videos you can list and what you are guaranteed each month.'];
        $steps[] = ['key' => 'publish', 'title' => 'Publish', 'optional' => false, 'done' => $b->published_at !== null, 'art' => $b->published_at !== null ? 'br-live' : 'br-publish',
            'enables' => 'Puts your listing live. Nothing is public until you press Publish.'];

        return $steps;
    }

    #[On('brand-setup-open')]
    public function launch(): void
    {
        $brand = $this->brand();
        $this->fillFrom($brand);
        $steps = $this->steps();
        $next = collect($steps)->first(fn ($s) => ! $s['done'] && ! $s['optional']) ?? end($steps);
        $this->step = $next['key'];
        $this->reset('ok', 'error');
        $this->open = true;
    }

    public function close(): void
    {
        session()->put(self::SEEN, true);
        $this->open = false;
        $this->dispatch('brand-updated');
    }

    public function goTo(string $key): void
    {
        if (in_array($key, array_column($this->steps(), 'key'), true)) {
            $this->step = $key;
            $this->reset('ok', 'error');
        }
    }

    public function next(): void
    {
        $keys = array_column($this->steps(), 'key');
        $i = array_search($this->step, $keys, true);
        $this->goTo($keys[($i === false ? -1 : $i) + 1] ?? 'publish');
    }

    public function back(): void
    {
        $keys = array_column($this->steps(), 'key');
        $i = array_search($this->step, $keys, true);
        $this->goTo($keys[max(0, ($i === false ? 0 : $i) - 1)]);
    }

    public function saveIdentity(): void
    {
        $this->reset('ok', 'error');
        $this->validate([
            'brandName' => 'required|string|max:120',
            'category' => 'nullable|string|max:80',
            'description' => 'required|string|max:500',
        ], [], ['brandName' => 'brand name']);
        abort_unless($this->category === '' || BrandCategories::isValid($this->category), 422);
        $b = $this->brand();
        $b->fill(['brand_name' => $this->brandName, 'category' => $this->category ?: null, 'short_description' => $this->description])->save();
        app(BrandSubscriptionService::class)->activateIfComplete($b);
        $this->dispatch('brand-updated');
        $this->next();
    }

    public function saveLook(): void
    {
        $this->reset('ok', 'error');
        $this->validate([
            'color' => 'required|regex:/^#[0-9a-fA-F]{6}$/',
            'logoImage' => 'nullable|image|mimes:webp,jpg,jpeg,png|max:1024',
            'heroImage' => 'nullable|image|mimes:webp,jpg,jpeg,png|max:2048',
        ]);
        $b = $this->brand();
        if ($this->logoImage) {
            $b->logo_path = MediaStorage::storePublic($this->logoImage, 'brand-logo');
            $this->logoImage = null;
        }
        if ($this->heroImage) {
            $b->hero_image_path = MediaStorage::storePublic($this->heroImage, 'brand-hero');
            $this->heroImage = null;
        }
        $b->background_color = $this->color;
        $b->save();
        app(BrandSubscriptionService::class)->activateIfComplete($b);
        $this->dispatch('brand-updated');
        $this->next();
    }

    public function addHandle(): void
    {
        $this->reset('ok', 'error');
        $b = $this->brand();
        $limit = (int) ($b->plan->handles_included ?? 1);
        if ($b->handles->count() >= $limit) {
            $this->error = "Your plan allows {$limit} ".Str::plural('handle', $limit).'. Upgrade your plan to add more.';

            return;
        }
        $this->validate([
            'newHandle.platform' => 'required|in:'.implode(',', SocialPlatforms::keys()),
            'newHandle.handle_label' => 'required|string|max:80',
            'newHandle.handle_url' => 'required|url|max:512',
        ], [], ['newHandle.handle_label' => 'label', 'newHandle.handle_url' => 'profile link']);
        if (! BrandHandleFormat::matches($this->newHandle['platform'], $this->newHandle['handle_url'])) {
            $this->addError('newHandle.handle_url', 'That does not look like a valid '.SocialPlatforms::label($this->newHandle['platform']).' link. '.BrandHandleFormat::hint($this->newHandle['platform']));

            return;
        }
        BrandPartnerHandle::create([
            'brand_partner_id' => $b->id, 'platform' => $this->newHandle['platform'], 'handle_label' => $this->newHandle['handle_label'], 'handle_url' => $this->newHandle['handle_url'],
            'credit_reward' => 2, 'verification' => 'self', 'is_active' => true, 'sort_order' => (int) $b->handles()->max('sort_order') + 1,
        ]);
        $this->newHandle = ['platform' => $this->newHandle['platform'], 'handle_label' => '', 'handle_url' => ''];
        app(BrandSubscriptionService::class)->activateIfComplete($this->brand());
        $this->ok = 'Added.';
        $this->dispatch('brand-updated');
    }

    public function removeHandle(int $id): void
    {
        BrandPartnerHandle::where('brand_partner_id', $this->brand()->id)->whereKey($id)->delete();
        $this->dispatch('brand-updated');
    }

    public function addVideo(): void
    {
        $this->reset('ok', 'error');
        $b = $this->brand();
        $limit = (int) ($b->plan->video_previews_allowed ?? 0);
        if ($limit <= 0 || $b->videos->count() >= $limit) {
            $this->error = $limit <= 0 ? 'Your plan does not include video previews.' : "Your plan allows {$limit} video ".Str::plural('preview', $limit).'.';

            return;
        }
        $this->validate(['videoPlatform' => 'required|in:youtube,vimeo', 'videoUrl' => 'required|url|max:512'], [], ['videoUrl' => 'video link']);
        BrandPartnerVideo::create(['brand_partner_id' => $b->id, 'video_url' => $this->videoUrl, 'platform' => $this->videoPlatform, 'sort_order' => (int) $b->videos()->max('sort_order') + 1]);
        $this->videoUrl = '';
        $this->ok = 'Video added.';
        $this->dispatch('brand-updated');
    }

    public function removeVideo(int $id): void
    {
        BrandPartnerVideo::where('brand_partner_id', $this->brand()->id)->whereKey($id)->delete();
        $this->dispatch('brand-updated');
    }

    public function addLink(): void
    {
        abort_unless(class_exists(\App\Services\BrandLinks\BrandLinkService::class), 404);
        $this->reset('ok', 'error', 'linkProblems');
        $this->validate(['linkLabel' => ['required', 'string', 'max:40'], 'linkUrl' => ['required', 'string', 'max:500']], [], ['linkLabel' => 'label', 'linkUrl' => 'link']);
        $key = 'brand-link-add:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->linkProblems = ['You have tried a lot of links in a short time. Please wait a few minutes.'];

            return;
        }
        RateLimiter::hit($key, 600);
        try {
            $res = app(\App\Services\BrandLinks\BrandLinkService::class)->add($this->brand(), $this->linkLabel, $this->linkUrl);
        } catch (\DomainException $e) {
            $this->linkProblems = [$e->getMessage()];

            return;
        }
        $v = $res['verdict'];
        if ($v->status === 'rejected') {
            $this->linkProblems = $v->failures();

            return;
        }
        $this->ok = $v->status === 'approved' ? 'Link added. It now shows on your profile.' : 'Link added. A person will check it before it appears.';
        $this->reset('linkLabel', 'linkUrl');
        $this->dispatch('brand-updated');
    }

    public function removeLink(int $id): void
    {
        $this->brand()->links()->whereKey($id)->delete();
        $this->dispatch('brand-updated');
    }

    // ---- plan step: choose a plan, start the free trial on it, or subscribe -----------------------------------------------------------

    private function planFits(BrandPartner $b, \App\Models\BrandSubscriptionPlan $plan): ?string
    {
        if ($b->handles->count() > $plan->handles_included) {
            return "That plan allows {$plan->handles_included} ".Str::plural('handle', $plan->handles_included).'. Remove '.($b->handles->count() - $plan->handles_included).' on the handles step first.';
        }
        if ($b->videos->count() > $plan->video_previews_allowed) {
            return "That plan allows {$plan->video_previews_allowed} ".Str::plural('video', $plan->video_previews_allowed).'. Remove some on the video step first.';
        }

        return null;
    }

    private function pickedPlan(): ?\App\Models\BrandSubscriptionPlan
    {
        return $this->pickPlanId ? \App\Models\BrandSubscriptionPlan::active()->find($this->pickPlanId) : null;
    }

    public function pickPlan(int $id): void
    {
        $this->pickPlanId = \App\Models\BrandSubscriptionPlan::active()->whereKey($id)->exists() ? $id : null;
        $this->trialAccept = false;
        $this->reset('ok', 'error');
    }

    /** Start the free trial on the picked plan: nothing is charged now, and the wallet is debited for exactly this plan when the trial ends. */
    public function startTrial(): void
    {
        $this->reset('ok', 'error');
        abort_unless(class_exists(\App\Services\Brands\BrandTrialService::class), 404);
        $plan = $this->pickedPlan();
        if (! $plan) {
            $this->error = 'Pick a plan first.';

            return;
        }
        if ($msg = $this->planFits($this->brand(), $plan)) {
            $this->error = $msg;

            return;
        }
        try {
            app(\App\Services\Brands\BrandTrialService::class)->start(auth()->user(), $plan, $this->trialAccept);
        } catch (\DomainException $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->trialAccept = false;
        $this->ok = 'Your free trial has started on '.$plan->name.'.';
        $this->dispatch('brand-updated');
    }

    /** Subscribe now (first month charged from the wallet, atomically). */
    public function subscribeNow(): void
    {
        $this->reset('ok', 'error');
        $plan = $this->pickedPlan();
        if (! $plan) {
            $this->error = 'Pick a plan first.';

            return;
        }
        if ($msg = $this->planFits($this->brand(), $plan)) {
            $this->error = $msg;

            return;
        }
        try {
            app(BrandSubscriptionService::class)->subscribe(auth()->user(), $plan);
        } catch (\App\Exceptions\InsufficientBalanceException) {
            $this->error = 'Your wallet is short for the first month ($'.number_format((float) $plan->price_usd_per_month, 2).'). Top up your wallet, then try again.';

            return;
        }
        $this->ok = 'You are on '.$plan->name.'.';
        $this->dispatch('brand-updated');
    }

    /** During a trial: change which plan the wallet is charged for when it ends. */
    public function changeTrialPlan(): void
    {
        $this->reset('ok', 'error');
        abort_unless(class_exists(\App\Services\Brands\BrandTrialService::class), 404);
        $plan = $this->pickedPlan();
        $b = $this->brand();
        $sub = $b->subscription;
        if (! $plan || ! $sub) {
            return;
        }
        if ($msg = $this->planFits($b, $plan)) {
            $this->error = $msg;

            return;
        }
        try {
            app(\App\Services\Brands\BrandTrialService::class)->changePlan($sub, $plan);
        } catch (\DomainException $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->ok = 'When your trial ends, $'.number_format((float) $plan->price_usd_per_month, 2).' for '.$plan->name.' is taken from your wallet.';
        $this->dispatch('brand-updated');
    }

    public function trialAuto(bool $on): void
    {
        $sub = $this->brand()->subscription;
        if ($sub && class_exists(\App\Services\Brands\BrandTrialService::class)) {
            app(\App\Services\Brands\BrandTrialService::class)->setAutoConvert($sub, $on);
            $this->ok = $on ? 'Your plan will start automatically when the trial ends.' : 'Auto-start is off. The trial will end without a charge.';
            $this->dispatch('brand-updated');
        }
    }

    public function saveCard(): void
    {
        abort_unless(class_exists(\App\Services\Brands\BusinessCardService::class), 404);
        $this->reset('ok', 'error');
        try {
            app(\App\Services\Brands\BusinessCardService::class)->setSlug($this->brand(), $this->cardSlug);
            $this->cardSlug = strtolower(trim($this->cardSlug));
            $this->dispatch('brand-updated');
            $this->next();
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function publish(): void
    {
        $this->reset('ok', 'error');
        try {
            $b = BrandVisibility::publish($this->brand());
        } catch (\DomainException $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->ok = BrandVisibility::message($b->fresh(['subscription']));
        session()->put(self::SEEN, true);
        $this->dispatch('brand-updated');
    }

    /** What the plan step needs: the plans, whether a trial is possible and why not, the running trial, and the wallet against the picked plan. */
    private function planInfo(BrandPartner $brand): array
    {
        $plans = \App\Models\BrandSubscriptionPlan::active()->ordered()->get();
        $sub = $brand->subscription;
        $trialing = $sub && $sub->status === 'trialing' && $sub->trial_started_at !== null && $sub->converted_at === null;
        $active = $sub && $sub->status === 'active';
        $trial = null;
        if (class_exists(\App\Services\Brands\BrandTrialService::class) && \App\Services\Brands\BrandTrialService::enabled() && ! $trialing && ! $active) {
            $svc = app(\App\Services\Brands\BrandTrialService::class);
            $code = $svc->blocker(auth()->user());
            $trial = ['days' => \App\Services\Brands\BrandTrialService::days(), 'available' => $code === null, 'why' => $code ? $svc->message($code) : null];
        }
        $picked = $this->pickPlanId ? $plans->firstWhere('id', $this->pickPlanId) : null;
        if (! $picked) {
            $picked = $trialing ? ($sub->convertTo ?? $sub->plan) : ($active ? $sub->plan : null);
        }
        $balance = (float) ($brand->owner?->wallet?->usd_balance ?? 0);
        $current = $trialing ? ($sub->convertTo ?? $sub->plan) : $brand->plan;

        return ['plans' => $plans, 'trialing' => $trialing, 'active' => $active, 'sub' => $sub, 'trial' => $trial, 'picked' => $picked, 'current' => $current, 'balance' => $balance,
            'short' => $picked && $balance < (float) $picked->price_usd_per_month,
            'delta' => $picked ? \App\Support\Business\NaaraProCatalogue::delta(($trialing || $active) && $current && $current->id !== $picked->id ? $current : null, $picked) : []];
    }

    /** @return array{url:string,short:string}|null the public Business Card addresses, once the listing is live */
    private function share(BrandPartner $brand): ?array
    {
        if ($brand->published_at === null || ! class_exists(\App\Services\Brands\BusinessCardService::class)) {
            return null;
        }
        $cards = app(\App\Services\Brands\BusinessCardService::class);
        $url = $cards->url($brand);

        return $url ? ['url' => $url, 'short' => $cards->shortUrl($brand)] : null;
    }

    public function render()
    {
        if (! $this->open) {
            return view('livewire.brand-setup-wizard', ['brand' => null, 'steps' => [], 'current' => null, 'percent' => 0, 'requirements' => [], 'categories' => [], 'platforms' => [], 'links' => collect(), 'share' => null, 'planInfo' => null]);
        }
        $brand = $this->brand();
        $steps = $this->steps();
        $requiredDone = collect($steps)->where('optional', false)->where('done', true)->count();
        $requiredAll = max(1, collect($steps)->where('optional', false)->count());

        return view('livewire.brand-setup-wizard', [
            'brand' => $brand, 'steps' => $steps, 'current' => collect($steps)->firstWhere('key', $this->step),
            'percent' => (int) round($requiredDone / $requiredAll * 100),
            'requirements' => BrandVisibility::requirements($brand, BrandVisibility::GATE_V2),
            'categories' => BrandCategories::all(), 'platforms' => SocialPlatforms::all(),
            'links' => class_exists(\App\Services\BrandLinks\BrandLinkService::class) ? $brand->links()->orderBy('sort_order')->get() : collect(),
            'share' => $this->share($brand),
            'planInfo' => $this->step === 'plan' ? $this->planInfo($brand) : null,
        ]);
    }
}
