<?php

namespace Tests\Feature\Omni;

use App\Jobs\Omni\ProcessOmniRawEvent;
use App\Livewire\BusinessChannels;
use App\Models\ChannelConnection;
use App\Models\InboundMessage;
use App\Models\Omni\OmniConversation;
use App\Models\Omni\OmniMessage;
use App\Models\OutboundMessage;
use App\Models\Setting;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Models\WalletGroup;
use App\Services\Omni\Outbox;
use App\Services\Permissions\WorkspaceRoleService;
use App\Services\SMS\SmsDeliveryReconciler;
use App\Services\Wallet\WalletService;
use App\Support\Permissions\BusinessRole;
use App\Support\Workspace;
use Database\Seeders\BusinessRoleSeeder;
use Database\Seeders\PricingSettingsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\FakePermanentProvider;
use Tests\TestCase;

/** SMS on a workspace number in the omni inbox: connect it, receive through the existing verified SMS webhook, reply through the one SMS pipeline (charged to the owner), delivery follows. */
class SmsChannelTest extends TestCase
{
    use \Tests\Concerns\EnablesBusinessSuite;
    use RefreshDatabase;

    private User $owner;

    private WalletGroup $ws;

    private VirtualNumber $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(BusinessRoleSeeder::class);
        $this->seed(PricingSettingsSeeder::class);
        Setting::setValue('business.module.inbox', true, 'business');
        Setting::setValue('business.module.omni', true, 'business');
        app()->instance('number.twilio', new FakePermanentProvider(smsCost: 0.0079));
        $this->owner = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        app(WalletService::class)->credit($this->owner, 10, 'USD', ['reference' => 'seed:'.$this->owner->id]);
        $this->ws = WalletGroup::create(['owner_user_id' => $this->owner->id, 'name' => 'Acme', 'kind' => WalletGroup::KIND_BUSINESS]);
        app(WorkspaceRoleService::class)->assignRole($this->ws, $this->owner, BusinessRole::OWNER);
        Workspace::setActive($this->ws);
        $this->line = VirtualNumber::create(['user_id' => $this->owner->id, 'wallet_group_id' => $this->ws->id, 'provider' => 'twilio', 'phone_number' => '+15550001111', 'sid' => 'SID-1',
            'capabilities' => ['sms' => true, 'voice' => true], 'monthly_cost' => 1.0, 'monthly_retail' => 1.4, 'status' => 'active', 'next_billing_date' => now()->addMonth()->toDateString(), 'provisioned_at' => now()]);
        config(['services.plivo.webhook_token' => 'inbound-token-123']);
    }

    private function inbound(string $ref, string $body = 'Is the red one in stock?', string $from = '+2348012345678')
    {
        return $this->postJson('/webhooks/sms-inbound/plivo', ['to' => '+15550001111', 'from' => $from, 'text' => $body, 'message_id' => $ref], ['X-Webhook-Token' => 'inbound-token-123']);
    }

    public function test_the_workspace_connects_its_own_number_and_only_its_own(): void
    {
        $someoneElses = VirtualNumber::create(['user_id' => User::factory()->create()->id, 'provider' => 'twilio', 'phone_number' => '+15550002222', 'sid' => 'S2', 'capabilities' => ['sms' => true],
            'monthly_cost' => 1, 'monthly_retail' => 1.4, 'status' => 'active', 'next_billing_date' => now()->addMonth()->toDateString(), 'provisioned_at' => now()]);
        $t = Livewire::actingAs($this->owner)->test(BusinessChannels::class)->assertSee('data-sms-channel', false)->assertSee('+15550001111')->assertDontSee('+15550002222');
        $t->call('connectSms', $someoneElses->id)->assertSee('not an active number of this workspace');
        $t->call('connectSms', $this->line->id)->assertSet('error', null)->assertSee('SMS number connected');
        $c = ChannelConnection::where('channel', 'sms')->firstOrFail();
        $this->assertSame('+15550001111', $c->external_id);
        $this->assertSame($this->ws->id, (int) $c->wallet_group_id);
        $this->assertSame(1, ChannelConnection::where('channel', 'sms')->count());
    }

    public function test_an_inbound_text_is_kept_by_the_sms_system_and_filed_in_the_omni_inbox_once(): void
    {
        Livewire::actingAs($this->owner)->test(BusinessChannels::class)->call('connectSms', $this->line->id);
        $this->inbound('ref-1')->assertOk();
        $this->inbound('ref-1')->assertOk();   // a carrier retry
        $this->assertSame(1, InboundMessage::count(), 'the existing SMS inbox still records it');
        $m = OmniMessage::where('workspace_id', $this->ws->id)->where('direction', 'in')->firstOrFail();
        $this->assertSame('Is the red one in stock?', $m->body);
        $this->assertSame(1, OmniMessage::count());
        $conv = OmniConversation::firstOrFail();
        $this->assertSame('sms', $conv->channel);
        $this->assertFalse((bool) \App\Models\Omni\OmniIdentity::where('external_id', '+2348012345678')->doesntExist());
    }

    public function test_a_number_not_connected_or_not_in_a_workspace_files_nothing_in_omni(): void
    {
        $this->inbound('ref-2')->assertOk();   // not connected yet
        $this->assertSame(0, OmniMessage::count());
        $personal = VirtualNumber::create(['user_id' => $this->owner->id, 'provider' => 'twilio', 'phone_number' => '+15550003333', 'sid' => 'S3', 'capabilities' => ['sms' => true],
            'monthly_cost' => 1, 'monthly_retail' => 1.4, 'status' => 'active', 'next_billing_date' => now()->addMonth()->toDateString(), 'provisioned_at' => now()]);
        $this->postJson('/webhooks/sms-inbound/plivo', ['to' => $personal->phone_number, 'from' => '+2348012345678', 'text' => 'hi', 'message_id' => 'p1'], ['X-Webhook-Token' => 'inbound-token-123'])->assertOk();
        $this->assertSame(0, OmniMessage::count());
        $this->postJson('/webhooks/sms-inbound/plivo', ['to' => '+15550001111', 'from' => '+2348012345678', 'text' => 'forged', 'message_id' => 'f1'], ['X-Webhook-Token' => 'wrong'])->assertStatus(401);
        $this->assertSame(0, OmniMessage::count());
    }

    public function test_a_reply_goes_through_the_one_sms_pipeline_is_charged_to_the_owner_and_delivery_updates_it(): void
    {
        Queue::fake([\App\Jobs\SendOutboundSmsJob::class]);
        Livewire::actingAs($this->owner)->test(BusinessChannels::class)->call('connectSms', $this->line->id);
        $this->inbound('ref-3')->assertOk();
        $conv = OmniConversation::firstOrFail();
        $before = (float) \App\Models\WalletTransaction::where('user_id', $this->owner->id)->count();

        $msg = app(Outbox::class)->queue($this->ws->id, $conv, 'Yes, two left.', [], $this->owner->id, 'idem-1');
        $this->assertFalse(app(\App\Services\Omni\AdapterRegistry::class)->for('sms')->requiresWindow());
        app(Outbox::class)->deliver($msg->id);
        $msg->refresh();
        $this->assertSame('sent', $msg->status);
        $out = OutboundMessage::where('user_id', $this->owner->id)->firstOrFail();
        $this->assertSame('+2348012345678', $out->to_number);
        $this->assertSame('Yes, two left.', $out->body);
        $this->assertSame('sms-'.$out->id, $msg->provider_message_id);
        $this->assertGreaterThan(0, (float) $out->amount_charged);

        app(SmsDeliveryReconciler::class)->apply($out, 'delivered');
        $this->assertSame('delivered', $msg->fresh()->status);
    }

    public function test_a_failed_send_shows_failed_and_the_reply_refund_rules_are_the_sms_systems_own(): void
    {
        Livewire::actingAs($this->owner)->test(BusinessChannels::class)->call('connectSms', $this->line->id);
        $this->inbound('ref-4')->assertOk();
        $conv = OmniConversation::firstOrFail();
        app()->instance('number.twilio', new FakePermanentProvider(smsCost: 0.0079, throwOnSend: true));
        Queue::fake([\App\Jobs\SendOutboundSmsJob::class]);

        $msg = app(Outbox::class)->queue($this->ws->id, $conv, 'hello', [], $this->owner->id, 'idem-2');
        app(Outbox::class)->deliver($msg->id);
        $out = OutboundMessage::firstOrFail();
        app(\App\Services\SMS\MessageSenderService::class)->deliver($out->id);   // the queued job body: provider throws, wallet refunded, row failed
        $this->assertSame('failed', $out->fresh()->status);
        $this->assertSame('failed', $msg->fresh()->status);
    }

    public function test_the_channel_cannot_be_reached_from_outside(): void
    {
        Livewire::actingAs($this->owner)->test(BusinessChannels::class)->call('connectSms', $this->line->id);
        $c = ChannelConnection::where('channel', 'sms')->firstOrFail();
        Queue::fake();
        $this->postJson('/webhooks/omni/sms/'.$c->public_id, ['ref' => 'x', 'from' => '+2348000000000', 'body' => 'spoof'])->assertStatus(403);
        Queue::assertNotPushed(ProcessOmniRawEvent::class);
    }
}
