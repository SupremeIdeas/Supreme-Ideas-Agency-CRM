<?php

namespace Tests\Feature\BusinessSuite;

use App\Models\User;
use App\Models\WalletGroup;
use App\Models\WalletGroupMember;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Business Suite Prompt 13 §4. Switching is session-scoped ONLY — it never
 * re-authenticates and never touches another session/device (§4.2).
 */
class WorkspaceSwitcherTest extends TestCase
{
    use \Tests\Concerns\EnablesBusinessSuite;

    use RefreshDatabase;

    private function activeUser(array $attrs = []): User
    {
        return User::factory()->create([...$attrs, 'is_active' => true]);
    }

    public function test_switching_to_an_owned_group_sets_session(): void
    {
        $owner = $this->activeUser();
        $group = WalletGroup::create(['owner_user_id' => $owner->id, 'name' => 'Acme', 'kind' => 'business']);

        $this->actingAs($owner)->post(route('workspace.switch', $group))
            ->assertRedirect()
            ->assertSessionHas('active_wallet_group_id', $group->id);
    }

    public function test_switching_to_personal_clears_session(): void
    {
        $owner = $this->activeUser();
        $group = WalletGroup::create(['owner_user_id' => $owner->id, 'name' => 'Acme', 'kind' => 'business']);

        $this->actingAs($owner)
            ->withSession(['active_wallet_group_id' => $group->id])
            ->post(route('workspace.switch'))
            ->assertRedirect()
            ->assertSessionMissing('active_wallet_group_id');
    }

    public function test_a_non_member_cannot_switch_into_someone_elses_group(): void
    {
        $owner = $this->activeUser();
        $stranger = $this->activeUser();
        $group = WalletGroup::create(['owner_user_id' => $owner->id, 'name' => 'Acme', 'kind' => 'business']);

        $this->actingAs($stranger)->post(route('workspace.switch', $group))->assertForbidden();
    }

    public function test_an_accepted_member_can_switch_but_a_pending_invite_cannot(): void
    {
        $owner = $this->activeUser();
        $group = WalletGroup::create(['owner_user_id' => $owner->id, 'name' => 'Acme', 'kind' => 'business']);
        $accepted = $this->activeUser();
        $pending = $this->activeUser();
        WalletGroupMember::create(['wallet_group_id' => $group->id, 'user_id' => $accepted->id, 'invited_at' => now(), 'accepted_at' => now()]);
        WalletGroupMember::create(['wallet_group_id' => $group->id, 'user_id' => $pending->id, 'invited_at' => now(), 'accepted_at' => null]);

        $this->actingAs($accepted)->post(route('workspace.switch', $group))
            ->assertRedirect()
            ->assertSessionHas('active_wallet_group_id', $group->id);

        $this->actingAs($pending)->post(route('workspace.switch', $group))->assertForbidden();
    }

    public function test_active_resolves_to_null_and_clears_session_if_membership_was_since_revoked(): void
    {
        $owner = $this->activeUser();
        $group = WalletGroup::create(['owner_user_id' => $owner->id, 'name' => 'Acme', 'kind' => 'business']);
        $member = $this->activeUser();
        $row = WalletGroupMember::create(['wallet_group_id' => $group->id, 'user_id' => $member->id, 'invited_at' => now(), 'accepted_at' => now()]);

        $this->actingAs($member);
        session(['active_wallet_group_id' => $group->id]);
        $row->delete();

        $this->assertNull(Workspace::active());
        $this->assertNull(session('active_wallet_group_id'));
    }

    public function test_switchable_for_includes_owned_and_accepted_but_not_pending_groups(): void
    {
        $user = $this->activeUser();
        $owned = WalletGroup::create(['owner_user_id' => $user->id, 'name' => 'Mine', 'kind' => 'business']);

        $otherOwner = $this->activeUser();
        $acceptedGroup = WalletGroup::create(['owner_user_id' => $otherOwner->id, 'name' => 'Theirs (accepted)', 'kind' => 'business']);
        WalletGroupMember::create(['wallet_group_id' => $acceptedGroup->id, 'user_id' => $user->id, 'invited_at' => now(), 'accepted_at' => now()]);

        $pendingGroup = WalletGroup::create(['owner_user_id' => $otherOwner->id, 'name' => 'Theirs (pending)', 'kind' => 'business']);
        WalletGroupMember::create(['wallet_group_id' => $pendingGroup->id, 'user_id' => $user->id, 'invited_at' => now(), 'accepted_at' => null]);

        $switchable = Workspace::switchableFor($user)->pluck('id');

        $this->assertTrue($switchable->contains($owned->id));
        $this->assertTrue($switchable->contains($acceptedGroup->id));
        $this->assertFalse($switchable->contains($pendingGroup->id));
    }
}
