<?php

namespace Tests\Feature\N2N;

use App\Jobs\SendWebPushJob;
use App\Livewire\N2N\Chat;
use App\Models\Contact;
use App\Models\MessageThread;
use App\Models\N2N\Block;
use App\Models\N2N\Conversation;
use App\Models\N2N\Message;
use App\Models\N2N\MessageReceipt;
use App\Models\N2N\Presence;
use App\Models\N2N\RealtimeEvent;
use App\Models\N2N\Report;
use App\Models\Setting;
use App\Models\User;
use App\Services\N2N\Credit\InsufficientUsageCredit;
use App\Services\N2N\Credit\UsageCredit;
use App\Services\N2N\Credit\UsageLedgerService;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Messaging\Inbox;
use App\Services\N2N\Messaging\LinkFilter;
use App\Services\N2N\Messaging\MessageService;
use App\Services\N2N\Messaging\MessagingException;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/** N2N Batch 6: messaging core. Authorisation, charging, idempotency, requests, blocks, filters (MASTER-ONLY). */
class MessagingTest extends N2NTestCase
{
    private function member(array $attrs = [], float $buyUsd = 10): User
    {
        $u = $this->user(array_merge(['email_verified_at' => now()], $attrs));
        $u->forceFill(['naara_id' => NaaraId::generate(), 'created_at' => now()->subDays(30)])->save();
        if ($buyUsd > 0) {
            app(WalletService::class)->credit($u, $buyUsd, 'USD', ['reference' => 'f'.$u->id.uniqid()]);
            app(UsageCredit::class)->purchase($u, $buyUsd, 'b'.uniqid());
        }

        return $u->fresh();
    }

    private function connect(User $a, User $b): void
    {
        Contact::create(['user_id' => $b->id, 'name' => 'A', 'phone_number' => '0803'.random_int(1000000, 9999999)])->forceFill(['linked_user_id' => $a->id])->saveQuietly();
    }

    private function send(User $from, $to, string $body = 'hello there', ?string $id = null): array
    {
        $target = is_int($to) ? ['conversation_id' => $to] : ['to' => $to instanceof User ? $to->naara_id : $to];

        return app(MessageService::class)->send($from, $target, $id ?? 'c'.uniqid().'xxxx', $body);
    }

    private function bal(User $u): int
    {
        return array_sum(app(UsageLedgerService::class)->balances($u->id));
    }

    // ---- link filter
    public function test_the_link_filter_catches_urls_domains_markdown_and_common_obfuscations(): void
    {
        foreach ([
            'visit https://evil.example/login', 'go to www.evil.com now', 'evil.com/pay', 'check evil.io', '[click here](http://x.y)', 'example dot com', 'example [dot] com',
            'ex ample . com', 'e x a m p l e . c o m', "evil\u{200B}.com", 'hxxp://evil.test', 'pаypal.com' /* cyrillic a */, 'visit my site: bit.ly/abc', 'pay at WWW . evil . NET',
        ] as $bad) {
            $this->assertNotNull(LinkFilter::problem($bad), 'should block: '.$bad);
        }
        foreach (['see you at 5.30pm', 'it costs 3.5 credits', 'Mr. Smith e.g. tomorrow', 'ok.thanks', 'hello. how are you? good.', 'I am free at 10.', 'call me on Monday'] as $ok) {
            $this->assertNull(LinkFilter::problem($ok), 'should allow: '.$ok);
        }
        $own = rtrim((string) config('app.url'), '/').'/u/frank';
        $this->assertNull(LinkFilter::problem('my profile '.$own), 'Naara-issued links are allowed');
        Setting::setValue('n2n.allowed_link_hosts', 'partner.example');
        $this->assertNull(LinkFilter::problem('see https://partner.example/page'));
    }

