<?php

namespace Tests\Feature\Omni;

use App\Livewire\Omni\RespondAutomation;
use App\Livewire\Omni\RespondInbox;
use App\Models\ChannelConnection;
use App\Models\Omni\OmniAgentRun;
use App\Models\Omni\OmniAgentSetting;
use App\Models\Omni\OmniBroadcast;
use App\Models\Omni\OmniContact;
use App\Models\Omni\OmniConversation;
use App\Models\Omni\OmniFlow;
use App\Models\Omni\OmniIdentity;
use App\Models\Omni\OmniKbSource;
use App\Models\Omni\OmniMessage;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletGroup;
use App\Services\Permissions\WorkspaceRoleService;
use App\Support\Permissions\BusinessRole;
use App\Support\Workspace;
use Database\Seeders\BusinessRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** The Respond automation page: access, knowledge, agent switch rules, flows, broadcasts, webhooks, insights; plus the AI suggestion and opt-in toggle in the inbox. */
class RespondAutomationPageTest extends TestCase
{
    use \Tests\Concerns\EnablesBusinessSuite;
    use RefreshDatabase;

    private User $owner;

    private WalletGroup $ws;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(BusinessRoleSeeder::class);
        Setting::setValue('business.module.inbox', true, 'business');
        Setting::setValue('business.module.omni', true, 'business');
        Setting::setValue('business.module.brain_assist', true, 'business');
        $this->owner = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $this->ws = WalletGroup::create(['owner_user_id' => $this->owner->id, 'name' => 'Acme', 'kind' => WalletGroup::KIND_BUSINESS]);
        app(WorkspaceRoleService::class)->assignRole($this->ws, $this->owner, BusinessRole::OWNER);
        Workspace::setActive($this->ws);
    }

    private function page()
    {
        return Livewire::actingAs($this->owner)->test(RespondAutomation::class);
    }

    public function test_only_owner_and_admins_open_the_page_and_it_hides_when_the_switch_is_off(): void
    {
        $agent = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        \App\Models\WalletGroupMember::create(['wallet_group_id' => $this->ws->id, 'user_id' => $agent->id, 'role' => BusinessRole::AGENT, 'status' => 'active', 'invited_at' => now(), 'accepted_at' => now()]);
        app(WorkspaceRoleService::class)->assignRole($this->ws, $agent, BusinessRole::AGENT);
        $this->actingAs($this->owner)->withSession(['active_wallet_group_id' => $this->ws->id])->get('/business/respond/automation')->assertOk()->assertSee('Respond automation');
        $this->actingAs($agent)->withSession(['active_wallet_group_id' => $this->ws->id])->get('/business/respond/automation')->assertNotFound();
        Setting::setValue('business.module.omni', false, 'business');
        $this->actingAs($this->owner)->withSession(['active_wallet_group_id' => $this->ws->id])->get('/business/respond/automation')->assertDontSee('data-respond-automation', false);
    }

    public function test_knowledge_can_be_added_and_an_unsafe_page_is_refused_with_a_plain_message(): void
    {
        $this->page()->set('kbTitle', 'Delivery')->set('kbText', 'Delivery in Lagos takes two days.')->call('addKbText')->assertSee('Delivery')->assertSet('error', null);
        $this->page()->set('kbUrl', 'http://127.0.0.1/admin')->call('addKbUrl')->assertSee('private network');
        Http::fake(['https://8.8.8.8/*' => Http::response('<title>FAQ</title><p>We accept cards and transfers.</p>', 200, ['Content-Type' => 'text/html'])]);
        $this->page()->set('kbUrl', 'https://8.8.8.8/faq')->call('addKbUrl')->assertSet('error', null)->assertSee('FAQ');
        $this->assertSame(2, OmniKbSource::where('workspace_id', $this->ws->id)->count());
    }

    public function test_the_assistant_cannot_be_switched_on_before_it_has_been_taught(): void
    {
        $this->page()->set('mode', 'auto')->call('saveAgent')->assertSee('Teach the assistant first');
        $this->assertNull(OmniAgentSetting::where('workspace_id', $this->ws->id)->value('mode'));
        app(\App\Services\Omni\Kb\KnowledgeBase::class)->addText($this->ws->id, 'faq', 'Hours', 'We open at nine.');
        $this->page()->set('mode', 'suggest')->set('cap', 50)->set('handoff', 'human, refund')->call('saveAgent')->assertSet('error', null);
        $s = OmniAgentSetting::where('workspace_id', $this->ws->id)->firstOrFail();
        $this->assertSame('suggest', $s->mode);
        $this->assertSame(['human', 'refund'], $s->handoff_words);
    }

    public function test_the_assistant_needs_the_plan_and_shows_the_shared_allowance(): void
    {
        app(\App\Services\Omni\Kb\KnowledgeBase::class)->addText($this->ws->id, 'faq', 'Hours', 'We open at nine.');
        $this->page()->call('setTab', 'agent')->assertSee('assistant actions used this month');
        Setting::setValue(\App\Support\Business\BusinessSettings::ACCESS.'model', 'pro');
        $this->page()->set('mode', 'auto')->call('saveAgent')->assertSee('not part of your current Naara Pro plan');
        $this->assertNull(OmniAgentSetting::where('workspace_id', $this->ws->id)->value('mode'));
    }

    public function test_flows_are_built_validated_tested_and_switched_on(): void
    {
        $p = $this->page()->call('setTab', 'flows')->set('fName', 'Prices')->set('fTrigger', 'keyword')->call('saveFlow')->assertSee('keyword');
        $p->set('fValue', 'price')->set('stepType', 'send_text')->set('stepValue', 'Hi {name}')->call('addStep')->call('saveFlow')->assertSet('error', null);
        $flow = OmniFlow::where('workspace_id', $this->ws->id)->firstOrFail();
        $this->assertSame('draft', $flow->status);
        $p->call('testFlow', $flow->id)->assertSee('nothing was sent')->assertSee('Would send: Hi Customer');
        $p->call('toggleFlow', $flow->id);
        $this->assertSame('live', $flow->fresh()->status);
        $p->call('installStarters')->call('installStarters');
        $this->assertSame(5, OmniFlow::where('workspace_id', $this->ws->id)->count(), 'starters install once');
    }

    public function test_broadcast_preview_send_and_the_template_channel_block(): void
    {
        Queue::fake();
        $conn = ChannelConnection::create(['wallet_group_id' => $this->ws->id, 'channel' => 'webchat', 'external_id' => 'wc1', 'status' => 'active', 'label' => 'Site', 'credentials' => [], 'webhook_secret' => 'secret-0123456789abc']);
        foreach (range(1, 3) as $i) {
            $c = OmniContact::create(['workspace_id' => $this->ws->id, 'name' => 'P'.$i, 'marketing_opt_in' => true]);
            OmniIdentity::create(['workspace_id' => $this->ws->id, 'contact_id' => $c->id, 'channel' => 'webchat', 'external_id' => 'v'.$i]);
        }
        $p = $this->page()->call('setTab', 'broadcasts')->set('bName', 'Promo')->set('bBody', 'Sale!')->call('previewBroadcast')->assertSee('Pick the channel');
        $p->set('bConn', $conn->id)->call('previewBroadcast')->assertSee('3 people would receive this');
        $p->call('sendBroadcast')->assertSet('error', null);
        $this->assertSame('sending', OmniBroadcast::where('workspace_id', $this->ws->id)->firstOrFail()->status);
        $wa = ChannelConnection::create(['wallet_group_id' => $this->ws->id, 'channel' => 'fake', 'external_id' => 'f1', 'status' => 'active', 'label' => 'Fake', 'credentials' => [], 'webhook_secret' => 'secret-0123456789abc']);
        $this->page()->call('setTab', 'broadcasts')->set('bBody', 'Sale!')->set('bConn', $wa->id)->call('previewBroadcast')->assertSee('approved message templates');
    }

    public function test_webhooks_show_the_secret_once_and_refuse_private_addresses(): void
    {
        $p = $this->page()->call('setTab', 'integrations')->set('whUrl', 'https://127.0.0.1/x')->call('addWebhook')->assertSee('private network');
        $p->set('whUrl', 'https://8.8.8.8/hook')->call('addWebhook')->assertSee('shown once');
        $this->assertSame(1, \App\Models\Omni\OmniWebhook::where('workspace_id', $this->ws->id)->count());
        $this->page()->call('setTab', 'integrations')->assertDontSee('shown once');
    }

    public function test_insights_render_numbers_and_health_for_this_workspace_only(): void
    {
        $conn = ChannelConnection::create(['wallet_group_id' => $this->ws->id, 'channel' => 'telegram', 'external_id' => 't1', 'status' => 'paused', 'label' => '@acme_bot', 'credentials' => [], 'webhook_secret' => 'secret-0123456789abc']);
        $this->page()->call('setTab', 'insights')->assertSee('Conversations')->assertSee('Not working')->assertSee('@acme_bot')->call('setDays', 30)->assertSet('days', 30);
    }

    public function test_the_inbox_shows_the_latest_ai_suggestion_and_records_broadcast_consent(): void
    {
        $conn = ChannelConnection::create(['wallet_group_id' => $this->ws->id, 'channel' => 'webchat', 'external_id' => 'wc1', 'status' => 'active', 'label' => 'Site', 'credentials' => [], 'webhook_secret' => 'secret-0123456789abc']);
        $contact = OmniContact::create(['workspace_id' => $this->ws->id, 'name' => 'Ada']);
        $conv = OmniConversation::create(['workspace_id' => $this->ws->id, 'contact_id' => $contact->id, 'connection_id' => $conn->id, 'channel' => 'webchat', 'status' => 'open', 'last_inbound_at' => now(), 'last_message_at' => now()]);
        $m = OmniMessage::create(['workspace_id' => $this->ws->id, 'conversation_id' => $conv->id, 'direction' => 'in', 'status' => 'received', 'body' => 'Hours?', 'provider_message_id' => 'a']);
        $run = OmniAgentRun::create(['workspace_id' => $this->ws->id, 'conversation_id' => $conv->id, 'message_id' => $m->id, 'mode' => 'suggest', 'outcome' => 'suggested', 'reply' => 'We open at nine.']);
        $t = Livewire::actingAs($this->owner)->test(RespondInbox::class)->call('select', $conv->id)->assertSee('AI suggests')->assertSee('We open at nine.');
        $t->call('useSuggestion', $run->id)->assertSet('draft', 'We open at nine.');
        $t->call('toggleOptIn');
        $this->assertTrue($contact->fresh()->marketing_opt_in);
        $t->call('toggleOptIn');
        $this->assertFalse($contact->fresh()->marketing_opt_in);
        $this->assertNotNull($contact->fresh()->opted_out_at);
    }

    public function test_the_inbox_cannot_use_another_workspaces_suggestion(): void
    {
        $other = WalletGroup::create(['owner_user_id' => User::factory()->create()->id, 'name' => 'Rival', 'kind' => WalletGroup::KIND_BUSINESS]);
        $contact = OmniContact::create(['workspace_id' => $other->id, 'name' => 'Z']);
        $oc = OmniConversation::create(['workspace_id' => $other->id, 'contact_id' => $contact->id, 'channel' => 'webchat', 'status' => 'open']);
        $run = OmniAgentRun::create(['workspace_id' => $other->id, 'conversation_id' => $oc->id, 'mode' => 'suggest', 'outcome' => 'suggested', 'reply' => 'secret']);
        $mine = OmniConversation::create(['workspace_id' => $this->ws->id, 'contact_id' => OmniContact::create(['workspace_id' => $this->ws->id, 'name' => 'A'])->id, 'channel' => 'webchat', 'status' => 'open']);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::actingAs($this->owner)->test(RespondInbox::class)->call('select', $mine->id)->call('useSuggestion', $run->id);
    }
}
