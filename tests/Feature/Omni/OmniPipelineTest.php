<?php

namespace Tests\Feature\Omni;

use App\Jobs\Omni\ProcessOmniRawEvent;
use App\Jobs\Omni\SendOmniMessage;
use App\Models\ChannelConnection;
use App\Models\Omni\OmniContact;
use App\Models\Omni\OmniConversation;
use App\Models\Omni\OmniMessage;
use App\Models\Omni\OmniRawEvent;
use App\Models\User;
use App\Models\WalletGroup;
use App\Services\Omni\Adapters\FakeChannelAdapter;
use App\Services\Omni\Outbox;
use App\Services\Omni\WindowClosedException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Naara Respond omni core, proven end to end on the FakeChannel: signed webhook, replay, out-of-order status, window rule, failed send, isolation. */
class OmniPipelineTest extends TestCase
{
    use RefreshDatabase;

    private array $conn = [];

    protected function setUp(): void
    {
        parent::setUp();
        FakeChannelAdapter::$sent = [];
        foreach (['a', 'b'] as $k) {
            $g = WalletGroup::create(['owner_user_id' => User::factory()->create()->id, 'name' => 'Workspace '.$k, 'kind' => WalletGroup::KIND_BUSINESS]);
            $this->conn[$k] = ChannelConnection::create(['wallet_group_id' => $g->id, 'channel' => 'fake', 'external_id' => 'fake-'.$k, 'status' => 'active', 'label' => $k, 'credentials' => [], 'webhook_secret' => 'secret-'.$k.'-1234567890']);
            $this->conn[$k]->forceFill(['public_id' => (string) Str::uuid()])->save();
        }
    }