    // ---- send pipeline
    public function test_a_send_charges_half_a_credit_once_inserts_one_message_and_a_repeat_returns_the_same_one(): void
    {
        $a = $this->member(); $b = $this->member();
        $this->connect($a, $b);
        $before = $this->bal($a);

        $r1 = $this->send($a, $b, 'hi', 'same-client-id');
        $r2 = $this->send($a, $b, 'hi', 'same-client-id');

        $this->assertFalse($r1['replay']);
        $this->assertTrue($r2['replay']);
        $this->assertSame($r1['message']->id, $r2['message']->id);
        $this->assertSame($before - 50, $this->bal($a), 'charged once: 0.50 credit');
        $this->assertSame(1, Message::count());
        $this->assertSame('0.50', (string) $r1['message']->cost_credits);
    }

    public function test_insufficient_credit_inserts_nothing_and_tells_the_caller_what_it_needs(): void
    {
        $a = $this->member([], 0); $b = $this->member();
        $this->expectException(InsufficientUsageCredit::class);
        try {
            $this->send($a, $b);
        } finally {
            $this->assertSame(0, Message::count());
            $this->assertSame(0, Conversation::where('requested_by', $a->id)->where('last_message_id', '!=', null)->count());
        }
    }

    public function test_a_failure_after_the_charge_returns_the_charge(): void
    {
        $a = $this->member(); $b = $this->member();
        $this->connect($a, $b);
        $before = $this->bal($a);
        Message::creating(function () { throw new \RuntimeException('db down'); });
        try {
            $this->send($a, $b, 'x', 'will-fail-1');
            $this->fail('expected the insert to fail');
        } catch (\RuntimeException) {
        }
        Message::flushEventListeners();
        $this->assertSame($before, $this->bal($a), 'never charge without delivering');
        $this->assertSame(0, Message::count());
    }

    public function test_a_link_is_refused_with_a_clear_422_and_a_blank_or_oversized_message_too(): void
    {
        $a = $this->member(); $b = $this->member();
        try { $this->send($a, $b, 'see evil.com'); $this->fail(); } catch (MessagingException $e) { $this->assertSame(422, $e->status()); $this->assertSame('link_blocked', $e->reason); }
        try { $this->send($a, $b, '   '); $this->fail(); } catch (MessagingException $e) { $this->assertSame('invalid', $e->reason); }
        try { $this->send($a, $b, str_repeat('a', 4001)); $this->fail(); } catch (MessagingException $e) { $this->assertSame('invalid', $e->reason); }
        $this->assertSame(0, Message::count());
    }

    public function test_the_per_minute_rate_limit_and_the_new_conversations_per_day_cap_apply(): void
    {
        Setting::setValue('n2n.msg_per_min', 3);
        $a = $this->member(); $b = $this->member();
        $this->connect($a, $b);
        $cid = $this->send($a, $b)['conversation']->id;
        $this->send($a, $cid); $this->send($a, $cid);
        try { $this->send($a, $cid); $this->fail(); } catch (MessagingException $e) { $this->assertSame('rate_limited', $e->reason); }

        Setting::setValue('n2n.new_conv_per_day', 1);
        $c = $this->member(); $d = $this->member();
        $this->send($c, $d);
        $e2 = $this->member();
        try { $this->send($c, $e2); $this->fail(); } catch (MessagingException $e) { $this->assertSame('rate_limited', $e->reason); }
    }

    // ---- authorisation / IDOR
    public function test_a_stranger_gets_the_identical_refusal_for_a_real_and_a_missing_conversation(): void
    {
        $a = $this->member(); $b = $this->member(); $x = $this->member();
        $cid = $this->send($a, $b)['conversation']->id;
        $svc = app(MessageService::class);
        $msgs = [];
        foreach ([$cid, $cid + 999] as $id) {
            try { $svc->send($x, ['conversation_id' => $id], 'cl'.$id.'xxxxxx', 'hi'); } catch (MessagingException $e) { $msgs[] = [$e->reason, $e->getMessage(), $e->status()]; }
        }
        $this->assertCount(2, $msgs);
        $this->assertSame($msgs[0], $msgs[1], 'no existence oracle');
        $this->assertSame(403, $msgs[0][2]);
        foreach (['accept', 'decline', 'block', 'markRead'] as $m) {
            try { $svc->{$m}($x, $cid); $this->fail($m); } catch (MessagingException) { $this->addToAssertionCount(1); }
        }
        $this->assertNull(Block::first());
    }

