<?php

namespace Tests\Feature\N2N;

use App\Jobs\SendWebPushJob;
use App\Models\Contact;
use App\Models\N2N\Message;
use App\Models\N2N\Participant;
use App\Models\N2N\RealtimeEvent;
use App\Models\Setting;
use App\Models\User;
use App\Services\N2N\Credit\UsageCredit;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Messaging\Disappearing;
use App\Services\N2N\Messaging\MessageService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Queue;

/** Composer extras on the server side (Gestures G4, MASTER-ONLY): "Send silently" and "Send and disappear after...". The browser pieces are covered by scripts/gestures/composer-extras.mjs. */
class ComposerExtrasTest extends N2NTestCase
{
    private function member(string $name = 'Member'): User
    {
        $u = $this->user(['email_verified_at' => now(), 'name' => $name]);
        $u->forceFill(['naara_id' => NaaraId::generate(), 'created_at' => now()->subDays(30)])->save();
        app(WalletService::class)->credit($u, 10, 'USD', ['reference' => 'f'.$u->id.uniqid()]);
        app(UsageCredit::class)->purchase($u, 10, 'b'.uniqid());

        return $u->fresh();
    }

    private function connect(User $a, User $b): void
    {
        Contact::create(['user_id' => $b->id, 'name' => 'A', 'phone_number' => '0803'.random_int(1000000, 9999999)])->forceFill(['linked_user_id' => $a->id])->saveQuietly();
    }

    /** @return array{0: User, 1: User, 2: int} */
    private function pair(): array
    {
        $a = $this->member('Ada');
        $b = $this->member('Bayo');
        $this->connect($a, $b);
        $this->connect($b, $a);
        $r = app(MessageService::class)->send($a, ['to' => $b->naara_id], 'c'.uniqid().'xxxx', 'hello');

        return [$a, $b, $r['conversation']->id];
    }

    private function send($from, int $cid, string $body, bool $silent = false, ?int $after = null): Message
    {
        return app(MessageService::class)->send($from, ['conversation_id' => $cid], 'c'.uniqid().'xxxx', $body, null, [], null, null, null, null, null, $silent, $after)['message'];
    }

    public function test_a_silent_send_is_delivered_and_counted_but_makes_no_tone_and_no_push(): void
    {
        [$a, $b, $cid] = $this->pair();
        Queue::fake();   // after the first, ordinary message
        $m = $this->send($a, $cid, 'quiet one', true);
        $this->assertSame('quiet one', $m->body);
        $event = RealtimeEvent::query()->where('user_id', $b->id)->where('type', 'message.new')->orderByDesc('id')->first();
        $this->assertNull($event->payload['tone']);
        $this->assertTrue($event->payload['muted']);
        Queue::assertNotPushed(SendWebPushJob::class);
        $this->assertFalse(\App\Services\N2N\Messaging\ConversationPrefs::isMuted(Participant::query()->where('conversation_id', $cid)->where('user_id', $b->id)->first()), 'it does not mute the chat');
        $this->send($a, $cid, 'loud again');
        $this->assertSame('message.naara', RealtimeEvent::query()->where('user_id', $b->id)->where('type', 'message.new')->orderByDesc('id')->first()->payload['tone']);
    }

    public function test_a_message_can_disappear_on_its_own_timer_only_from_the_offered_choices(): void
    {
        [$a, $b, $cid] = $this->pair();
        $this->assertNull($this->send($a, $cid, 'no timer')->expires_at);
        $m = $this->send($a, $cid, 'gone in an hour', false, 3600);
        $this->assertEqualsWithDelta(now()->addHour()->timestamp, $m->expires_at->timestamp, 5);
        $this->assertNull($this->send($a, $cid, 'odd value ignored', false, 1234)->expires_at, 'a value that is not on the list is ignored');
        // The chat's own timer and the message's timer: whichever ends first wins.
        $c = \App\Models\N2N\Conversation::query()->findOrFail($cid);
        $c->forceFill(['disappear_after' => 86400])->save();
        $this->assertEqualsWithDelta(now()->addHour()->timestamp, $this->send($a, $cid, 'sooner', false, 3600)->expires_at->timestamp, 5);
        $this->assertEqualsWithDelta(now()->addDay()->timestamp, $this->send($a, $cid, 'chat timer', false, 604800)->expires_at->timestamp, 5, 'the chat timer is shorter than 7 days');
    }

    public function test_the_per_message_timer_is_off_when_disappearing_messages_are_switched_off(): void
    {
        [$a, $b, $cid] = $this->pair();
        Setting::setValue('n2n.disappearing_enabled', false);
        $this->assertFalse(Disappearing::enabled());
        $this->assertNull($this->send($a, $cid, 'stays', false, 3600)->expires_at);
    }

    public function test_the_send_endpoint_reads_the_silent_and_timer_fields(): void
    {
        [$a, $b, $cid] = $this->pair();
        $meta = ['clientId' => 'ep-'.uniqid().'abcd', 'chat' => $cid, 'text' => 'via the endpoint', 'silent' => true, 'disappear_after' => 86400];
        $this->actingAs($a)->post(route('n2n.api.send', ['conversationId' => $cid]), ['meta' => json_encode($meta)])->assertStatus(201);
        $m = Message::query()->where('body', 'via the endpoint')->firstOrFail();
        $this->assertEqualsWithDelta(now()->addDay()->timestamp, $m->expires_at->timestamp, 5);
    }
}
