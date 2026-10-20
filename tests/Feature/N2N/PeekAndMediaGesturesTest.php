<?php

namespace Tests\Feature\N2N;

use App\Models\N2N\Attachment;
use App\Models\N2N\Conversation;
use App\Models\N2N\Message;
use App\Models\N2N\MessageHide;
use App\Models\N2N\Participant;
use App\Models\User;
use App\Services\N2N\Identity\NaaraId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** The peek card, the new gesture counters and Range support for media (Gestures blueprint, MASTER-ONLY). */
class PeekAndMediaGesturesTest extends N2NTestCase
{
    private User $a;

    private User $b;

    private int $cid;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['a', 'b'] as $k) {
            $u = $this->user(['email_verified_at' => now()]);
            $u->forceFill(['naara_id' => NaaraId::generate()])->save();
            $this->$k = $u->fresh();
        }
        $c = Conversation::query()->create(['type' => 'direct', 'status' => 'active']);
        foreach ([$this->a, $this->b] as $u) {
            Participant::query()->create(['conversation_id' => $c->id, 'user_id' => $u->id]);
        }
        $this->cid = $c->id;
    }

    private function say(User $from, string $text, string $type = 'text'): Message
    {
        $m = Message::query()->create(['conversation_id' => $this->cid, 'sender_id' => $from->id, 'client_id' => uniqid('k'), 'type' => $type, 'body' => $text, 'created_at' => now()]);
        Conversation::query()->whereKey($this->cid)->update(['last_message_id' => $m->id]);

        return $m;
    }

    public function test_a_member_sees_the_last_five_messages_oldest_first_as_plain_text(): void
    {
        foreach (range(1, 7) as $i) {
            $this->say($i % 2 ? $this->b : $this->a, "message $i");
        }
        $this->say($this->b, '', 'voice');

        $json = $this->actingAs($this->a)->getJson(route('n2n.api.peek', $this->cid))->assertOk()->json();

        $this->assertFalse($json['locked']);
        $this->assertCount(5, $json['messages']);
        $this->assertSame(['message 4', 'message 5', 'message 6', 'message 7', 'Voice message'], array_column($json['messages'], 'text'));
        $this->assertTrue($json['messages'][1]['mine'] === false && $json['messages'][2]['mine'] === true);
    }

    public function test_it_never_returns_html_and_hides_what_the_member_hid_or_lost(): void
    {
        $keep = $this->say($this->b, '**bold** and <script>alert(1)</script>');
        $hidden = $this->say($this->b, 'secret I deleted for me');
        MessageHide::query()->create(['message_id' => $hidden->id, 'user_id' => $this->a->id]);
        $gone = $this->say($this->b, 'was here');
        $gone->forceFill(['deleted_for_all_at' => now()])->save();

        $texts = array_column($this->actingAs($this->a)->getJson(route('n2n.api.peek', $this->cid))->json('messages'), 'text');

        $this->assertNotContains('secret I deleted for me', $texts);
        $this->assertContains('Message deleted', $texts);
        $this->assertStringNotContainsString('**bold**', implode('|', $texts));
        $this->assertContains('bold and <script>alert(1)</script>', $texts);   // plain text: the card renders it with x-text, never as HTML
        $this->assertNotNull($keep);
    }

    public function test_a_stranger_and_a_missing_chat_answer_identically(): void
    {
        $this->say($this->b, 'private');
        $stranger = $this->user();

        $this->actingAs($stranger)->getJson(route('n2n.api.peek', $this->cid))->assertNotFound();
        $this->actingAs($stranger)->getJson(route('n2n.api.peek', 99999))->assertNotFound();
        $this->assertNotSame(200, $this->getJson(route('n2n.api.peek', $this->cid))->status());   // a guest gets nothing
    }

    public function test_a_locked_chat_is_never_previewed(): void
    {
        $this->say($this->b, 'locked away');
        Participant::query()->where('conversation_id', $this->cid)->where('user_id', $this->a->id)->update(['locked_at' => now()]);

        $this->actingAs($this->a)->getJson(route('n2n.api.peek', $this->cid))->assertOk()->assertJson(['locked' => true, 'messages' => []]);
    }

    public function test_the_new_gesture_counters_are_accepted_and_unknown_ones_refused(): void
    {
        $rows = [['g' => 'peek', 'o' => 'fired', 'd' => 'touch', 'n' => 2], ['g' => 'drag_select', 'o' => 'committed', 'd' => 'mouse', 'n' => 1], ['g' => 'lightbox_zoom', 'o' => 'committed'], ['g' => 'made_up', 'o' => 'fired']];
        $this->actingAs($this->a)->postJson(route('n2n.api.gesture-stat'), ['s' => $rows])->assertOk();

        $this->assertSame(2, (int) DB::table('n2n_gesture_stats')->where('gesture', 'peek')->value('n'));
        $this->assertSame(1, DB::table('n2n_gesture_stats')->where('gesture', 'drag_select')->where('device', 'mouse')->count());
        $this->assertSame(0, DB::table('n2n_gesture_stats')->where('gesture', 'made_up')->count());
    }

    public function test_media_supports_range_requests_so_a_voice_note_can_be_seeked(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('n2n/t/vn.wav', str_repeat('0123456789', 100));
        $m = $this->say($this->b, '', 'voice');
        $att = Attachment::query()->create(['message_id' => $m->id, 'uploader_id' => $this->b->id, 'kind' => 'voice', 'disk' => 'local', 'storage_key' => 'n2n/t/vn.wav', 'mime' => 'audio/wav', 'bytes' => 1000, 'status' => Attachment::ATTACHED]);

        $full = $this->actingAs($this->a)->get(route('n2n.media', $att->id))->assertOk();
        $this->assertSame('bytes', $full->headers->get('Accept-Ranges'));
        $part = $this->actingAs($this->a)->get(route('n2n.media', $att->id), ['Range' => 'bytes=10-19'])->assertStatus(206);
        $this->assertSame('bytes 10-19/1000', $part->headers->get('Content-Range'));
        $this->assertStringContainsString('private', (string) $full->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('public', (string) $full->headers->get('Cache-Control'));
        $this->assertStringContainsString('sandbox', (string) $full->headers->get('Content-Security-Policy'));

        $this->actingAs($this->user())->get(route('n2n.media', $att->id))->assertNotFound();
    }
}
