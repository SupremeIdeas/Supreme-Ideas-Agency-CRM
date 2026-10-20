<?php

namespace App\Livewire;

use App\Models\ChannelConnection;
use App\Models\WalletGroup;
use App\Services\Inbox\ChannelConnector;
use App\Services\Inbox\ChannelSendException;
use App\Services\Inbox\TelegramBotClient;
use App\Services\Permissions\WorkspaceRoleService;
use App\Services\Wallet\WalletGroupService;
use App\Support\Business\SetupWizard;
use App\Support\Business\WorkspaceAdminAccess;
use App\Support\Permissions\BusinessRole;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * NaaraBusiness Suite Setup Wizard (MASTER-ONLY): a resumable, step-by-step pop-up launched from the workspace overview. Every step saves as the person moves on, shows what
 * it enables and where to get what it needs, and tests the connection in plain words. Steps follow the owner's Naara Pro plan: a locked step says what unlocks it. Channel secrets are
 * write-only (encrypted by the connector, wiped from the page after a connect) and never logged.
 */
class BusinessSetupWizard extends Component
{
    public bool $open = false;

    public string $step = 'profile';

    public string $name = '';

    public string $businessEmail = '';

    public string $telegramToken = '';

    public string $waNumberId = '';

    public string $waToken = '';

    public string $waSecret = '';

    public string $igAccountId = '';

    public string $igToken = '';

    public string $igSecret = '';

    public string $inviteEmail = '';

    public string $inviteRole = BusinessRole::AGENT;

    public ?string $ok = null;

    public ?string $error = null;

    /** @var list<array{label:string, pass:bool, detail:string}> */
    public array $selfTest = [];

    private function group(): ?WalletGroup
    {
        return WorkspaceAdminAccess::groupFor(auth()->user());
    }

    private function mustGroup(): WalletGroup
    {
        $g = $this->group();
        abort_unless($g !== null, 404);

        return $g;
    }

    #[On('business-setup-open')]
    public function launch(): void
    {
        $g = $this->mustGroup();
        $this->name = (string) $g->name;
        $this->businessEmail = (string) $g->business_email;
        // Resume where the person left off: the first step that is neither done nor locked.
        $steps = app(SetupWizard::class)->steps($g, auth()->user());
        $next = collect($steps)->first(fn ($s) => in_array($s['status'], [SetupWizard::TODO, SetupWizard::ATTENTION], true));
        $this->step = $next['key'] ?? 'profile';
        $this->reset('ok', 'error', 'selfTest');
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->dispatch('business-setup-changed');
    }

    public function goTo(string $key): void
    {
        $this->step = $key;
        $this->reset('ok', 'error');
    }

    /** @return list<string> the step keys in order */
    private function keys(): array
    {
        return array_column(app(SetupWizard::class)->steps($this->mustGroup(), auth()->user()), 'key');
    }

    public function next(): void
    {
        $keys = $this->keys();
        $i = array_search($this->step, $keys, true);
        if ($i !== false && isset($keys[$i + 1])) {
            $this->goTo($keys[$i + 1]);
        } else {
            $this->goTo('done');
        }
    }

    public function back(): void
    {
        $keys = $this->keys();
        $i = array_search($this->step, $keys, true);
        $this->goTo($this->step === 'done' ? (end($keys) ?: 'profile') : ($keys[max(0, ($i === false ? 0 : $i) - 1)] ?? 'profile'));
    }

    public function saveProfile(): void
    {
        $this->reset('ok', 'error');
        $g = $this->mustGroup();
        $this->validate(['name' => 'required|string|max:255', 'businessEmail' => 'nullable|email|max:255']);
        $emailChanged = mb_strtolower((string) $g->business_email) !== mb_strtolower($this->businessEmail);
        $g->forceFill([
            'name' => $this->name,
            'business_email' => $this->businessEmail !== '' ? $this->businessEmail : null,
            'business_email_verified_at' => $emailChanged ? null : $g->business_email_verified_at,
        ])->save();
        if ($emailChanged && $g->needsBusinessEmailVerification()) {
            \Illuminate\Support\Facades\Notification::route('mail', $g->business_email)->notify(new \App\Notifications\BusinessEmailVerificationNotification($g->id));
        }
        $this->ok = 'Saved.';
        $this->dispatch('business-setup-changed');
        $this->next();
    }

    public function connectTelegram(ChannelConnector $connector): void
    {
        $this->attempt(fn () => $connector->connectTelegram($this->mustGroup(), $this->telegramToken), 'Connected. Telegram accepted the bot and now sends its messages here.', ['telegramToken']);
    }

    public function connectWhatsApp(ChannelConnector $connector): void
    {
        $this->attempt(fn () => $connector->connectWhatsApp($this->mustGroup(), $this->waNumberId, $this->waToken, $this->waSecret), 'Connected. Now paste the callback address and verify token into Meta (see the Channels page).', ['waToken', 'waSecret']);
    }

    public function connectInstagram(ChannelConnector $connector): void
    {
        $this->attempt(fn () => $connector->connectInstagram($this->mustGroup(), $this->igAccountId, $this->igToken, $this->igSecret), 'Connected. Now paste the callback address and verify token into Meta (see the Channels page).', ['igToken', 'igSecret']);
    }

