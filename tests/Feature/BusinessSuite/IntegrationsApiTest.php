<?php

namespace Tests\Feature\BusinessSuite;

use App\Jobs\DeliverBusinessWebhookJob;
use App\Jobs\LogCallEventJob;
use App\Jobs\RecordChannelMessageJob;
use App\Jobs\RecordInboundMessageJob;
use App\Livewire\DeveloperPortal;
use App\Models\ApiClient;
use App\Models\CallEvent;
use App\Models\CallFlow;
use App\Models\ChannelConnection;
use App\Models\InboundMessage;
use App\Models\MessageThread;
use App\Models\Setting;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Models\WalletGroup;
use App\Models\WebhookSubscription;
use App\Services\Api\ApiClientService;
use App\Services\Business\Integrations\WebhookEvents;
use App\Services\Permissions\WorkspaceRoleService;
use App\Support\Permissions\BusinessRole;
use Database\Seeders\BusinessRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** Business Suite Prompt 18: workspace-bound API keys, the read-only endpoints, signed webhooks, and the developer-portal additions. */
class IntegrationsApiTest extends TestCase
{
    use \Tests\Concerns\EnablesBusinessSuite;
    use RefreshDatabase;

    private User $owner;

    private WalletGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(BusinessRoleSeeder::class);
        Setting::setValue('developer_api.enabled', true, 'developer_api');
        Setting::setValue('business.module.integrations', true, 'business');

