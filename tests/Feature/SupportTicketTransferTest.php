<?php

namespace Tests\Feature;

use App\Livewire\Admin\SupportQueue;
use App\Models\SupportConversation;
use App\Models\User;
use App\Notifications\TicketTransferredNotification;
use App\Support\StaffPresence;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Single-owner rule ("built with Wisdom", owner request): only one staff
 * member owns a support ticket at a time; handing it to someone else for
 * more attention is an explicit transfer, not a free-for-all reassignment.
 */
class SupportTicketTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function staff(string $name = 'Staffer'): User
    {
        $s = User::factory()->create(['name' => $name]);
        $s->assignRole('staff');
        $s->givePermissionTo('tickets.manage');
        $s->forceFill(['two_factor_secret' => encrypt('S'), 'two_factor_confirmed_at' => now()])->save();

        return $s;
    }

    public function test_a_second_staff_member_cannot_steal_an_already_owned_ticket(): void
    {
        $owner = $this->staff('Owner');
        $other = $this->staff('Other');
        $customer = User::factory()->create();
        $conv = SupportConversation::create(['user_id' => $customer->id, 'escalated' => true, 'assigned_to' => $owner->id, 'status' => 'assigned']);

        Livewire::actingAs($other)->test(SupportQueue::class)
            ->call('assignToMe', $conv->id);

        $this->assertSame($owner->id, $conv->fresh()->assigned_to);
    }

    public function test_an_admin_can_still_take_over_an_owned_ticket(): void
    {
        $owner = $this->staff('Owner');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->forceFill(['two_factor_secret' => encrypt('S'), 'two_factor_confirmed_at' => now()])->save();
        $customer = User::factory()->create();
        $conv = SupportConversation::create(['user_id' => $customer->id, 'escalated' => true, 'assigned_to' => $owner->id, 'status' => 'assigned']);

        Livewire::actingAs($admin)->test(SupportQueue::class)
            ->call('assignToMe', $conv->id);

        $this->assertSame($admin->id, $conv->fresh()->assigned_to);
    }

    public function test_the_owner_can_transfer_the_ticket_to_a_fellow_staff_member(): void
    {
        Notification::fake();
        $owner = $this->staff('Owner');
        $recipient = $this->staff('Recipient');
        $customer = User::factory()->create();
        $conv = SupportConversation::create(['user_id' => $customer->id, 'escalated' => true, 'assigned_to' => $owner->id, 'status' => 'assigned']);

        Livewire::actingAs($owner)->test(SupportQueue::class)
            ->call('transfer', $conv->id, $recipient->id);

        $this->assertSame($recipient->id, $conv->fresh()->assigned_to);
        Notification::assertSentTo($recipient, TicketTransferredNotification::class);
    }

    public function test_a_bystander_staff_member_cannot_transfer_someone_elses_ticket(): void
    {
        $owner = $this->staff('Owner');
        $bystander = $this->staff('Bystander');
        $recipient = $this->staff('Recipient');
        $customer = User::factory()->create();
        $conv = SupportConversation::create(['user_id' => $customer->id, 'escalated' => true, 'assigned_to' => $owner->id, 'status' => 'assigned']);

        Livewire::actingAs($bystander)->test(SupportQueue::class)
            ->call('transfer', $conv->id, $recipient->id);

        $this->assertSame($owner->id, $conv->fresh()->assigned_to);
    }

    public function test_a_ticket_cannot_be_transferred_to_someone_who_cannot_manage_tickets(): void
    {
        $owner = $this->staff('Owner');
        $regularUser = User::factory()->create();
        $regularUser->assignRole('user');
        $customer = User::factory()->create();
        $conv = SupportConversation::create(['user_id' => $customer->id, 'escalated' => true, 'assigned_to' => $owner->id, 'status' => 'assigned']);

        Livewire::actingAs($owner)->test(SupportQueue::class)
            ->call('transfer', $conv->id, $regularUser->id);

        $this->assertSame($owner->id, $conv->fresh()->assigned_to);
    }

    public function test_online_agents_list_shows_others_but_excludes_the_viewer(): void
    {
        $viewer = $this->staff('Viewer');
        $other = $this->staff('Other');
        StaffPresence::heartbeat($viewer);
        StaffPresence::heartbeat($other);

        Livewire::actingAs($viewer)->test(SupportQueue::class)
            ->assertViewHas('onlineAgents', function ($agents) use ($viewer, $other) {
                return $agents->pluck('id')->contains($other->id)
                    && ! $agents->pluck('id')->contains($viewer->id);
            });
    }
}
