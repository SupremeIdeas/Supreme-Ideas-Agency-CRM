<?php

namespace App\Livewire;

use App\Models\User;
use App\Models\WalletGroup;
use App\Models\WalletGroupMember;
use App\Services\Permissions\WorkspaceRoleService;
use App\Support\Business\BusinessSettings;
use App\Services\Wallet\WalletGroupService;
use App\Support\Permissions\BusinessRole;
use App\Support\Workspace;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Business Suite Prompt 13 §3/§4/§5 — the authenticated workspace management
 * surface. Creating a workspace here just calls WalletGroupService with
 * `kind = business` (Prompt 11 §3's own "Invite to shared plan" primitive,
 * generalized, not a second creation flow); switching and inviting seats
 * follow the same rule.
 */
#[Layout('components.layouts.business')]
class Business extends Component
{
    public string $name = '';

    public string $businessEmail = '';

    public string $inviteEmail = '';

    public string $inviteRole = BusinessRole::AGENT;

    public string $inviteCapUsd = '';

    public string $inviteCapNgn = '';

    public ?string $error = null;

    public function mount(): void
    {
        $this->name = auth()->user()->name ? auth()->user()->name."'s business workspace" : '';
    }

    public function group(): ?WalletGroup
    {
        return auth()->user()->ownedWalletGroups()->where('kind', WalletGroup::KIND_BUSINESS)->first();
    }

    public function createWorkspace(WalletGroupService $groups): void
    {
        $this->error = null;
        $this->validate([
            'name' => 'required|string|max:255',
            'businessEmail' => 'nullable|email',
        ]);

        if (! $this->group() && ! BusinessSettings::workspaceCreationOpen()) {
            $this->error = 'New workspaces are paused for now. Please check back soon.';

            return;
        }

        if (! $this->group() && ! app(\App\Services\Business\BusinessAccess::class)->mayCreateWorkspace(auth()->user())) {
            $this->error = 'A workspace comes with a NaaraBusiness Suite plan. Pick a plan, then come back and create yours.';

            return;
        }

        $group = $groups->findOrCreateOwnGroup(auth()->user(), WalletGroup::KIND_BUSINESS);
        $group->forceFill([
            'name' => $this->name,
            'business_email' => $this->businessEmail ?: null,
        ])->save();

        if ($group->needsBusinessEmailVerification()) {
            \Illuminate\Support\Facades\Notification::route('mail', $group->business_email)
                ->notify(new \App\Notifications\BusinessEmailVerificationNotification($group->id));
        }

        app(WorkspaceRoleService::class)->assignRole($group, auth()->user(), BusinessRole::OWNER);
        Workspace::setActive($group);

        $this->dispatch('nx-toast', type: 'success', message: 'Business workspace created.');
    }

    public function inviteSeat(WalletGroupService $groups, WorkspaceRoleService $roles): void
    {
        $this->error = null;
        $this->validate([
            'inviteEmail' => 'required|email',
            'inviteRole' => 'required|in:'.implode(',', BusinessRole::all()),
            'inviteCapUsd' => 'nullable|numeric|min:0.01',
            'inviteCapNgn' => 'nullable|numeric|min:0.01',
        ]);

        $group = $this->group();
        if (! $group) {
            return;
        }

        try {
            $member = $groups->inviteSeat(
                auth()->user(),
                auth()->user(),
                $this->inviteEmail,
                $this->inviteRole,
                $this->inviteCapUsd !== '' ? (float) $this->inviteCapUsd : null,
                $this->inviteCapNgn !== '' ? (float) $this->inviteCapNgn : null,
            );
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        // An existing user already has an account to assign the role to now;
        // a not-yet-registered invite gets its role granted once
        // WalletGroupService::attachRegisteredInvite() links them (Prompt 13
        // §5.2) — see the accept step below.
        if ($member->user_id) {
            $roles->assignRole($group, $member->user, $this->inviteRole);
        }

        $this->reset('inviteEmail', 'inviteCapUsd', 'inviteCapNgn');
        $this->dispatch('nx-toast', type: 'success', message: 'Seat invited.');
    }

    public function removeSeat(WalletGroupService $groups, WorkspaceRoleService $roles, int $memberId): void
    {
        $group = $this->group();
        $member = $group?->members()->find($memberId);
        if (! $group || ! $member) {
            return;
        }

        if ($member->user_id) {
            $roles->removeRole($group, $member->user);
        }
        $groups->remove(auth()->user(), $member);
        $this->dispatch('nx-toast', type: 'success', message: 'Seat removed.');
    }

    public function switchTo(?int $walletGroupId): void
    {
        $group = $walletGroupId ? WalletGroup::find($walletGroupId) : null;
        if ($walletGroupId && (! $group || ! Workspace::belongsTo(auth()->user(), $group))) {
            return;
        }

        Workspace::setActive($group);
    }

    /** The Setup Wizard saved something: re-read so the Finish setup card and the status chips stay live. */
    #[On('business-setup-changed')]
    public function refreshSetup(): void
    {
    }

    public function render()
    {
        $group = $this->group();

        return view('livewire.business', [
            'group' => $group,
            'members' => $group?->members()->with(['user', 'invitedBy'])->get() ?? collect(),
            'switchable' => Workspace::switchableFor(auth()->user()),
            'active' => Workspace::active(),
            'roles' => BusinessRole::all(),
            'aiLive' => BusinessSettings::module('ai_voice') && $group && \App\Support\Business\WorkspaceAdminAccess::can($group, auth()->user()),
            'inboxLive' => BusinessSettings::module('inbox') && $group && \App\Support\Business\WorkspaceAdminAccess::can($group, auth()->user()),
            'recordingLive' => BusinessSettings::module('recording') && $group && \App\Support\Business\WorkspaceAdminAccess::can($group, auth()->user()),
        ]);
    }
}
