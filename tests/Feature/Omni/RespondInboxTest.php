<?php

namespace Tests\Feature\Omni;

use App\Jobs\Omni\SendOmniMessage;
use App\Livewire\Omni\RespondInbox;
use App\Models\ChannelConnection;
use App\Models\Omni\OmniContact;
use App\Models\Omni\OmniConversation;
use App\Models\Omni\OmniMessage;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletGroup;
use App\Services\Omni\Adapters\FakeChannelAdapter;
use App\Services\Permissions\WorkspaceRoleService;
use App\Support\Permissions\BusinessRole;
use App\Support\Workspace;
use Database\Seeders\BusinessRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** Naara Respond inbox page: gated by the omni switch, workspace-scoped, obeys the 24-hour window, one message per double click. */
class RespondInboxTest extends TestCase
{
    use \Tests\Concerns\EnablesBusinessSuite;
    use RefreshDatabase;

    private User $owner;

    private WalletGroup $ws;

    private WalletGroup $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(BusinessRoleSeeder::class);
        Setting::setValue('business.module.inbox', true, 'business');
        Setting::setValue('business.module.omni', true, 'business');
        FakeChannelAdapter::$sent = [];

        $this->owner = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $this->ws = WalletGroup::create(['owner_user_id' => $this->owner->id, 'name' => 'Acme', 'kind' => WalletGroup::KIND_BUSINESS]);
        app(WorkspaceRoleService::class)->assignRole($this->ws, $this->owner, BusinessRole::OWNER);
        $this->other = WalletGroup::create(['owner_user_id' => User::factory()->create()->id, 'name' => 'Rival', 'kind' => WalletGroup::KIND_BUSINESS]);
        Workspace::setActive($this->ws);
    }

    private function thread(WalletGroup $g, string $name, bool $inboundNow = true, string $preview = 'hello there'): OmniConversation
    {
        $conn = ChannelConnection::create(['wallet_group_id' => $g->id, 'channel' => 'fake', 'external_id' => 'f-'.$g->id.'-'.$name, 'status' => 'active', 'label' => 'x', 'credentials' => [], 'webhook_secret' => 'secret-0123456789abc']);
        $contact = OmniContact::create(['workspace_id' => $g->id, 'name' => $name]);
        $c = OmniConversation::create(['workspace_id' => $g->id, 'contact_id' => $contact->id, 'connection_id' => $conn->id, 'channel' => 'fake', 'status' => 'open',
            'last_preview' => $preview, 'last_message_at' => now(), 'last_inbound_at' => $inboundNow ? now() : now()->subHours(30), 'unread' => 2]);
        OmniMessage::create(['workspace_id' => $g->id, 'conversation_id' => $c->id, 'direction' => 'in', 'status' => 'received', 'body' => $preview]);

        return $c;
    }

    public function test_the_page_is_coming_soon_while_the_switch_is_off(): void
    {
        Setting::setValue('business.module.omni', false, 'business');
        $r = $this->actingAs($this->owner)->withSession(['active_wallet_group_id' => $this->ws->id])->get('/business/respond');
        $this->assertNotSame(500, $r->status());
        $r->assertDontSee('wire:poll');
    }

    public function test_it_lists_only_this_workspaces_conversations_and_cannot_open_another(): void
    {
        $mine = $this->thread($this->ws, 'Ada');
        $theirs = $this->thread($this->other, 'Zed');
        Livewire::actingAs($this->owner)->test(RespondInbox::class)
            ->assertSee('Ada')->assertDontSee('Zed');
        try {
            Livewire::actingAs($this->owner)->test(RespondInbox::class)->call('select', $theirs->id);
            $this->fail('another workspace\'s conversation must not open');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->assertTrue(true);
        }
        Livewire::actingAs($this->owner)->test(RespondInbox::class)->call('select', $mine->id)->assertSet('selected', $mine->id);
        $this->assertSame(0, (int) $mine->fresh()->unread);
    }

    public function test_search_and_status_filter(): void
    {
        $this->thread($this->ws, 'Ada', true, 'refund please');
        $b = $this->thread($this->ws, 'Bola', true, 'invoice');
        $b->forceFill(['status' => 'resolved'])->save();
        $t = Livewire::actingAs($this->owner)->test(RespondInbox::class);
        $t->assertSee('Ada')->assertDontSee('Bola');
        $t->call('setFilter', 'resolved')->assertSee('Bola')->assertDontSee('Ada');
        $t->call('setFilter', 'all')->set('q', 'refund')->assertSee('Ada')->assertDontSee('Bola');
    }

    public function test_a_reply_is_queued_once_even_on_a_double_click_and_the_job_sends_it(): void
    {
        Queue::fake();
        $c = $this->thread($this->ws, 'Ada');
        $t = Livewire::actingAs($this->owner)->test(RespondInbox::class)->call('select', $c->id);
        $key = $t->get('sendKey');
        $t->set('draft', 'On it')->call('send')->assertSet('draft', '');
        // a replayed request with the same key must not create a second message
        $t->set('sendKey', $key)->set('draft', 'On it')->call('send');
        $this->assertSame(1, OmniMessage::where('conversation_id', $c->id)->where('direction', 'out')->count());
        Queue::assertPushed(SendOmniMessage::class, 1);
    }

    public function test_the_window_rule_blocks_a_free_form_reply_after_24_hours(): void
    {
        Queue::fake();
        $c = $this->thread($this->ws, 'Ada', false);
        $adapter = app(\App\Services\Omni\AdapterRegistry::class)->for('fake');
        if (! $adapter->requiresWindow()) {
            $this->markTestSkipped('fake adapter does not require a window');
        }
        Livewire::actingAs($this->owner)->test(RespondInbox::class)->call('select', $c->id)->set('draft', 'late')->call('send')->assertNotSet('error', null);
        $this->assertSame(0, OmniMessage::where('conversation_id', $c->id)->where('direction', 'out')->count());
    }

    public function test_status_and_assignment_change(): void
    {
        $c = $this->thread($this->ws, 'Ada');
        Livewire::actingAs($this->owner)->test(RespondInbox::class)->call('select', $c->id)->call('setStatus', 'pending')->call('assignToMe');
        $this->assertSame('pending', $c->fresh()->status);
        $this->assertSame($this->owner->id, (int) $c->fresh()->assignee_id);
        Livewire::actingAs($this->owner)->test(RespondInbox::class)->call('select', $c->id)->call('setStatus', 'bogus')->assertStatus(422);
    }

    public function test_notes_are_private_and_never_queue_a_message(): void
    {
        Queue::fake();
        $c = $this->thread($this->ws, 'Ada');
        Livewire::actingAs($this->owner)->test(RespondInbox::class)->call('select', $c->id)->set('note', 'VIP, refund approved')->call('addNote')
            ->assertSee('VIP, refund approved')->assertSee('Private note');
        $this->assertSame(1, \App\Models\Omni\OmniNote::where('conversation_id', $c->id)->count());
        $this->assertSame(0, OmniMessage::where('conversation_id', $c->id)->where('direction', 'out')->count());
        Queue::assertNothingPushed();
    }

    public function test_saved_replies_insert_into_the_draft_and_stay_in_their_workspace(): void
    {
        $c = $this->thread($this->ws, 'Ada');
        \App\Models\Omni\OmniSnippet::create(['workspace_id' => $this->other->id, 'title' => 'Secret', 'body' => 'rival text']);
        $t = Livewire::actingAs($this->owner)->test(RespondInbox::class)->call('select', $c->id)->set('snipTitle', 'Hours')->set('snipBody', 'We open 9 to 5.')->call('saveSnippet')
            ->assertSee('Hours')->assertDontSee('Secret');
        $id = \App\Models\Omni\OmniSnippet::where('workspace_id', $this->ws->id)->value('id');
        $t->call('useSnippet', $id)->assertSet('draft', 'We open 9 to 5.');
        $rival = \App\Models\Omni\OmniSnippet::where('workspace_id', $this->other->id)->value('id');
        $t->call('deleteSnippet', $rival);
        $this->assertSame(1, \App\Models\Omni\OmniSnippet::where('workspace_id', $this->other->id)->count());
    }

    public function test_scope_filters_and_bulk_actions_stay_inside_the_workspace(): void
    {
        $a = $this->thread($this->ws, 'Ada');
        $b = $this->thread($this->ws, 'Bola');
        $z = $this->thread($this->other, 'Zed');
        $b->forceFill(['assignee_id' => $this->owner->id])->save();
        $t = Livewire::actingAs($this->owner)->test(RespondInbox::class);
        $t->call('setScope', 'mine')->assertSee('Bola')->assertDontSee('Ada');
        $t->call('setScope', 'unassigned')->assertSee('Ada')->assertDontSee('Bola');
        $t->call('setScope', 'all')->set('picked', [$a->id, $b->id, $z->id])->call('bulkSetStatus', 'resolved');
        $this->assertSame('resolved', $a->fresh()->status);
        $this->assertSame('resolved', $b->fresh()->status);
        $this->assertSame('open', $z->fresh()->status, 'another workspace is untouched');
        $t->set('picked', [$z->id])->call('bulkAssignToMe');
        $this->assertNull($z->fresh()->assignee_id);
    }

    public function test_an_unanswered_conversation_past_the_sla_is_marked_overdue(): void
    {
        $c = $this->thread($this->ws, 'Ada');
        Livewire::actingAs($this->owner)->test(RespondInbox::class)->assertDontSee('Overdue');
        $c->forceFill(['last_inbound_at' => now()->subMinutes(40)])->save();
        Livewire::actingAs($this->owner)->test(RespondInbox::class)->assertSee('Overdue');
    }
}