    // ---- requests and privacy
    public function test_a_first_message_to_a_non_connection_is_a_request_the_sender_is_capped_on_until_accepted(): void
    {
        Setting::setValue('n2n.request_cap', 2);
        $a = $this->member(); $b = $this->member();
        $r = $this->send($a, $b, 'one');
        $cid = $r['conversation']->id;
        $this->assertSame(Conversation::REQUEST, $r['conversation']->status);
        $this->send($a, $cid, 'two');
        try { $this->send($a, $cid, 'three'); $this->fail(); } catch (MessagingException $e) { $this->assertSame('request_cap', $e->reason); }

        app(MessageService::class)->accept($b, $cid);
        $this->assertSame(Conversation::ACTIVE, Conversation::find($cid)->status);
        $this->assertSame('three-ok', $this->send($a, $cid, 'three-ok')['message']->body);
        $this->assertDatabaseHas('n2n_realtime_events', ['user_id' => $a->id, 'type' => 'conversation.accepted']);
    }

    public function test_replying_to_a_request_accepts_it_and_a_connection_skips_the_request_step(): void
    {
        $a = $this->member(); $b = $this->member(); $c = $this->member();
        $cid = $this->send($a, $b)['conversation']->id;
        $this->send($b, $cid, 'hello back');
        $this->assertSame(Conversation::ACTIVE, Conversation::find($cid)->status);

        $this->connect($a, $c);
        $this->assertSame(Conversation::ACTIVE, $this->send($a, $c)['conversation']->status, 'a known contact is delivered straight away');
    }

    public function test_declining_a_request_hides_it_for_the_recipient(): void
    {
        $a = $this->member(); $b = $this->member();
        $cid = $this->send($a, $b)['conversation']->id;
        $this->assertCount(1, app(Inbox::class)->entries($b, 'requests'));
        app(MessageService::class)->decline($b, $cid);
        $this->assertCount(0, app(Inbox::class)->entries($b, 'requests'));
        $this->assertCount(0, app(Inbox::class)->entries($b));
    }

    public function test_blocked_and_privacy_restricted_senders_look_delivered_but_reach_nobody(): void
    {
        $a = $this->member(); $b = $this->member(); $c = $this->member(['n2n_who_can_message' => 'nobody']); $d = $this->member(['n2n_who_can_message' => 'connections']);
        Block::create(['blocker_id' => $b->id, 'blocked_id' => $a->id, 'created_at' => now()]);
        $before = $this->bal($a);

        $r = $this->send($a, $b, 'let me in');
        $this->assertTrue($r['dropped']);
        $this->assertSame($before - 50, $this->bal($a), 'the sender is still charged: no balance oracle');
        $this->assertSame(0, MessageReceipt::count(), 'nobody is notified');
        $this->assertSame(0, RealtimeEvent::where('user_id', $b->id)->count());
        $this->assertCount(0, app(Inbox::class)->entries($b));
        $this->assertCount(1, app(Inbox::class)->entries($a), 'but the sender sees their own message');

        $this->assertTrue($this->send($a, $c)['dropped'], 'who-can-message = nobody');
        $this->assertTrue($this->send($a, $d)['dropped'], 'connections only, not connected');
        $this->connect($a, $d);
        $this->assertFalse($this->send($a, $d, 'now connected')['dropped']);
    }