    /** Test an already-connected Telegram bot again, in plain words. Meta channels cannot be re-tested without their secrets, so they show their last recorded status. */
    public function retest(int $id, TelegramBotClient $telegram): void
    {
        $this->reset('ok', 'error');
        $c = ChannelConnection::where('wallet_group_id', $this->mustGroup()->id)->find($id);
        if (! $c) {
            return;
        }
        if ($c->channel !== ChannelConnection::TELEGRAM) {
            $this->ok = $c->status === ChannelConnection::ACTIVE ? 'This channel is active and has no recorded error.' : null;
            $this->error = $c->status === ChannelConnection::ACTIVE ? null : ($c->last_error ?: 'This channel is not active. Reconnect it with fresh details.');

            return;
        }
        $me = $telegram->me((string) $c->secret('bot_token'));
        if ($me) {
            $c->forceFill(['status' => ChannelConnection::ACTIVE, 'last_error' => null])->save();
            $this->ok = 'Telegram answered: the bot @'.($me['username'] ?? 'bot').' is working.';
        } else {
            $c->forceFill(['status' => ChannelConnection::ERROR, 'last_error' => 'Telegram did not accept the bot token any more.'])->save();
            $this->error = 'Telegram did not accept the saved token. Reconnect the bot with a fresh token from BotFather.';
        }
        $this->dispatch('business-setup-changed');
    }

    public function inviteSeat(WalletGroupService $groups, WorkspaceRoleService $roles): void
    {
        $this->reset('ok', 'error');
        $g = $this->mustGroup();
        $this->validate(['inviteEmail' => 'required|email', 'inviteRole' => 'required|in:'.implode(',', BusinessRole::all())]);
        try {
            $member = $groups->inviteSeat(auth()->user(), auth()->user(), $this->inviteEmail, $this->inviteRole, null, null);
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }
        if ($member->user_id) {
            $roles->assignRole($g, $member->user, $this->inviteRole);
        }
        $this->reset('inviteEmail');
        $this->ok = 'Invitation sent.';
        $this->dispatch('business-setup-changed');
    }

    /** After setup: check what is switched on really works, and say pass or fail for each. Read-only except for the one Telegram call per bot. */
    public function runSelfTest(TelegramBotClient $telegram): void
    {
        $g = $this->mustGroup();
        $out = [];
        foreach (ChannelConnection::where('wallet_group_id', $g->id)->get() as $c) {
            if ($c->channel === ChannelConnection::TELEGRAM) {
                $ok = (bool) $telegram->me((string) $c->secret('bot_token'));
                $out[] = ['label' => 'Telegram '.$c->label, 'pass' => $ok, 'detail' => $ok ? 'Telegram answered.' : 'Telegram did not accept the saved token.'];
            } else {
                $ok = $c->status === ChannelConnection::ACTIVE;
                $out[] = ['label' => ChannelConnection::label($c->channel).' '.$c->label, 'pass' => $ok, 'detail' => $ok ? 'Active, no recorded error.' : ($c->last_error ?: 'Not active.')];
            }
        }
        foreach (app(SetupWizard::class)->steps($g, auth()->user()) as $s) {
            if (in_array($s['key'], ['call_flows', 'recording', 'ai_voice'], true) && $s['status'] !== SetupWizard::LOCKED) {
                $out[] = ['label' => $s['title'], 'pass' => $s['status'] === SetupWizard::DONE, 'detail' => $s['status'] === SetupWizard::DONE ? $s['detail'] : $s['detail'].' (optional)'];
            }
        }
        $this->selfTest = $out === [] ? [['label' => 'Nothing to test yet', 'pass' => false, 'detail' => 'Connect a channel or switch a feature on first.']] : $out;
        $this->dispatch('business-setup-changed');
    }

    /** @param list<string> $clear fields wiped after a successful connect so a secret never sits in the page */
    private function attempt(\Closure $run, string $success, array $clear): void
    {
        $this->reset('ok', 'error');
        try {
            $run();
        } catch (ChannelSendException $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->reset($clear);
        $this->ok = $success;
        $this->dispatch('business-setup-changed');
    }

    public function render()
    {
        $g = $this->group();
        $wizard = app(SetupWizard::class);
        $steps = $g ? $wizard->steps($g, auth()->user()) : [];

        return view('livewire.business-setup-wizard', [
            'group' => $g,
            'steps' => $steps,
            'percent' => $wizard->percent($steps),
            'current' => collect($steps)->firstWhere('key', $this->step),
            'guides' => (array) config('business_suite_setup'),
            'connections' => $g ? ChannelConnection::where('wallet_group_id', $g->id)->orderBy('channel')->get()->map(fn (ChannelConnection $c) => [
                'id' => $c->id, 'channel' => $c->channel, 'name' => ChannelConnection::label($c->channel), 'label' => $c->label, 'status' => $c->status, 'error' => $c->last_error,
            ]) : collect(),
            'roles' => BusinessRole::all(),
        ]);
    }
}
