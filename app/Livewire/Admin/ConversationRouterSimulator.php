<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Services\Messaging\ConversationRouter;
use App\Services\Messaging\ConversationRouterFacts;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Admin > Who pays? (Brand-Growth Phase 9, MASTER-ONLY): the router's rules (country allow/deny) and a simulator that shows the route, price, payer and blockers for any person, target and intent. It sends nothing and charges nothing. */
#[Layout('components.layouts.admin')]
class ConversationRouterSimulator extends Component
{
    public string $actorEmail = '';

    public string $target = '';

    public string $intent = 'message';

    public bool $viaSms = false;

    public bool $media = false;

    public ?array $plan = null;

    public ?string $error = null;

    public string $denied = '';

    public string $allowed = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 404);
        $this->actorEmail = (string) Auth::user()->email;
        $this->denied = (string) Setting::getValue('messaging.router.denied_countries', '');
        $this->allowed = (string) Setting::getValue('messaging.router.allowed_countries', '');
    }

    public function simulate(ConversationRouter $router): void
    {
        $this->guard();
        $this->validate(['actorEmail' => ['required', 'email'], 'target' => ['required', 'string', 'max:64'], 'intent' => ['required', 'in:message,call']]);
        $this->error = null;
        $this->plan = null;
        $actor = User::where('email', $this->actorEmail)->first();
        if (! $actor) {
            $this->error = 'No account with that email.';

            return;
        }
        $this->plan = $router->resolve($actor, $this->target, $this->intent, ['via_sms' => $this->viaSms, 'via_pstn' => $this->viaSms, 'media' => $this->media])->toArray();
        $this->plan['balances'] = ['credit' => app(ConversationRouterFacts::class)->creditBalance($actor), 'wallet_usd' => app(ConversationRouterFacts::class)->walletUsd($actor)];
        $this->plan['blocker_text'] = array_map(fn ($c) => ConversationRouter::BLOCKER_TEXT[$c] ?? $c, $this->plan['blockers']);
        \App\Support\Auditor::log('router.simulated', 'User', $actor->id, ['intent' => $this->intent]);
    }

    public function saveRules(): void
    {
        $this->guard();
        $clean = fn (string $v) => implode(',', array_values(array_unique(array_filter(array_map(fn ($c) => preg_match('/^[A-Z]{2}$/', $c) ? $c : null, array_map(fn ($x) => strtoupper(trim($x)), explode(',', $v)))))));
        Setting::setValue('messaging.router.denied_countries', $clean($this->denied), 'messaging');
        Setting::setValue('messaging.router.allowed_countries', $clean($this->allowed), 'messaging');
        $this->denied = $clean($this->denied);
        $this->allowed = $clean($this->allowed);
        \App\Support\Auditor::log('router.rules_saved');
        $this->dispatch('nx-toast', type: 'success', message: 'Saved.');
    }

    private function guard(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function render()
    {
        return view('livewire.admin.conversation-router-simulator');
    }
}
