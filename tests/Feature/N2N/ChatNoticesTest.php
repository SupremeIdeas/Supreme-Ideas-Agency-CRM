<?php

namespace Tests\Feature\N2N;

use App\Models\N2N\ChatNotice;
use App\Models\Setting;
use App\Services\N2N\Messaging\ChatNotices;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatNoticesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guard_blocks_end_to_end_claims_while_e2ee_is_off(): void
    {
        $g = new ChatNotices;
        foreach (['Your chats are end-to-end encrypted', 'Messages use E2EE', 'Nobody else can read this', 'Zero-knowledge privacy'] as $t) {
            $this->assertNotEmpty($g->problems('Privacy', $t), $t);
        }
        $this->assertSame([], $g->problems('Privacy', 'Chats are encrypted in transit and at rest.'));
    }

    public function test_guard_allows_the_claim_only_when_the_flag_is_really_on(): void
    {
        Setting::setValue('n2n.e2ee_enabled', true);
        $this->assertSame([], (new ChatNotices)->problems('Privacy', 'End-to-end encrypted'));
    }

    public function test_notice_is_live_in_window_seen_once_and_stays_dismissed(): void
    {
        $u = User::factory()->create();
        $live = ChatNotice::create(['title' => 'Hi', 'body' => 'Welcome', 'active' => true]);
        ChatNotice::create(['title' => 'Later', 'body' => 'x', 'active' => true, 'starts_at' => now()->addDay()]);
        ChatNotice::create(['title' => 'Off', 'body' => 'x', 'active' => false]);
        $svc = new ChatNotices;
        $this->assertSame([$live->id], $svc->forMember($u->id)->pluck('id')->all());
        $svc->forMember($u->id);
        $this->assertSame(['seen' => 1, 'dismissed' => 0], $svc->stats($live->id));
        $svc->dismiss($u->id, $live->id);
        $this->assertCount(0, $svc->forMember($u->id));
        $this->assertSame(1, $svc->stats($live->id)['dismissed']);
    }

    public function test_non_dismissible_notice_cannot_be_dismissed(): void
    {
        $u = User::factory()->create();
        $n = ChatNotice::create(['title' => 'Must', 'body' => 'x', 'dismissible' => false]);
        $svc = new ChatNotices;
        $svc->dismiss($u->id, $n->id);
        $this->assertCount(1, $svc->forMember($u->id));
    }
}