        $this->owner = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $this->group = $this->workspace($this->owner, 'Acme');
    }

    private function workspace(User $owner, string $name): WalletGroup
    {
        $g = WalletGroup::create(['owner_user_id' => $owner->id, 'name' => $name, 'kind' => WalletGroup::KIND_BUSINESS]);
        app(WorkspaceRoleService::class)->assignRole($g, $owner, BusinessRole::OWNER);

        return $g;
    }

    /** @return array{0:string,1:ApiClient} */
    private function key(?User $owner = null, ?WalletGroup $group = null, array $scopes = ['contacts:read', 'calls:read', 'messages:read', 'webhook:subscribe']): array
    {
        $r = app(ApiClientService::class)->create($owner ?? $this->owner, 'zap', $scopes, ($group ?? $this->group)->id);

        return [$r['token'], $r['client']];
    }

    private function line(User $u, WalletGroup $g, string $n): VirtualNumber
    {
        return VirtualNumber::create(['user_id' => $u->id, 'wallet_group_id' => $g->id, 'provider' => 'twilio', 'phone_number' => $n, 'sid' => 'PN'.$n, 'status' => 'active']);
    }

    private function flow(User $u, WalletGroup $g, string $n): CallFlow
    {
        return CallFlow::create(['wallet_group_id' => $g->id, 'virtual_number_id' => $this->line($u, $g, $n)->id, 'status' => CallFlow::ACTIVE]);
    }

    /** Each request is a separate caller: the test app would otherwise keep the previous request's authenticated client. */
    private function as(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function thread(WalletGroup $g, string $who, string $channel = 'whatsapp'): MessageThread
    {
        return MessageThread::create(['user_id' => $g->owner_user_id, 'channel' => $channel, 'wallet_group_id' => $g->id, 'counterpart_number' => $who, 'last_body' => 'hi', 'last_direction' => 'in', 'last_at' => now()]);
    }

    public function test_business_scopes_only_stick_to_a_key_bound_to_a_workspace_the_owner_administers(): void
    {
        [, $bound] = $this->key();
        $this->assertSame($this->group->id, $bound->wallet_group_id);
        $this->assertContains('contacts:read', $bound->scopes);

        $plain = app(ApiClientService::class)->create($this->owner, 'plain', ['catalogue', 'contacts:read'])['client'];
        $this->assertNull($plain->wallet_group_id);
        $this->assertSame(['catalogue'], $plain->scopes);

        $stranger = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $foreign = app(ApiClientService::class)->create($stranger, 'sneaky', ['catalogue', 'contacts:read'], $this->group->id)['client'];
        $this->assertNull($foreign->wallet_group_id);
        $this->assertNotContains('contacts:read', $foreign->scopes);
    }

    public function test_business_scopes_are_not_offered_while_the_module_is_off(): void
    {
        Setting::setValue('business.module.integrations', false, 'business');
        $this->assertSame(ApiClient::SCOPES, ApiClient::availableScopes());
        $client = app(ApiClientService::class)->create($this->owner, 'k', ['catalogue', 'contacts:read'], $this->group->id)['client'];
        $this->assertNull($client->wallet_group_id);
        $this->assertSame(['catalogue'], $client->scopes);
    }

    public function test_the_api_answers_404_when_the_module_is_off_or_the_key_is_not_bound(): void
    {
        [$token] = $this->key();
        $this->as($token)->getJson('/api/v1/business/contacts')->assertOk();

        Setting::setValue('business.module.integrations', false, 'business');
        $this->as($token)->getJson('/api/v1/business/contacts')->assertNotFound();

        Setting::setValue('business.module.integrations', true, 'business');
        $plain = app(ApiClientService::class)->create($this->owner, 'plain', ['catalogue'])['token'];
        $this->as($plain)->getJson('/api/v1/business/contacts')->assertNotFound(); // not bound to a workspace: the surface does not exist for this key
    }

    public function test_a_key_stops_working_when_its_owner_is_no_longer_an_admin_of_the_workspace(): void
    {
        $admin = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        app(WorkspaceRoleService::class)->assignRole($this->group, $admin, BusinessRole::ADMIN);
        [$token] = $this->key($admin);
        $this->as($token)->getJson('/api/v1/business/contacts')->assertOk();

        app(WorkspaceRoleService::class)->assignRole($this->group, $admin, BusinessRole::AGENT);
        $this->as($token)->getJson('/api/v1/business/contacts')->assertNotFound();
    }

    public function test_every_endpoint_enforces_its_own_scope(): void
    {
        [$token] = $this->key(scopes: ['contacts:read']);
        $this->as($token)->getJson('/api/v1/business/contacts')->assertOk();
        $this->as($token)->getJson('/api/v1/business/calls')->assertForbidden();
        $this->as($token)->getJson('/api/v1/business/messages')->assertForbidden();
        $this->as($token)->getJson('/api/v1/business/webhooks')->assertForbidden();
    }

    public function test_contacts_calls_and_messages_are_scoped_to_the_workspace_with_no_leakage(): void
    {
        $other = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $otherGroup = $this->workspace($other, 'Rival');

        $this->thread($this->group, 'wa-mine');
        $this->thread($otherGroup, 'wa-theirs');

        $line = $this->line($this->owner, $this->group, '+15550009');
        InboundMessage::create(['user_id' => $this->owner->id, 'channel' => 'sms', 'virtual_number_id' => $line->id, 'from_number' => '+15550001', 'body' => 'mine-sms', 'provider' => 'twilio', 'provider_ref' => 'a', 'received_at' => now()]);
        InboundMessage::create(['user_id' => $other->id, 'channel' => 'whatsapp', 'wallet_group_id' => $otherGroup->id, 'from_number' => 'x', 'body' => 'theirs', 'provider' => 'whatsapp', 'provider_ref' => 'b', 'received_at' => now()]);

        $mine = $this->flow($this->owner, $this->group, '+15550101');
        $theirs = $this->flow($other, $otherGroup, '+15550102');
        CallEvent::create(['call_flow_id' => $mine->id, 'call_sid' => 'CAmine', 'from_number' => '+1', 'to_number' => '+2', 'status' => 'answered']);
        CallEvent::create(['call_flow_id' => $theirs->id, 'call_sid' => 'CAtheirs', 'from_number' => '+1', 'to_number' => '+2', 'status' => 'answered']);

        [$token] = $this->key();
        $contacts = $this->as($token)->getJson('/api/v1/business/contacts')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing(['wa-mine', '+15550001'], array_column($contacts, 'counterpart')); // the WhatsApp thread + the SMS thread on the workspace line
        $this->assertNotContains('wa-theirs', array_column($contacts, 'counterpart'));

        $messages = $this->as($token)->getJson('/api/v1/business/messages')->assertOk()->json('data');
        $this->assertSame(['mine-sms'], array_column($messages, 'body'));

        $calls = $this->as($token)->getJson('/api/v1/business/calls')->assertOk()->json('data');
        $this->assertSame(['CAmine'], array_column($calls, 'call_id'));

        // a channel filter narrows without widening
        $this->as($token)->getJson('/api/v1/business/contacts?channel=telegram')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_webhooks_can_be_created_listed_and_removed_and_the_secret_is_shown_once(): void
    {
        [$token, $client] = $this->key();
        $res = $this->as($token)->postJson('/api/v1/business/webhooks', ['event_type' => 'message.received', 'target_url' => 'https://8.8.8.8/hook'])->assertCreated();
        $secret = $res->json('data.secret');
        $this->assertStringStartsWith('whsec_', $secret);

        $list = $this->as($token)->getJson('/api/v1/business/webhooks')->assertOk();
        $this->assertArrayNotHasKey('secret', $list->json('data.0'));
        $this->assertSame($secret, WebhookSubscription::first()->secret);
        $this->assertNotSame($secret, \DB::table('webhook_subscriptions')->value('secret'), 'stored encrypted');

        $id = $res->json('data.id');
        $this->as($token)->deleteJson("/api/v1/business/webhooks/$id")->assertOk();
        $this->assertSame(0, WebhookSubscription::count());

        // another key's webhook is invisible
        [$t2] = $this->key();
        $mine = WebhookSubscription::create(['api_client_id' => $client->id, 'wallet_group_id' => $this->group->id, 'event_type' => 'call.completed', 'target_url' => 'https://8.8.8.8/x', 'secret' => 's', 'status' => 'active']);
        $this->as($t2)->deleteJson("/api/v1/business/webhooks/{$mine->id}")->assertNotFound();
    }

    public function test_webhook_registration_refuses_private_and_non_https_addresses_and_unknown_events(): void
    {
        [$token] = $this->key();
        foreach (['https://127.0.0.1/h', 'https://10.0.0.5/h', 'http://8.8.8.8/h', 'https://169.254.169.254/latest'] as $url) {
            $this->as($token)->postJson('/api/v1/business/webhooks', ['event_type' => 'call.completed', 'target_url' => $url])->assertStatus(422);
        }
        $this->as($token)->postJson('/api/v1/business/webhooks', ['event_type' => 'wallet.debited', 'target_url' => 'https://8.8.8.8/h'])->assertStatus(422);
        $this->assertSame(0, WebhookSubscription::count());
    }

    public function test_a_delivery_is_signed_over_the_raw_body_and_marks_success(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        [, $client] = $this->key();
        $sub = WebhookSubscription::create(['api_client_id' => $client->id, 'wallet_group_id' => $this->group->id, 'event_type' => 'message.received', 'target_url' => 'https://8.8.8.8/h', 'secret' => 'whsec_test', 'status' => 'active', 'consecutive_failures' => 2]);

        $body = json_encode(['id' => 'd1', 'type' => 'message.received', 'data' => ['body' => 'héllo/']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        (new DeliverBusinessWebhookJob($sub->id, 'd1', $body))->handle();

        Http::assertSent(function ($request) use ($body) {
            return $request->url() === 'https://8.8.8.8/h'
                && $request->body() === $body
                && hash_equals('sha256='.hash_hmac('sha256', $body, 'whsec_test'), $request->header('X-Naara-Signature')[0])
                && $request->header('X-Naara-Event')[0] === 'message.received'
                && $request->header('X-Naara-Delivery')[0] === 'd1';
        });
        $sub->refresh();
        $this->assertSame(0, $sub->consecutive_failures);
        $this->assertSame(200, $sub->last_status_code);
    }

    public function test_a_non_2xx_answer_throws_so_the_queue_retries_and_exhausted_retries_flip_the_status(): void
    {
        Http::fake(['*' => Http::response('nope', 500)]);
        [, $client] = $this->key();
        $sub = WebhookSubscription::create(['api_client_id' => $client->id, 'wallet_group_id' => $this->group->id, 'event_type' => 'call.completed', 'target_url' => 'https://8.8.8.8/h', 'secret' => 's', 'status' => 'active']);
        $job = new DeliverBusinessWebhookJob($sub->id, 'd', '{"type":"call.completed"}');

        try {
            $job->handle();
            $this->fail('expected the job to throw so it is retried');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('500', $e->getMessage());
        }
        $this->assertSame([30, 120, 600, 1800], $job->backoff());
        $this->assertSame(5, $job->tries);

        for ($i = 0; $i < WebhookSubscription::FAILURE_LIMIT; $i++) {
            $this->assertTrue($sub->fresh()->isActive());
            $job->failed(new \RuntimeException('HTTP 500'));
        }
        $this->assertSame(WebhookSubscription::FAILED, $sub->fresh()->status);
    }

    public function test_a_delivery_never_follows_a_target_that_stopped_being_public(): void
    {
        Http::fake();
        [, $client] = $this->key();
        $sub = WebhookSubscription::create(['api_client_id' => $client->id, 'wallet_group_id' => $this->group->id, 'event_type' => 'call.completed', 'target_url' => 'https://127.0.0.1/h', 'secret' => 's', 'status' => 'active']);

        (new DeliverBusinessWebhookJob($sub->id, 'd', '{}'))->handle();

        Http::assertNothingSent();
        $this->assertSame(WebhookSubscription::FAILED, $sub->fresh()->status);
    }

    public function test_events_are_queued_only_for_active_subscriptions_of_that_workspace_and_event(): void
    {
        Bus::fake([DeliverBusinessWebhookJob::class]);
        [, $client] = $this->key();
        $mk = fn (array $a) => WebhookSubscription::create($a + ['api_client_id' => $client->id, 'wallet_group_id' => $this->group->id, 'event_type' => 'call.completed', 'target_url' => 'https://8.8.8.8/h', 'secret' => 's', 'status' => 'active']);
        $mk([]);
        $mk(['status' => 'failed']);
        $mk(['event_type' => 'message.received']);

        WebhookEvents::fire('call.completed', $this->group->id, ['call_id' => 'CA1']);
        Bus::assertDispatchedTimes(DeliverBusinessWebhookJob::class, 1);

        WebhookEvents::fire('call.completed', $this->group->id + 99, []);
        WebhookEvents::fire('unknown.event', $this->group->id, []);
        Bus::assertDispatchedTimes(DeliverBusinessWebhookJob::class, 1);

        Setting::setValue('business.module.integrations', false, 'business');
        WebhookEvents::fire('call.completed', $this->group->id, []);
        Bus::assertDispatchedTimes(DeliverBusinessWebhookJob::class, 1);
    }

    public function test_the_real_event_points_fire_their_webhooks(): void
    {
        Bus::fake([DeliverBusinessWebhookJob::class]);
        [, $client] = $this->key();
        foreach (['call.completed', 'message.received'] as $ev) {
            WebhookSubscription::create(['api_client_id' => $client->id, 'wallet_group_id' => $this->group->id, 'event_type' => $ev, 'target_url' => 'https://8.8.8.8/h', 'secret' => 's', 'status' => 'active']);
        }

        // a finished flow call
        $flow = $this->flow($this->owner, $this->group, '+15550103');
        (new LogCallEventJob(['call_flow_id' => $flow->id, 'call_sid' => 'CA9', 'from' => '+1', 'to' => '+2', 'status' => 'ringing']))->handle();
        Bus::assertNotDispatched(DeliverBusinessWebhookJob::class); // ringing is not an outcome
        (new LogCallEventJob(['call_flow_id' => $flow->id, 'call_sid' => 'CA9', 'from' => '+1', 'to' => '+2', 'status' => 'answered']))->handle();
        Bus::assertDispatchedTimes(DeliverBusinessWebhookJob::class, 1);

        // an SMS on a workspace line
        $line = $this->line($this->owner, $this->group, '+15550009');
        (new RecordInboundMessageJob(userId: $this->owner->id, virtualNumberId: $line->id, fromNumber: '+15551', body: 'hello', attachmentUrl: null, providerRef: 'sms-1', provider: 'twilio'))->handle();
        Bus::assertDispatchedTimes(DeliverBusinessWebhookJob::class, 2);

        // a channel message
        $c = ChannelConnection::create(['wallet_group_id' => $this->group->id, 'channel' => 'telegram', 'external_id' => 'bot1', 'status' => 'active', 'label' => 'bot', 'credentials' => ['bot_token' => '123:ABC'], 'webhook_secret' => 'sec-1234567890']);
        (new RecordChannelMessageJob($c->id, 'tg-1', 'yo', 'tg-ref-1'))->handle();
        Bus::assertDispatchedTimes(DeliverBusinessWebhookJob::class, 3);
    }

    public function test_the_developer_portal_binds_a_key_and_manages_webhooks(): void
    {
        $this->actingAs($this->owner);
        $c = Livewire::test(DeveloperPortal::class)
            ->set('name', 'Zapier')->set('workspaceId', $this->group->id)->set('scopes', ['catalogue', 'contacts:read', 'webhook:subscribe'])
            ->call('create')->assertHasNoErrors();
        $client = ApiClient::firstOrFail();
        $this->assertSame($this->group->id, $client->wallet_group_id);

        $c->set("hookEvent.{$client->id}", 'call.completed')->set("hookUrl.{$client->id}", 'https://8.8.8.8/zap')->call('addWebhook', $client->id)->assertHasNoErrors();
        $this->assertNotNull($c->get('hookSecret'));
        $sub = WebhookSubscription::firstOrFail();

        $sub->forceFill(['status' => 'failed'])->save();
        $c->call('reenableWebhook', $sub->id);
        $this->assertSame('active', $sub->fresh()->status);

        $c->set("hookEvent.{$client->id}", 'call.completed')->set("hookUrl.{$client->id}", 'https://127.0.0.1/zap')->call('addWebhook', $client->id)->assertHasErrors(["hookUrl.{$client->id}"]);

        $c->call('removeWebhook', $sub->id);
        $this->assertSame(0, WebhookSubscription::count());
    }

    public function test_someone_else_cannot_touch_another_persons_webhook_from_the_portal(): void
    {
        [, $client] = $this->key();
        $sub = WebhookSubscription::create(['api_client_id' => $client->id, 'wallet_group_id' => $this->group->id, 'event_type' => 'call.completed', 'target_url' => 'https://8.8.8.8/h', 'secret' => 's', 'status' => 'failed']);

        $this->actingAs(User::factory()->create(['is_active' => true, 'email_verified_at' => now()]));
        Livewire::test(DeveloperPortal::class)->call('reenableWebhook', $sub->id)->call('removeWebhook', $sub->id);
        $this->assertSame('failed', $sub->fresh()->status);
        $this->assertSame(1, WebhookSubscription::count());
    }

    public function test_the_portal_does_not_offer_workspace_binding_while_the_module_is_off(): void
    {
        Setting::setValue('business.module.integrations', false, 'business');
        $this->actingAs($this->owner)->get('/developer')->assertOk()->assertDontSee('Business workspace');
    }

    public function test_the_zapier_app_definition_ships_with_the_three_triggers(): void
    {
        $base = base_path('docs/business-suite/zapier');
        foreach (['package.json', 'authentication.js', 'index.js', 'triggers/new_message.js', 'triggers/new_call.js', 'triggers/recording_ready.js', 'triggers/_restHook.js', 'README.md'] as $f) {
            $this->assertFileExists("$base/$f");
        }
        $this->assertStringContainsString('message.received', file_get_contents("$base/triggers/new_message.js"));
        $this->assertStringContainsString('call.completed', file_get_contents("$base/triggers/new_call.js"));
        $this->assertStringContainsString('call.recording_ready', file_get_contents("$base/triggers/recording_ready.js"));
    }
}