    // ---- realtime, receipts, push
    public function test_the_recipient_gets_one_realtime_event_that_the_poll_driver_also_returns_and_the_sender_gets_a_sent_event(): void
    {
        $a = $this->member(); $b = $this->member();
        $this->connect($a, $b);
        $r = $this->send($a, $b, 'ping');

        $this->assertSame(1, RealtimeEvent::where('user_id', $b->id)->where('type', 'message.new')->count());
        $ev = $this->actingAs($b)->getJson('/n2n/realtime/sync?cursor=0')->json('events');
        $this->assertSame('message.new', $ev[0]['type']);
        $this->assertSame($r['conversation']->id, $ev[0]['payload']['conversation_id']);
        $this->assertSame('message.naara', $ev[0]['payload']['tone']);
        $this->assertStringNotContainsString('ping', json_encode($ev), 'events carry ids, never the message body');
        $this->assertSame(1, RealtimeEvent::where('user_id', $a->id)->where('type', 'message.sent')->count());
    }

    public function test_read_receipts_flow_to_the_sender_only_if_the_reader_allows_them(): void
    {
        $a = $this->member(); $b = $this->member(); $c = $this->member(['n2n_read_receipts' => false]);
        $this->connect($a, $b); $this->connect($a, $c);
        $cb = $this->send($a, $b)['conversation']->id; $cc = $this->send($a, $c)['conversation']->id;

        $this->assertSame(1, app(MessageService::class)->markRead($b, $cb));
        app(MessageService::class)->markRead($c, $cc);
        $this->assertSame(1, RealtimeEvent::where('user_id', $a->id)->where('type', 'message.read')->count(), 'only the reader who allows receipts');
        $this->assertSame(0, app(MessageService::class)->markRead($b, $cb), 'idempotent');
    }

    public function test_an_offline_recipient_gets_a_generic_web_push_and_an_online_one_does_not(): void
    {
        Queue::fake([SendWebPushJob::class]);
        $a = $this->member(); $off = $this->member(); $on = $this->member();
        $this->connect($a, $off); $this->connect($a, $on);
        Presence::create(['user_id' => $on->id, 'last_seen_at' => now()]);

        $this->send($a, $off, 'secret text'); $this->send($a, $on, 'other text');
        Queue::assertPushed(SendWebPushJob::class, 1);
        Queue::assertPushed(SendWebPushJob::class, fn ($j) => $j->userId === $off->id && ! str_contains(json_encode($j->payload), 'secret') && $j->payload['title'] === 'New message on Naara');

        Setting::setValue('n2n.push_preview', true);
        Queue::fake([SendWebPushJob::class]);
        $this->send($a, $off, 'previewed text');
        Queue::assertPushed(SendWebPushJob::class, fn ($j) => str_contains($j->payload['body'], 'previewed'));
    }

    // ---- inbox
    public function test_inbox_filters_unread_known_unknown_requests_naara_line_and_links_and_search_stays_inside_your_own_chats(): void
    {
        $me = $this->member(); $friend = $this->member(['name' => 'Friend']); $stranger = $this->member(['name' => 'Stranger']); $other1 = $this->member(); $other2 = $this->member();
        $this->connect($friend, $me);                               // `me` has `friend` in their contacts -> known
        Contact::where('user_id', $me->id)->delete();
        Contact::create(['user_id' => $me->id, 'name' => 'Friend', 'phone_number' => '08035550001'])->forceFill(['linked_user_id' => $friend->id])->saveQuietly();
        $this->send($friend, $me, 'hello from a friend');
        $this->send($stranger, $me, 'hello from a stranger');        // request
        $own = parse_url((string) config('app.url'), PHP_URL_HOST);
        $this->send($friend, $me, 'my profile http://'.$own.'/u/friend');
        $this->send($other1, $other2, 'private between others');
        MessageThread::create(['user_id' => $me->id, 'virtual_number_id' => null, 'counterpart_number' => '+15550001', 'last_body' => 'sms body', 'last_direction' => 'in', 'last_at' => now(), 'unread_count' => 2]);
        $inbox = app(Inbox::class);

        $this->assertCount(3, $inbox->entries($me), 'friend + the stranger as a flagged request + the line thread');
        $this->assertSame(['Friend'], array_column($inbox->entries($me, 'known'), 'title'));
        $this->assertCount(1, $inbox->entries($me, 'requests'));
        $this->assertCount(2, $inbox->entries($me, 'unread'), 'friend chat + the line thread');
        $this->assertSame(['line'], array_column($inbox->entries($me, 'line'), 'kind') ? ['line'] : []);
        $this->assertSame(['naara'], array_unique(array_column($inbox->entries($me, 'naara'), 'line')));
        $this->assertCount(1, $inbox->entries($me, 'links'), 'only chats with a Naara-issued link');
        $this->assertSame(['Friend'], array_column($inbox->entries($me, 'all', 'friend'), 'title'));
        $this->assertCount(0, $inbox->entries($me, 'all', 'private between'), 'search never reaches other people\'s conversations');
        $this->assertSame(3, $inbox->totalUnread($me), 'friend (2) + the request (1); Line SMS unread is counted by its own screen');
    }

