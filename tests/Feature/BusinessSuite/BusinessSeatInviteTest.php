<?php

namespace Tests\Feature\BusinessSuite;

use App\Models\User;
use App\Models\WalletGroup;
use App\Models\WalletGroupMember;
use App\Notifications\WalletGroupInvitedNotification;
use App\Services\Wallet\WalletGroupService;
use App\Support\Permissions\BusinessRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Business Suite Prompt 13 §1/§5. `findOrCreateOwnGroup` now takes `kind`
 * (family vs business never collide for the same owner) and `inviteSeat`
 * extends Prompt 11 §3's invite primitive to accept an email with no
 * NaaraSim account yet — closing the one gap Prompt 13 §5.2 depends on that
 * the family-plan-only `invite()` doesn't cover.
 */
class BusinessSeatInviteTest extends TestCase
{
    use \Tests\Concerns\EnablesBusinessSuite;

    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\BusinessRoleSeeder']);
    }

    private function svc(): WalletGroupService
    {
        return app(WalletGroupService::class);
    }

    public function test_a_family_and_business_group_for_the_same_owner_never_collide(): void
    {
        $owner = User::factory()->create();

        $family = $this->svc()->findOrCreateOwnGroup($owner, WalletGroup::KIND_FAMILY);
        $business = $this->svc()->findOrCreateOwnGroup($owner, WalletGroup::KIND_BUSINESS);

        $this->assertNotSame($family->id, $business->id);
        $this->assertSame('family', $family->kind);
        $this->assertSame('business', $business->kind);
        $this->assertSame(2, WalletGroup::where('owner_user_id', $owner->id)->count());
    }

    public function test_inviting_an_existing_user_by_email_notifies_them_immediately(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $invitee = User::factory()->create();

        $member = $this->svc()->inviteSeat($owner, $owner, $invitee->email, BusinessRole::AGENT, null, null);

        $this->assertSame($invitee->id, $member->user_id);
        $this->assertNull($member->invited_email);
        $this->assertSame('agent', $member->role);
        $this->assertSame(WalletGroupMember::STATUS_INVITED, $member->status);
        Notification::assertSentTo($invitee, WalletGroupInvitedNotification::class);
    }

    public function test_inviting_an_email_with_no_account_creates_a_pending_row_with_no_user_id(): void
    {
        Notification::fake();
        $owner = User::factory()->create();

        $member = $this->svc()->inviteSeat($owner, $owner, 'future-hire@example.com', BusinessRole::VIEWER, null, null);

        $this->assertNull($member->user_id);
        $this->assertSame('future-hire@example.com', $member->invited_email);
        $this->assertTrue($member->awaitingRegistration());
        Notification::assertNothingSent();
    }

    public function test_registering_with_an_invited_email_attaches_and_activates_the_pending_seat(): void
    {
        $owner = User::factory()->create();
        $pending = $this->svc()->inviteSeat($owner, $owner, 'new-hire@example.com', BusinessRole::AGENT, null, null);

        $newUser = User::factory()->create(['email' => 'new-hire@example.com']);
        $this->svc()->attachRegisteredInvite($newUser);

        $pending->refresh();
        $this->assertSame($newUser->id, $pending->user_id);
        $this->assertNull($pending->invited_email);
        $this->assertTrue($pending->isActive());
        $this->assertSame(WalletGroupMember::STATUS_ACTIVE, $pending->status);
        $this->assertTrue(app(\App\Services\Permissions\WorkspaceRoleService::class)
            ->hasPermission($pending->walletGroup, $newUser, \App\Support\Permissions\Permissions::USE_WORKSPACE));
    }

    public function test_registering_with_an_unrelated_email_does_not_touch_other_pending_invites(): void
    {
        $owner = User::factory()->create();
        $this->svc()->inviteSeat($owner, $owner, 'someone-else@example.com', BusinessRole::AGENT, null, null);

        $unrelatedUser = User::factory()->create(['email' => 'unrelated@example.com']);
        $this->svc()->attachRegisteredInvite($unrelatedUser);

        $this->assertDatabaseHas('wallet_group_members', [
            'invited_email' => 'someone-else@example.com', 'user_id' => null,
        ]);
    }

    public function test_re_inviting_the_same_unregistered_email_updates_rather_than_duplicates(): void
    {
        $owner = User::factory()->create();

        $this->svc()->inviteSeat($owner, $owner, 'repeat@example.com', BusinessRole::VIEWER, null, null);
        $this->svc()->inviteSeat($owner, $owner, 'repeat@example.com', BusinessRole::ADMIN, null, null);

        $this->assertSame(1, WalletGroupMember::where('invited_email', 'repeat@example.com')->count());
        $this->assertSame('admin', WalletGroupMember::where('invited_email', 'repeat@example.com')->first()->role);
    }

    public function test_cannot_invite_your_own_email(): void
    {
        $owner = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->svc()->inviteSeat($owner, $owner, $owner->email, BusinessRole::ADMIN, null, null);
    }
}
