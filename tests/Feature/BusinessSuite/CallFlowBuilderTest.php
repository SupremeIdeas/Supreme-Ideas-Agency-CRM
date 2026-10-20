<?php

namespace Tests\Feature\BusinessSuite;

use App\Jobs\SyncVoiceWebhookJob;
use App\Livewire\CallFlowBuilder;
use App\Models\BusinessHoursSchedule;
use App\Models\CallFlow;
use App\Models\CallFlowNode;
use App\Models\RingGroup;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Models\WalletGroup;
use App\Models\WalletGroupMember;
use App\Services\Permissions\WorkspaceRoleService;
use App\Support\Permissions\BusinessRole;
use Database\Seeders\BusinessRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** Business Suite Prompt 14 §5 — the CallFlowBuilder screen: access, steps, validation, going live. */
class CallFlowBuilderTest extends TestCase
{
    use \Tests\Concerns\EnablesBusinessSuite;

    use RefreshDatabase;

    private User $owner;

    private WalletGroup $group;

    private VirtualNumber $number;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(BusinessRoleSeeder::class);
        config(['services.twilio.account_sid' => 'AC_test', 'services.twilio.auth_token' => 'secret_token']);

        $this->owner = User::factory()->create(['is_active' => true]);
        $this->group = WalletGroup::create(['owner_user_id' => $this->owner->id, 'name' => 'Acme', 'kind' => WalletGroup::KIND_BUSINESS]);
        app(WorkspaceRoleService::class)->assignRole($this->group, $this->owner, BusinessRole::OWNER);
        $this->number = VirtualNumber::create([
            'user_id' => $this->owner->id, 'wallet_group_id' => $this->group->id, 'provider' => 'twilio',
            'phone_number' => '+15005550006', 'sid' => 'PN_test', 'status' => 'active',
        ]);
    }

    private function builder(?User $user = null)
    {
        return Livewire::actingAs($user ?? $this->owner)->test(CallFlowBuilder::class);
    }

    private function seatUser(string $role): User
    {
        $u = User::factory()->create(['is_active' => true]);
        WalletGroupMember::create([
            'wallet_group_id' => $this->group->id, 'user_id' => $u->id, 'role' => $role,
            'status' => 'active', 'invited_at' => now(), 'accepted_at' => now(),
        ]);
        app(WorkspaceRoleService::class)->assignRole($this->group, $u, $role);

        return $u;
    }

    public function test_the_page_404s_when_twilio_is_not_active(): void
    {
        config(['services.twilio.account_sid' => '', 'services.twilio.auth_token' => '']);

        $this->actingAs($this->owner)->get(route('business.call-flows'))->assertNotFound();
    }

    public function test_a_user_without_a_business_workspace_gets_a_404(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]))->get(route('business.call-flows'))->assertNotFound();
    }

    public function test_the_owner_and_an_admin_may_build_but_agents_and_viewers_may_not(): void
    {
        $this->actingAs($this->owner)->get(route('business.call-flows'))->assertOk();
        $this->actingAs($this->seatUser(BusinessRole::ADMIN))->withSession(['active_wallet_group_id' => $this->group->id])
            ->get(route('business.call-flows'))->assertOk();

        $this->actingAs($this->seatUser(BusinessRole::AGENT))->withSession(['active_wallet_group_id' => $this->group->id])
            ->get(route('business.call-flows'))->assertNotFound();
        $this->actingAs($this->seatUser(BusinessRole::VIEWER))->withSession(['active_wallet_group_id' => $this->group->id])
            ->get(route('business.call-flows'))->assertNotFound();
    }

    public function test_selecting_a_line_creates_a_paused_flow(): void
    {
        $this->builder()->call('selectNumber', $this->number->id)->assertSet('step', 'team');

        $flow = CallFlow::first();
        $this->assertSame(CallFlow::PAUSED, $flow->status);
        $this->assertSame($this->group->id, $flow->wallet_group_id);
    }

    public function test_a_telnyx_line_is_hidden_and_cannot_be_selected(): void
    {
        $telnyx = VirtualNumber::create([
            'user_id' => $this->owner->id, 'wallet_group_id' => $this->group->id, 'provider' => 'telnyx',
            'phone_number' => '+15005550099', 'sid' => 'TX1', 'status' => 'active',
        ]);

        $this->builder()->assertDontSee('+15005550099')->assertSee('use call flows yet')
            ->call('selectNumber', $telnyx->id)->assertSet('step', 'numbers');

        $this->assertSame(0, CallFlow::count());
    }

    public function test_a_line_from_another_workspace_cannot_be_selected(): void
    {
        $other = WalletGroup::create(['owner_user_id' => User::factory()->create()->id, 'name' => 'Other', 'kind' => WalletGroup::KIND_BUSINESS]);
        $foreign = VirtualNumber::create(['user_id' => $other->owner_user_id, 'wallet_group_id' => $other->id, 'provider' => 'twilio', 'phone_number' => '+15005550077', 'sid' => 'X', 'status' => 'active']);

        $this->builder()->call('selectNumber', $foreign->id)->assertSet('step', 'numbers');

        $this->assertSame(0, CallFlow::count());
    }

    public function test_the_owner_can_bring_their_own_twilio_line_into_the_workspace_but_not_someone_elses(): void
    {
        $mine = VirtualNumber::create(['user_id' => $this->owner->id, 'provider' => 'twilio', 'phone_number' => '+15005550011', 'sid' => 'M1', 'status' => 'active']);
        $theirs = VirtualNumber::create(['user_id' => User::factory()->create()->id, 'provider' => 'twilio', 'phone_number' => '+15005550012', 'sid' => 'M2', 'status' => 'active']);

        $this->builder()->call('assignNumber', $theirs->id)->call('assignNumber', $mine->id);

        $this->assertSame($this->group->id, $mine->fresh()->wallet_group_id);
        $this->assertNull($theirs->fresh()->wallet_group_id);
    }

    public function test_the_first_step_added_becomes_the_start_of_the_flow(): void
    {
        $c = $this->builder()->call('selectNumber', $this->number->id)->call('goto', 'steps');
        $c->set('nodeType', 'voicemail')->set('nodeCfg.greeting_url', 'https://cdn.example.com/hi.mp3')->call('saveNode');

        $flow = CallFlow::first();
        $node = $flow->nodes()->first();
        $this->assertSame('voicemail', $node->type);
        $this->assertSame($node->id, $flow->root_node_id);
        $this->assertSame('https://cdn.example.com/hi.mp3', $node->config['greeting_url']);
    }

    public function test_a_menu_keeps_its_digit_links_and_rejects_links_into_other_flows(): void
    {
        $c = $this->builder()->call('selectNumber', $this->number->id)->call('goto', 'steps');
        $c->set('nodeType', 'forward')->set('nodeCfg.target_number', '+2348011112222')->call('saveNode');
        $good = CallFlowNode::first();

        $c->set('nodeType', 'menu')->set('nodeCfg.prompt_text', 'Press 1')->set('nodeCfg.digits.1', (string) $good->id)->call('saveNode');
        $menu = CallFlowNode::where('type', 'menu')->first();
        $this->assertSame($good->id, $menu->config['digits']['1']);

        // A node id that exists, but in a different flow.
        $other = VirtualNumber::create(['user_id' => $this->owner->id, 'wallet_group_id' => $this->group->id, 'provider' => 'twilio', 'phone_number' => '+15005550013', 'sid' => 'O', 'status' => 'active']);
        $otherFlow = CallFlow::create(['wallet_group_id' => $this->group->id, 'virtual_number_id' => $other->id]);
        $foreign = CallFlowNode::create(['call_flow_id' => $otherFlow->id, 'type' => 'voicemail', 'config' => []]);

        $c->set('nodeType', 'menu')->set('nodeCfg.prompt_text', 'Evil')->set('nodeCfg.digits.2', (string) $foreign->id)->call('saveNode')
            ->assertSee('another step in this flow');
        $this->assertSame(2, CallFlow::first()->nodes()->count(), 'the bad menu must not have been saved');
    }

    public function test_node_config_validation_rejects_bad_numbers_urls_and_groups(): void
    {
        $c = $this->builder()->call('selectNumber', $this->number->id)->call('goto', 'steps');

        $c->set('nodeType', 'forward')->set('nodeCfg.target_number', '08012345678')->call('saveNode')->assertSee('international format');
        $c->set('nodeType', 'voicemail')->set('nodeCfg.greeting_url', 'http://insecure.example.com/a.mp3')->call('saveNode')->assertSee('https://');

        $foreignGroup = RingGroup::create([
            'wallet_group_id' => WalletGroup::create(['owner_user_id' => User::factory()->create()->id, 'name' => 'Z', 'kind' => 'business'])->id,
            'name' => 'Theirs', 'strategy' => 'simultaneous',
        ]);
        $c->set('nodeType', 'queue')->set('nodeCfg.ring_group_id', (string) $foreignGroup->id)->call('saveNode')->assertSee('Choose a ring group');

        $this->assertSame(0, CallFlowNode::count());
    }

    public function test_ring_groups_and_seats_stay_inside_the_workspace(): void
    {
        $agent = $this->seatUser(BusinessRole::AGENT);
        $seat = $this->group->members()->where('user_id', $agent->id)->first();
        $outsider = WalletGroupMember::create([
            'wallet_group_id' => WalletGroup::create(['owner_user_id' => User::factory()->create()->id, 'name' => 'Z', 'kind' => 'business'])->id,
            'user_id' => User::factory()->create()->id, 'status' => 'active', 'invited_at' => now(), 'accepted_at' => now(),
        ]);

        $c = $this->builder()->call('selectNumber', $this->number->id)
            ->set('rgName', 'Sales')->set('rgStrategy', 'sequential')->call('addRingGroup');
        $rg = RingGroup::first();
        $this->assertSame('sequential', $rg->strategy);

        $c->set("seatForm.{$rg->id}.member", (string) $outsider->id)->call('addSeat', $rg->id);
        $this->assertSame(0, $rg->members()->count(), 'a seat from another workspace must be refused');

        $c->set("seatForm.{$rg->id}.member", (string) $seat->id)->set("seatForm.{$rg->id}.priority", '2')->set("seatForm.{$rg->id}.number", '+2348011110001')->call('addSeat', $rg->id);
        $this->assertSame(1, $rg->members()->count());
        $this->assertSame('+2348011110001', $rg->members()->first()->destination_number);
    }

    public function test_business_hours_are_saved_and_reloaded(): void
    {
        $this->builder()->call('selectNumber', $this->number->id)
            ->set('bhTimezone', 'Africa/Lagos')->set('bhClosedDates', '2026-12-25, 2027-01-01')->call('saveSchedule');

        $s = BusinessHoursSchedule::first();
        $this->assertSame('Africa/Lagos', $s->timezone);
        $this->assertCount(5, $s->windows); // Mon–Fri defaults
        $this->assertSame('2026-12-25', $s->holiday_overrides[0]['date']);

        $this->builder()->assertSet('bhClosedDates', '2026-12-25, 2027-01-01');
    }

    public function test_an_invalid_holiday_date_or_timezone_is_refused(): void
    {
        $c = $this->builder()->call('selectNumber', $this->number->id);
        $c->set('bhClosedDates', '2026-02-31')->call('saveSchedule')->assertSee('valid date');
        $c->set('bhClosedDates', '')->set('bhTimezone', 'Mars/Olympus')->call('saveSchedule')->assertHasErrors('bhTimezone');
        $this->assertSame(0, BusinessHoursSchedule::count());
    }

    public function test_going_live_needs_a_start_step_then_queues_the_voice_webhook_sync(): void
    {
        Queue::fake();
        $c = $this->builder()->call('selectNumber', $this->number->id)->call('goto', 'review');

        $c->call('activate')->assertSee('Add at least one step');
        $this->assertSame(CallFlow::PAUSED, CallFlow::first()->status);
        Queue::assertNotPushed(SyncVoiceWebhookJob::class);

        $c->call('goto', 'steps')->set('nodeType', 'voicemail')->call('saveNode')->call('goto', 'review')->call('activate');

        $this->assertSame(CallFlow::ACTIVE, CallFlow::first()->status);
        Queue::assertPushed(SyncVoiceWebhookJob::class, fn ($j) => $j->numberSid === 'PN_test' && $j->attach === true);

        $c->call('pause');
        $this->assertSame(CallFlow::PAUSED, CallFlow::first()->status);
    }

    public function test_deleting_a_step_scrubs_every_link_to_it_and_unsets_a_deleted_root(): void
    {
        $c = $this->builder()->call('selectNumber', $this->number->id)->call('goto', 'steps');
        $c->set('nodeType', 'voicemail')->call('saveNode');
        $vm = CallFlowNode::first();
        $c->set('nodeType', 'menu')->set('nodeCfg.prompt_text', 'Hi')->set('nodeCfg.digits.1', (string) $vm->id)->set('nodeCfg.invalid_retry_node_id', (string) $vm->id)->call('saveNode');

        $c->call('deleteNode', $vm->id);

        $menu = CallFlowNode::where('type', 'menu')->first();
        $this->assertArrayNotHasKey('1', (array) $menu->config['digits']);
        $this->assertNull($menu->config['invalid_retry_node_id']);
        $this->assertNull(CallFlow::first()->root_node_id, 'the deleted root must be cleared');
    }

    public function test_a_step_id_from_another_flow_cannot_be_edited_or_deleted(): void
    {
        $other = VirtualNumber::create(['user_id' => $this->owner->id, 'wallet_group_id' => $this->group->id, 'provider' => 'twilio', 'phone_number' => '+15005550014', 'sid' => 'O', 'status' => 'active']);
        $otherFlow = CallFlow::create(['wallet_group_id' => $this->group->id, 'virtual_number_id' => $other->id]);
        $foreign = CallFlowNode::create(['call_flow_id' => $otherFlow->id, 'type' => 'voicemail', 'config' => []]);

        $this->builder()->call('selectNumber', $this->number->id)->call('deleteNode', $foreign->id)->call('editNode', $foreign->id)->assertSet('editingNodeId', null);

        $this->assertNotNull(CallFlowNode::find($foreign->id));
    }
}
