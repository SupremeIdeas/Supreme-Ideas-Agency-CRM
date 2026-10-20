<?php

namespace Tests\Feature\Omni;

use App\Jobs\Omni\ProcessOmniRawEvent;
use App\Models\ChannelConnection;
use App\Models\Omni\OmniConversation;
use App\Models\Omni\OmniMessage;
use App\Models\Omni\OmniRawEvent;
use App\Models\User;
use App\Models\WalletGroup;
use App\Services\Inbox\ChannelSendException;
use App\Services\Omni\Adapters\TelegramAdapter;
use App\Services\Omni\Dto\OutboundDraft;
use App\Services\Omni\Outbox;
use App\Services\Omni\Pipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/** The Telegram adapter against payloads shaped exactly like the official Bot API reference (Update, Message, User, Chat, PhotoSize, Document, Voice). */
class TelegramAdapterTest extends TestCase
{
    use RefreshDatabase;

    private ChannelConnection $conn;

    protected function setUp(): void
    {
        parent::setUp();
        $g = WalletGroup::create(['owner_user_id' => User::factory()->create()->id, 'name' => 'Acme', 'kind' => WalletGroup::KIND_BUSINESS]);
        $this->conn = ChannelConnection::create(['wallet_group_id' => $g->id, 'channel' => 'telegram', 'external_id' => '777', 'status' => 'active', 'label' => 'bot', 'credentials' => ['bot_token' => '123:ABC-token'], 'webhook_secret' => 'tg_Secret-0123456789']);
        $this->conn->forceFill(['public_id' => (string) Str::uuid()])->save();
    }

    private function update(array $message, int $id = 1001): array
    {
        return ['update_id' => $id, 'message' => $message + ['message_id' => 55, 'date' => 1792410000, 'chat' => ['id' => 424242, 'type' => 'private', 'first_name' => 'Ada'], 'from' => ['id' => 424242, 'is_bot' => false, 'first_name' => 'Ada', 'last_name' => 'Obi', 'username' => 'ada', 'language_code' => 'en']]];
    }

    private function adapter(): TelegramAdapter
    {
        return app(\App\Services\Omni\AdapterRegistry::class)->for('telegram');
    }

    public function test_a_text_update_becomes_one_message_event(): void
    {
        $e = $this->adapter()->events($this->update(['text' => 'Hello there']));
        $this->assertCount(1, $e);
        $this->assertSame('424242', $e[0]->externalId);
        $this->assertSame('55', $e[0]->providerMessageId);
        $this->assertSame('Hello there', $e[0]->text);
        $this->assertSame('Ada Obi', $e[0]->name);
        $this->assertSame(1792410000, $e[0]->at->timestamp);
    }

    public function test_everything_that_is_not_a_private_human_message_is_ignored(): void
    {
        $a = $this->adapter();
        $this->assertSame([], $a->events(['update_id' => 1, 'edited_message' => ['message_id' => 1, 'chat' => ['id' => 1, 'type' => 'private'], 'date' => 1, 'text' => 'x']]));
        $this->assertSame([], $a->events(['update_id' => 2, 'callback_query' => ['id' => 'x']]));
        $this->assertSame([], $a->events($this->update(['chat' => ['id' => -100, 'type' => 'group'], 'text' => 'group chatter'])));
        $this->assertSame([], $a->events($this->update(['from' => ['id' => 9, 'is_bot' => true, 'first_name' => 'Bot'], 'text' => 'from a bot'])));
        $this->assertSame([], $a->events($this->update(['new_chat_members' => [['id' => 1]]])), 'a service message has nothing to show');
    }