    // ---- report / block
    public function test_a_report_keeps_a_short_excerpt_of_the_other_persons_messages_blocks_them_and_files_it_for_review(): void
    {
        $a = $this->member(); $b = $this->member();
        $this->connect($b, $a);   // not a request: the cap does not apply
        $cid = $this->send($a, $b, 'first')['conversation']->id;
        foreach (['one', 'two', 'three', 'four', 'five', 'six'] as $w) { $this->send($a, $cid, $w); }
        $this->send($b, $cid, 'a reply');

        $rep = app(MessageService::class)->report($b, $cid, 'scam', 'asked for money');
        $this->assertSame('open', $rep->status);
        $this->assertSame($a->id, $rep->reported_id);
        $this->assertCount(5, $rep->excerpt);
        $ex = $rep->excerpt;
        $this->assertSame('six', end($ex)['body']);
        $this->assertTrue(Block::where(['blocker_id' => $b->id, 'blocked_id' => $a->id])->exists());
        $this->assertTrue($this->send($a, $cid, 'after block')['dropped']);
        $this->assertSame(1, Report::count());
        app(MessageService::class)->unblock($b, $a->id);
        $this->assertFalse(Block::exists());
    }

    // ---- HTTP endpoint (the composer contract)
    public function test_the_composer_endpoint_accepts_meta_and_an_idempotency_key_and_maps_every_refusal_to_a_status(): void
    {
        $a = $this->member(); $b = $this->member();
        $this->connect($a, $b);
        $meta = fn ($text) => ['meta' => json_encode(['clientId' => 'ignored', 'text' => $text])];

        $ok = $this->actingAs($a)->postJson('/n2n/api/messages?to='.$b->naara_id, $meta('hello'), ['Idempotency-Key' => 'http-key-0001'])->assertCreated()->json();
        $this->assertTrue($ok['delivered']);
        $cid = $ok['conversation_id'];
        $this->actingAs($a)->postJson('/n2n/api/messages?to='.$b->naara_id, $meta('hello'), ['Idempotency-Key' => 'http-key-0001'])->assertOk();
        $this->assertSame(1, Message::count());

        $this->actingAs($a)->postJson("/n2n/api/conversations/$cid/messages", $meta('see evil.com'), ['Idempotency-Key' => 'http-key-0002'])->assertStatus(422)->assertJsonPath('error', 'link_blocked');
        $this->actingAs($a)->post("/n2n/api/conversations/$cid/messages", ['meta' => json_encode(['text' => 'x']), 'files' => [\Illuminate\Http\UploadedFile::fake()->image('p.jpg')]], ['Idempotency-Key' => 'http-key-0003', 'Accept' => 'application/json'])->assertStatus(403)->assertJsonPath('error', 'pro_required');   // media needs Naara Pro (Batch 7)
        $x = $this->member();
        $this->actingAs($x)->postJson("/n2n/api/conversations/$cid/messages", $meta('hi'), ['Idempotency-Key' => 'http-key-0004'])->assertForbidden();
        $this->actingAs($x)->postJson('/n2n/api/conversations/999999/messages', $meta('hi'), ['Idempotency-Key' => 'http-key-0005'])->assertForbidden();   // same as a real one
        $this->actingAs($a)->postJson("/n2n/api/conversations/$cid/messages", $meta('hi'), ['Idempotency-Key' => 'short'])->assertStatus(422);
        $this->actingAs($a)->postJson('/n2n/api/messages?to=@nobody123', $meta('hi'), ['Idempotency-Key' => 'http-key-0006'])->assertNotFound();

        $broke = $this->member([], 0); $this->connect($broke, $b);
        $this->actingAs($broke)->postJson('/n2n/api/messages?to='.$b->naara_id, $meta('hi'), ['Idempotency-Key' => 'http-key-0007'])->assertStatus(402)
            ->assertJsonPath('error', 'insufficient_credit')->assertJsonStructure(['needed', 'available', 'actions' => ['buy', 'ask']]);
    }

