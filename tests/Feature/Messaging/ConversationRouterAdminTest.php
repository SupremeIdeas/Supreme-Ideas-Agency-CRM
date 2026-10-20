<?php

namespace Tests\Feature\Messaging;

use App\Livewire\Admin\ConversationRouterSimulator;
use App\Models\Setting;
use App\Models\User;
use App\Services\N2N\Identity\NaaraId;
use App\Support\Verify\Checks\ConversationRouterMatrixSound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConversationRouterAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $a = User::factory()->create();
        $a->assignRole('admin');

        return $a;
    }

    public function test_simulator_shows_n2n_on_credit_and_never_the_wallet_for_a_naara_id(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create();
        $id = NaaraId::for($other);
        Livewire::actingAs($admin)->test(ConversationRouterSimulator::class)
            ->set('actorEmail', $admin->email)->set('target', $id)->set('intent', 'message')->call('simulate')
            ->assertSet('error', null)
            ->assertSet('plan.channel', 'n2n')->assertSet('plan.payer', 'credit')
            ->set('viaSms', true)->call('simulate')
            ->assertSet('plan.channel', 'none')->assertSet('plan.blockers', ['naara_id_never_sms']);
    }

    public function test_rules_are_cleaned_and_admin_only(): void
    {
        $admin = $this->admin();
        Livewire::actingAs($admin)->test(ConversationRouterSimulator::class)->set('denied', 'ng, xx, 123, gh')->call('saveRules')->assertSet('denied', 'NG,XX,GH');
        $this->assertSame('NG,XX,GH', Setting::getValue('messaging.router.denied_countries'));
        $u = User::factory()->create();
        $u->assignRole('user');
        Livewire::actingAs($u)->test(ConversationRouterSimulator::class)->assertStatus(404);
    }

    public function test_verify_check_passes_on_this_install(): void
    {
        $this->assertSame('pass', (new ConversationRouterMatrixSound)->run()->status);
    }
}