    public function test_attachments_are_recorded_as_descriptors_and_the_caption_is_the_text(): void
    {
        $e = $this->adapter()->events($this->update(['caption' => 'My receipt', 'photo' => [
            ['file_id' => 'small', 'file_unique_id' => 'u1', 'width' => 90, 'height' => 60, 'file_size' => 1500],
            ['file_id' => 'large', 'file_unique_id' => 'u2', 'width' => 1280, 'height' => 853, 'file_size' => 90000],
        ]]));
        $this->assertSame('My receipt', $e[0]->text);
        $this->assertSame([['type' => 'image', 'file_id' => 'large', 'size' => 90000]], $e[0]->media);

        $d = $this->adapter()->events($this->update(['document' => ['file_id' => 'doc1', 'file_unique_id' => 'u', 'file_name' => 'invoice.pdf', 'mime_type' => 'application/pdf', 'file_size' => 2048]]));
        $this->assertNull($d[0]->text);
        $this->assertSame([['type' => 'file', 'file_id' => 'doc1', 'name' => 'invoice.pdf', 'mime' => 'application/pdf', 'size' => 2048]], $d[0]->media);

        $v = $this->adapter()->events($this->update(['voice' => ['file_id' => 'v1', 'file_unique_id' => 'u', 'duration' => 4, 'mime_type' => 'audio/ogg']]));
        $this->assertSame('voice', $v[0]->media[0]['type']);
    }

    public function test_the_secret_token_header_is_the_only_proof_and_is_checked_in_constant_time(): void
    {
        $body = json_encode($this->update(['text' => 'hi']));
        $call = fn (?string $secret) => $this->call('POST', '/webhooks/omni/telegram/'.$this->conn->public_id, [], [], [], array_filter(['CONTENT_TYPE' => 'application/json', 'HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => $secret]), $body);
        Queue::fake();
        $call(null)->assertForbidden();
        $call('wrong')->assertForbidden();
        $this->assertSame(0, OmniRawEvent::query()->count());
        $call('tg_Secret-0123456789')->assertOk();
        $call('tg_Secret-0123456789')->assertOk()->assertJson(['duplicate' => true]);   // Telegram redelivers on a non-2xx or timeout: a replay is a no-op
        $this->assertSame(1, OmniRawEvent::query()->count());
        Queue::assertPushed(ProcessOmniRawEvent::class, 1);
    }

    public function test_telegram_end_to_end_inbound_then_reply(): void
    {
        Queue::fake();
        $body = json_encode($this->update(['text' => 'Do you open Sundays?']));
        $this->call('POST', '/webhooks/omni/telegram/'.$this->conn->public_id, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => 'tg_Secret-0123456789'], $body)->assertOk();
        $raw = OmniRawEvent::query()->firstOrFail();
        $this->assertTrue(app(Pipeline::class)->process($raw));
        $ws = (int) $this->conn->wallet_group_id;
        $conv = OmniConversation::query()->forWorkspace($ws)->firstOrFail();
        $this->assertSame('Ada Obi', $conv->contact->name);

        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 9001]])]);
        $m = app(Outbox::class)->queue($ws, $conv, 'Yes, from 10am.', [], null, 'reply-1');
        app(Outbox::class)->deliver($m->id);
        $this->assertSame('sent', $m->fresh()->status);
        $this->assertSame('9001', $m->fresh()->provider_message_id);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/bot123:ABC-token/sendMessage') && $r['chat_id'] === '424242' && $r['text'] === 'Yes, from 10am.');
    }

    public function test_send_failures_become_plain_reasons_and_never_leak_the_token(): void
    {
        $send = fn () => $this->adapter()->send($this->conn, new OutboundDraft('424242', 'Hi'));
        Http::fake(['api.telegram.org/*' => Http::sequence()
            ->push(['ok' => false, 'error_code' => 403, 'description' => 'Forbidden: bot was blocked by the user'], 403)
            ->push(['ok' => false, 'error_code' => 429, 'description' => 'Too Many Requests: retry after 7', 'parameters' => ['retry_after' => 7]], 429)]);
        try {
            $send();
            $this->fail('expected a refusal');
        } catch (ChannelSendException $e) {
            $this->assertStringContainsString('blocked the bot', $e->getMessage());
            $this->assertStringNotContainsString('123:ABC', $e->getMessage());
        }
        try {
            $send();
            $this->fail('expected a refusal');
        } catch (ChannelSendException $e) {
            $this->assertStringContainsString('retry after 7 s', $e->getMessage());
        }
        $this->expectException(ChannelSendException::class);
        $this->adapter()->send($this->conn, new OutboundDraft('424242', '   '));
    }

    public function test_a_telegram_reply_never_needs_the_24_hour_window(): void
    {
        $this->assertFalse($this->adapter()->requiresWindow());
    }
}