    // ---- the page
    public function test_the_chat_page_shows_the_inbox_opens_a_thread_marks_it_read_and_escapes_message_text(): void
    {
        $a = $this->member(['name' => 'Ada']); $b = $this->member(['name' => 'Bola']);
        $this->connect($a, $b);
        $r = $this->send($a, $b, '<script>alert(1)</script> & <b>bold</b>');

        $this->actingAs($b)->get('/n2n/chat')->assertOk()->assertSee('Naara chat');
        $lw = Livewire::actingAs($b)->test(Chat::class)->assertSee('Ada')->call('select', $r['conversation']->id)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertSee('Messages are encrypted in transit and at rest');
        $this->assertNotNull(MessageReceipt::first()->read_at, 'opening the thread reads it');
        // the copy may only ever NEGATE end-to-end encryption (docs/legal/N2N-LEGAL-WORDING.md), never claim it
        $this->assertStringNotContainsString('end-to-end', str_replace('not end-to-end encrypted', '', strtolower($lw->html())));
        $lw->assertSee('not end-to-end encrypted');
        $lw->assertSee('0.50 credit');
    }

    public function test_the_chat_page_cannot_open_someone_elses_conversation_and_handles_requests_block_and_report(): void
    {
        $a = $this->member(['name' => 'Ada']); $b = $this->member(['name' => 'Bola']); $x = $this->member();
        $cid = $this->send($a, $b, 'request me')['conversation']->id;

        Livewire::actingAs($x)->test(Chat::class, ['c' => $cid])->assertSet('c', null);
        $lw = Livewire::actingAs($b)->test(Chat::class)->call('select', $cid)->assertSee('Accept')->call('accept')->assertSee('Request accepted.');
        $this->assertSame(Conversation::ACTIVE, Conversation::find($cid)->status);
        $lw->set('reportReason', 'spam')->call('sendReport')->assertSet('c', null);
        $this->assertSame(1, Report::count());
        $this->assertTrue(Block::where('blocker_id', $b->id)->exists());
    }

    public function test_the_new_chat_sheet_follows_the_router_and_the_naara_path_still_starts_a_chat(): void
    {
        $a = $this->member(); $b = $this->member(['name' => 'Bola', 'username' => 'bola']);
        Livewire::actingAs($a)->test(Chat::class)->call('openNew')->assertDontSee('data-route-card', false)
            ->set('newTo', '@bola')->assertSee('Chatting with')->assertSee('Naara chat')->assertSee('Paid with NaaraCredit')
            ->call('startChat')->assertSet('startedTo', $b->naara_id)->assertSet('showNew', false);

        Livewire::actingAs($a)->test(Chat::class)->call('openNew')->set('newTo', 'nobody@nowhere.test')->assertSee('Invite them');
    }

    public function test_n2n_pages_say_coming_soon_and_the_api_is_404_when_the_feature_is_off(): void
    {
        Setting::setValue('n2n.enabled', false);
        $u = $this->member();
        $this->actingAs($u)->get('/n2n/chat')->assertOk()->assertSee('Naara chat is coming soon'); // a page load gets the friendly sheet; the API stays 404
        $this->actingAs($u)->postJson('/n2n/api/messages?to=x', [], ['Idempotency-Key' => 'abcdefgh1'])->assertNotFound();
    }
}
