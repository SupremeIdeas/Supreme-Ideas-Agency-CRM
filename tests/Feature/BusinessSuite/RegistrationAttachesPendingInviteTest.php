<?php

namespace Tests\Feature\BusinessSuite;

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use App\Models\WalletGroup;
use App\Models\WalletGroupMember;
use App\Services\Wallet\WalletGroupService;
use App\Support\Permissions\BusinessRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Business Suite Prompt 13 §5.2 end-to-end through the REAL Fortify action —
 * "the same Fortify registration endpoint as any other signup," not a
 * parallel creation path.
 */
class RegistrationAttachesPendingInviteTest extends TestCase
{
    use \Tests\Concerns\EnablesBusinessSuite;

    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\BusinessRoleSeeder']);
    }

    public function test_registering_with_a_previously_invited_email_lands_them_in_the_workspace(): void
    {
        $owner = User::factory()->create();
        $group = WalletGroup::create(['owner_user_id' => $owner->id, 'name' => 'Acme', 'kind' => 'business']);
        app(WalletGroupService::class)->inviteSeat($owner, $owner, 'joins-later@example.com', BusinessRole::AGENT, null, null);

        $newUser = app(CreateNewUser::class)->create([
            'name' => 'New Hire',
            'email' => 'joins-later@example.com',
            'password' => 'Correct-Horse-1',
            'password_confirmation' => 'Correct-Horse-1',
        ]);

        $member = WalletGroupMember::where('wallet_group_id', $group->id)->first();
        $this->assertSame($newUser->id, $member->user_id);
        $this->assertTrue($member->isActive());
    }

    public function test_registering_with_an_email_nobody_invited_is_completely_unaffected(): void
    {
        $newUser = app(CreateNewUser::class)->create([
            'name' => 'Ordinary Signup',
            'email' => 'nobody-invited-this@example.com',
            'password' => 'Correct-Horse-1',
            'password_confirmation' => 'Correct-Horse-1',
        ]);

        $this->assertDatabaseHas('users', ['id' => $newUser->id]);
        $this->assertDatabaseCount('wallet_group_members', 0);
    }
}