    private function hook(string $k, array $body, ?string $secret = null)
    {
        $c = $this->conn[$k];
        $raw = json_encode($body);

        return $this->call('POST', "/webhooks/omni/fake/{$c->public_id}", [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_FAKE_SIGNATURE' => hash_hmac('sha256', $raw, $secret ?? (string) $c->webhook_secret)], $raw);
    }

    private function drain(): void
    {
        foreach (OmniRawEvent::query()->where('status', 'pending')->orderBy('id')->get() as $r) {
            (new ProcessOmniRawEvent($r->id))->handle(app(\App\Services\Omni\Pipeline::class));
        }
    }

    public function test_a_signed_message_becomes_a_contact_a_conversation_and_a_message(): void
    {
        Queue::fake();
        $this->hook('a', ['id' => 'e1', 'events' => [['type' => 'message', 'from' => 'c-1', 'mid' => 'm1', 'text' => 'Hello', 'name' => 'Ada', 'phone' => '+2348000000001']]])->assertOk();
        Queue::assertPushed(ProcessOmniRawEvent::class);
        $this->drain();
        $ws = $this->conn['a']->wallet_group_id;
        $conv = OmniConversation::query()->forWorkspace($ws)->firstOrFail();
        $this->assertSame('Ada', $conv->contact->name);
        $this->assertSame(1, $conv->unread);
        $this->assertTrue($conv->windowOpen());
        $this->assertSame('Hello', OmniMessage::query()->forWorkspace($ws)->firstOrFail()->body);
    }

    public function test_a_bad_signature_is_refused_and_stores_nothing_and_an_unknown_connection_is_404(): void
    {
        $this->hook('a', ['id' => 'e1', 'events' => []], 'wrong-secret')->assertForbidden();
        $this->assertSame(0, OmniRawEvent::query()->count());
        $this->call('POST', '/webhooks/omni/fake/'.Str::uuid(), [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}')->assertNotFound();
    }

    public function test_a_replayed_webhook_and_a_duplicate_message_id_change_nothing(): void
    {
        Queue::fake();
        $body = ['id' => 'e1', 'events' => [['type' => 'message', 'from' => 'c-1', 'mid' => 'm1', 'text' => 'Hello']]];
        $this->hook('a', $body)->assertOk();
        $this->hook('a', $body)->assertOk()->assertJson(['duplicate' => true]);
        $this->hook('a', ['id' => 'e2', 'events' => $body['events']])->assertOk();   // a new delivery carrying the same provider message id
        $this->drain();
        $this->assertSame(1, OmniMessage::query()->count());
        $this->assertSame(1, OmniContact::query()->count());
    }

    public function test_the_same_person_on_two_channels_is_one_contact(): void
    {
        Queue::fake();
        $this->hook('a', ['id' => 'e1', 'events' => [['type' => 'message', 'from' => 'c-1', 'mid' => 'm1', 'text' => 'Hi', 'phone' => '+2348000000001']]]);
        $this->hook('a', ['id' => 'e2', 'events' => [['type' => 'message', 'from' => 'other-id', 'mid' => 'm2', 'text' => 'Hi again', 'phone' => '+2348000000001']]]);
        $this->drain();
        $this->assertSame(1, OmniContact::query()->count());
        $this->assertSame(2, \App\Models\Omni\OmniIdentity::query()->count());
    }

    public function test_replies_obey_the_24_hour_window_and_the_idempotency_key(): void
    {
        Queue::fake();
        $this->hook('a', ['id' => 'e1', 'events' => [['type' => 'message', 'from' => 'c-1', 'mid' => 'm1', 'text' => 'Hello']]]);
        $this->drain();
        $ws = (int) $this->conn['a']->wallet_group_id;
        $conv = OmniConversation::query()->forWorkspace($ws)->firstOrFail();
        $outbox = app(Outbox::class);

        $m1 = $outbox->queue($ws, $conv, 'Welcome', [], null, 'key-1');
        $m2 = $outbox->queue($ws, $conv, 'Welcome', [], null, 'key-1');
        $this->assertSame($m1->id, $m2->id, 'a retried request makes one message');
        Queue::assertPushed(SendOmniMessage::class, 1);

        $outbox->deliver($m1->id);
        $outbox->deliver($m1->id);
        $this->assertCount(1, FakeChannelAdapter::$sent, 'delivered once even if the job runs twice');
        $this->assertSame('sent', $m1->fresh()->status);

        $conv->forceFill(['last_inbound_at' => now()->subHours(25)])->save();
        $this->expectException(WindowClosedException::class);
        $outbox->queue($ws, $conv->fresh(), 'Too late', [], null, 'key-2');
    }

    public function test_statuses_only_move_forward_and_one_that_beats_its_message_waits(): void
    {
        Queue::fake();
        $this->hook('a', ['id' => 'e1', 'events' => [['type' => 'message', 'from' => 'c-1', 'mid' => 'm1', 'text' => 'Hello']]]);
        $this->drain();
        $ws = (int) $this->conn['a']->wallet_group_id;
        $conv = OmniConversation::query()->forWorkspace($ws)->firstOrFail();
        $m = app(Outbox::class)->queue($ws, $conv, 'Reply', [], null, 'k');
        // read arrives BEFORE we have recorded the provider id: it must wait, not be lost
        $this->hook('a', ['id' => 's0', 'events' => [['type' => 'status', 'from' => 'c-1', 'mid' => 'fake-1', 'status' => 'read']]]);
        $raw = OmniRawEvent::query()->where('provider_event_id', 's0')->firstOrFail();
        $this->assertFalse(app(\App\Services\Omni\Pipeline::class)->process($raw));
        $this->assertSame('pending', $raw->fresh()->status);

        app(Outbox::class)->deliver($m->id);
        $this->assertTrue(app(\App\Services\Omni\Pipeline::class)->process($raw->fresh()));
        $this->assertSame('read', $m->fresh()->status);

        $this->hook('a', ['id' => 's1', 'events' => [['type' => 'status', 'from' => 'c-1', 'mid' => 'fake-1', 'status' => 'delivered']]]);
        $this->drain();
        $this->assertSame('read', $m->fresh()->status, 'a late delivered never undoes read');
    }

    public function test_a_refused_send_is_recorded_as_failed_and_not_retried(): void
    {
        Queue::fake();
        $this->conn['a']->forceFill(['credentials' => ['fail' => true]])->save();
        $this->hook('a', ['id' => 'e1', 'events' => [['type' => 'message', 'from' => 'c-1', 'mid' => 'm1', 'text' => 'Hello']]]);
        $this->drain();
        $ws = (int) $this->conn['a']->wallet_group_id;
        $m = app(Outbox::class)->queue($ws, OmniConversation::query()->forWorkspace($ws)->firstOrFail(), 'Reply', [], null, 'k');
        app(Outbox::class)->deliver($m->id);
        $this->assertSame('failed', $m->fresh()->status);
        $this->assertNotEmpty($m->fresh()->error);
        app(Outbox::class)->deliver($m->id);
        $this->assertCount(0, FakeChannelAdapter::$sent);
    }

    public function test_one_workspace_can_never_reach_another_workspaces_data(): void
    {
        Queue::fake();
        $this->hook('a', ['id' => 'e1', 'events' => [['type' => 'message', 'from' => 'c-1', 'mid' => 'm1', 'text' => 'Secret of A']]]);
        $this->hook('b', ['id' => 'e2', 'events' => [['type' => 'message', 'from' => 'c-1', 'mid' => 'm1', 'text' => 'Secret of B']]]);
        $this->drain();
        $a = (int) $this->conn['a']->wallet_group_id;
        $b = (int) $this->conn['b']->wallet_group_id;
        $this->assertSame(['Secret of A'], OmniMessage::query()->forWorkspace($a)->pluck('body')->all());
        $this->assertSame(['Secret of B'], OmniMessage::query()->forWorkspace($b)->pluck('body')->all());
        $this->assertSame(2, OmniContact::query()->count(), 'the same external id in two workspaces is two contacts');

        $convA = OmniConversation::query()->forWorkspace($a)->firstOrFail();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        app(Outbox::class)->queue($b, $convA, 'Hijack', [], null, 'x');   // workspace B cannot send into A's conversation
    }
}
