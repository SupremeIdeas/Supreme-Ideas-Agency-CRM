<?php

namespace Tests\Feature\N2N;

use App\Livewire\N2N\People;
use App\Livewire\N2N\Updates;
use App\Models\BrandPartner;
use App\Models\BrandPartnerFollower;
use App\Models\BrandPartnerImage;
use App\Models\N2N\Block;
use App\Models\N2N\CreditGift;
use App\Models\N2N\Follow;
use App\Models\N2N\Invite;
use App\Models\N2N\Message;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\N2NFollowNotice;
use App\Services\N2N\Connections\Connections;
use App\Services\N2N\Connections\Invites;
use App\Services\N2N\Credit\UsageLedgerService;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Updates\UpdatesService;
use App\Support\N2N\N2NNav;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/** N2N Batch 9: follow graph, connection rule, invites, Updates visibility, and the N2N shell navigation (MASTER-ONLY). */
class ConnectionsTest extends N2NTestCase
{
    private function member(): User
    {
        return $this->user(['naara_id' => NaaraId::generate(), 'username' => 'u'.substr(md5(uniqid()), 0, 8)]);
    }

    private function g(): Connections
    {
        return app(Connections::class);
    }

    private function credit(User $u, int $cents = 100000): void
    {
        app(UsageLedgerService::class)->apply([[$u->id, 'shareable', $cents, 'seed', 'seed:'.$u->id.uniqid(), null, []]]);
    }

    private function proBrand(User $owner, int $images = 2): BrandPartner
    {
        $b = BrandPartner::create(['owner_user_id' => $owner->id, 'brand_name' => 'Brand '.$owner->id, 'listing_status' => 'active', 'is_active' => true, 'pro_granted_at' => now()]);
        for ($i = 0; $i < $images; $i++) {
            BrandPartnerImage::create(['brand_partner_id' => $b->id, 'image_path' => "https://cdn.test/{$owner->id}-{$i}.webp", 'caption' => "Cap $i", 'sort_order' => $i]);
        }

        return $b;
    }

    // ---- graph rule -----------------------------------------------------------------------------------------------

    public function test_follow_only_is_not_a_connection_and_follow_back_is(): void
    {
        [$a, $b] = [$this->member(), $this->member()];
        $this->g()->follow($a, $b);
        $this->assertFalse($this->g()->connected($a->id, $b->id));
        $this->assertFalse($this->g()->connected($b->id, $a->id));
        $this->assertTrue($this->g()->follow($b, $a)['connected']);
        $this->assertTrue($this->g()->connected($a->id, $b->id));
        $this->assertTrue($this->g()->connected($b->id, $a->id));
    }

    public function test_unfollow_removes_the_connection_immediately(): void
    {
        [$a, $b] = [$this->member(), $this->member()];
        $this->g()->follow($a, $b);
        $this->g()->follow($b, $a);
        $this->g()->unfollow($b, $a);
        $this->assertFalse($this->g()->connected($a->id, $b->id));
        $this->assertNotContains($b->id, $this->g()->connectedIds($a->id));
    }

    public function test_user_following_a_brand_alone_is_not_connected_but_owner_follow_back_is(): void
    {
        [$user, $owner] = [$this->member(), $this->member()];
        $brand = $this->proBrand($owner);
        BrandPartnerFollower::create(['brand_partner_id' => $brand->id, 'user_id' => $user->id]);
        $this->assertFalse($this->g()->connected($user->id, $owner->id), 'following a brand alone never connects');
        $this->assertSame([], $this->g()->connectedIds($user->id));
        $this->g()->follow($owner, $user);                                  // owner follows the user back
        $this->assertTrue($this->g()->connected($user->id, $owner->id));
        $this->assertTrue($this->g()->connected($owner->id, $user->id));
        $this->assertContains($owner->id, $this->g()->connectedIds($user->id));
        $this->assertContains($user->id, $this->g()->connectedIds($owner->id));
        BrandPartnerFollower::query()->delete();                            // the user disconnects from the brand
        $this->assertFalse($this->g()->connected($user->id, $owner->id));
    }

    public function test_owner_following_a_user_who_does_not_follow_the_brand_is_not_a_connection(): void
    {
        [$user, $owner] = [$this->member(), $this->member()];
        $this->proBrand($owner);
        $this->g()->follow($owner, $user);
        $this->assertFalse($this->g()->connected($user->id, $owner->id));
    }

    public function test_connected_ids_matches_the_pairwise_rule(): void
    {
        $me = $this->member();
        $mutual = $this->member();
        $oneWay = $this->member();
        $this->g()->follow($me, $mutual); $this->g()->follow($mutual, $me);
        $this->g()->follow($me, $oneWay);
        $this->assertSame([$mutual->id], $this->g()->connectedIds($me->id));
    }

    public function test_blocked_follows_do_nothing_and_look_like_success(): void
    {
        [$a, $b] = [$this->member(), $this->member()];
        Block::create(['blocker_id' => $b->id, 'blocked_id' => $a->id, 'created_at' => now()]);
        $res = $this->g()->follow($a, $b);
        $this->assertTrue($res['ok']);
        $this->assertFalse($res['created']);
        $this->assertSame(0, Follow::count());
    }

    public function test_cannot_follow_self_or_an_inactive_account_and_the_daily_cap_applies(): void
    {
        $a = $this->member();
        $this->assertFalse($this->g()->follow($a, $a)['ok']);
        $this->assertFalse($this->g()->follow($a, $this->user(['naara_id' => NaaraId::generate(), 'is_active' => false]))['ok']);
        Setting::setValue('n2n.follow_per_day', 2);
        $this->g()->follow($a, $this->member());
        $this->g()->follow($a, $this->member());
        $this->assertFalse($this->g()->follow($a, $this->member())['ok']);
    }

    public function test_follow_notices_go_out_once_per_day_and_skip_a_muted_conversation(): void
    {
        Notification::fake();
        [$a, $b] = [$this->member(), $this->member()];
        $this->g()->follow($a, $b);
        $this->g()->unfollow($a, $b);
        $this->g()->follow($a, $b);
        Notification::assertSentToTimes($b, N2NFollowNotice::class, 1);
        // a muted conversation between them: no notice
        Cache::flush();
        [$c, $d] = [$this->member(), $this->member()];
        $conv = \App\Models\N2N\Conversation::create(['type' => 'direct', 'pair_key' => \App\Models\N2N\Conversation::pairKey($c->id, $d->id), 'requested_by' => $c->id, 'status' => 'active']);
        foreach ([$c, $d] as $u) {
            \App\Models\N2N\Participant::create(['conversation_id' => $conv->id, 'user_id' => $u->id, 'muted_until' => $u->id === $d->id ? now()->addDay() : null]);
        }
        $this->g()->follow($c, $d);
        Notification::assertNotSentTo($d, N2NFollowNotice::class);
    }

    public function test_the_connection_feeds_the_existing_privacy_rule(): void
    {
        [$a, $b] = [$this->member(), $this->member()];
        $this->assertFalse(\App\Services\N2N\Messaging\Privacy::connected($a->id, $b->id));
        $this->g()->follow($a, $b);
        $this->g()->follow($b, $a);
        $this->assertTrue(\App\Services\N2N\Messaging\Privacy::connected($a->id, $b->id));
    }

    // ---- invites ----------------------------------------------------------------------------------------------------

    public function test_unresolved_email_gets_an_invite_and_resolved_is_told_to_chat(): void
    {
        Queue::fake();
        $me = $this->member();
        $other = $this->member();
        $other->forceFill(['n2n_find_by_email' => true])->save();
        $svc = app(Invites::class);
        $r = $svc->invite($me, 'stranger@example.com');
        $this->assertSame('sent', $r['status']);
        Queue::assertPushed(\App\Jobs\N2N\SendInviteJob::class, fn ($j) => $j->kind === 'email' && $j->to === 'stranger@example.com');
        $this->assertSame('exists', $svc->invite($me, $other->email)['status']);
        $this->assertSame('invalid', $svc->invite($me, 'not an address')['status']);
    }

    public function test_the_invite_stores_only_a_hash_and_is_not_repeated_within_a_day(): void
    {
        Queue::fake();
        $me = $this->member();
        $svc = app(Invites::class);
        $svc->invite($me, 'once@example.com');
        $svc->invite($me, 'once@example.com');
        Queue::assertPushed(\App\Jobs\N2N\SendInviteJob::class, 1);
        $row = Invite::first();
        $this->assertStringNotContainsString('once@example.com', json_encode($row->getAttributes()));
        Setting::setValue('n2n.invites_per_day', 1);
        $this->assertSame('limit', $svc->invite($me, 'two@example.com')['status']);
    }

    public function test_an_invite_with_credit_creates_a_pending_gift_with_escrow(): void
    {
        Queue::fake();
        $me = $this->member();
        $this->credit($me);
        $r = app(Invites::class)->invite($me, 'gift@example.com', 5, 'k-test-1');
        $this->assertSame('gift', $r['status'], $r['message']);
        $gift = CreditGift::first();
        $this->assertSame('pending', $gift->status);
        $this->assertSame($me->id, $gift->sender_id);
        $this->assertSame('5.00', (string) $gift->amount);
    }

    public function test_the_people_page_shows_invite_for_a_miss_and_chat_for_a_hit(): void
    {
        Queue::fake();
        $me = $this->member();
        $them = $this->member();
        $this->actingAs($me);
        Livewire::test(People::class)->set('q', 'nobody@example.com')->assertSet('miss', true)->assertSee('Send invite')->call('invite')->assertSet('message', 'Invite sent.');
        Livewire::test(People::class)->set('q', $them->naara_id)->assertSet('miss', false)->assertSee('Chat');
    }

    // ---- Updates: visibility across the four relationship states ------------------------------------------------------------

    public function test_updates_visibility_across_relationship_states(): void
    {
        $viewer = $this->member();
        $owner = $this->member();
        $brand = $this->proBrand($owner);
        $svc = app(UpdatesService::class);

        // 1. stranger: nothing
        $this->assertSame([], $svc->rings($viewer));
        $this->assertNull($svc->items($viewer, $brand->id));

        // 2. viewer follows the owner only: still nothing
        $this->g()->follow($viewer, $owner);
        $this->assertNull($svc->items($viewer, $brand->id));

        // 3. connected (owner follows back): ring + items
        $this->g()->follow($owner, $viewer);
        $this->assertCount(1, $svc->rings($viewer));
        $this->assertCount(2, $svc->items($viewer, $brand->id));

        // 4. Pro lapses: ring gone on the next request
        $brand->forceFill(['pro_granted_at' => null])->save();
        \App\Services\N2N\Media\NaaraPro::bust($owner->id);
        $this->assertSame([], $svc->rings($viewer));
        $this->assertNull($svc->items($viewer, $brand->id));
    }

    public function test_updates_are_hidden_by_the_brand_setting_blocks_unfollow_and_empty_galleries(): void
    {
        [$viewer, $owner] = [$this->member(), $this->member()];
        $brand = $this->proBrand($owner);
        $this->g()->follow($viewer, $owner);
        $this->g()->follow($owner, $viewer);
        $svc = app(UpdatesService::class);
        $this->assertCount(1, $svc->rings($viewer));

        $brand->forceFill(['n2n_updates_visibility' => 'nobody'])->save();
        $this->assertSame([], $svc->rings($viewer));
        $brand->forceFill(['n2n_updates_visibility' => 'connections'])->save();

        Block::create(['blocker_id' => $owner->id, 'blocked_id' => $viewer->id, 'created_at' => now()]);
        $this->assertSame([], $svc->rings($viewer));
        Block::query()->delete();
        $this->assertCount(1, $svc->rings($viewer));

        $this->g()->unfollow($viewer, $owner);
        $this->assertSame([], $svc->rings($viewer));
        $this->g()->follow($viewer, $owner);

        BrandPartnerImage::query()->delete();                  // no gallery, no ring
        $this->assertSame([], $svc->rings($viewer));
    }

    public function test_a_non_pro_brand_never_appears_even_when_connected(): void
    {
        [$viewer, $owner] = [$this->member(), $this->member()];
        $brand = $this->proBrand($owner);
        $brand->forceFill(['pro_granted_at' => null])->save();
        $this->g()->follow($viewer, $owner);
        $this->g()->follow($owner, $viewer);
        $this->assertSame([], app(UpdatesService::class)->rings($viewer));
    }

    public function test_rings_are_ordered_unseen_first_and_seen_state_is_stored(): void
    {
        $viewer = $this->member();
        $svc = app(UpdatesService::class);
        $brands = [];
        foreach ([1, 2] as $n) {
            $o = $this->member();
            $brands[$n] = $this->proBrand($o);
            $this->g()->follow($viewer, $o);
            $this->g()->follow($o, $viewer);
        }
        $svc->markSeen($viewer, $brands[1]->id);
        $rings = $svc->rings($viewer);
        $this->assertSame($brands[2]->id, $rings[0]['brand_id']);
        $this->assertFalse($rings[0]['seen']);
        $this->assertTrue($rings[1]['seen']);
        $this->assertNull($svc->markSeen($viewer, 999999), 'unknown brand is a quiet no-op');
    }

    public function test_the_viewer_component_loads_media_only_for_a_visible_brand(): void
    {
        [$viewer, $owner, $stranger] = [$this->member(), $this->member(), $this->member()];
        $brand = $this->proBrand($owner);
        $this->g()->follow($viewer, $owner);
        $this->g()->follow($owner, $viewer);

        $this->actingAs($stranger);
        Livewire::test(Updates::class)->call('view', $brand->id)->assertSet('open', null)->assertDontSee('cdn.test');
        $this->actingAs($viewer);
        Livewire::test(Updates::class)->call('view', $brand->id)->assertSet('open', $brand->id)->assertSee('cdn.test/'.$owner->id.'-0.webp', false);
    }

    public function test_reactions_are_validated_one_per_item_and_replaceable(): void
    {
        [$viewer, $owner] = [$this->member(), $this->member()];
        $brand = $this->proBrand($owner);
        $this->g()->follow($viewer, $owner);
        $this->g()->follow($owner, $viewer);
        $svc = app(UpdatesService::class);
        $item = BrandPartnerImage::first();
        $this->assertTrue($svc->react($viewer, $brand->id, $item->id, '❤️'));
        $this->assertTrue($svc->react($viewer, $brand->id, $item->id, '👍'));
        $this->assertSame(1, \App\Models\N2N\UpdateReaction::count());
        $this->assertSame('👍', \App\Models\N2N\UpdateReaction::first()->emoji);
        $this->assertFalse($svc->react($viewer, $brand->id, $item->id, '<script>'));
        $this->assertFalse($svc->react($this->member(), $brand->id, $item->id, '👍'), 'a non-connected viewer cannot react');
        $this->assertFalse($svc->react($viewer, $brand->id, 999999, '👍'));
        $svc->react($viewer, $brand->id, $item->id, null);
        $this->assertSame(0, \App\Models\N2N\UpdateReaction::count());
    }

    public function test_a_reply_lands_in_the_owner_conversation_with_the_quote_and_normal_cost(): void
    {
        [$viewer, $owner] = [$this->member(), $this->member()];
        $brand = $this->proBrand($owner);
        $this->g()->follow($viewer, $owner);
        $this->g()->follow($owner, $viewer);
        $this->credit($viewer);
        $item = BrandPartnerImage::where('brand_partner_id', $brand->id)->first();
        $res = app(UpdatesService::class)->reply($viewer, $brand->id, $item->id, 'Love this!', 'upd-test-0001');
        $m = Message::find($res['message']->id);
        $this->assertSame($viewer->id, $m->sender_id);
        $this->assertSame('Love this!', $m->body);
        $this->assertSame(['kind' => 'update', 'brand_id' => $brand->id, 'brand' => $brand->brand_name, 'item_id' => $item->id, 'source' => 'gallery', 'type' => 'image', 'media' => $item->image_path, 'swatch' => null, 'caption' => 'Cap 0'], $m->quote_json);
        $this->assertSame('0.50', (string) $m->cost_credits);
        $this->assertTrue(\App\Models\N2N\Conversation::find($m->conversation_id)->isParticipant($owner->id));
        // a stranger cannot reply to an update they cannot see
        $this->expectException(\App\Services\N2N\Messaging\MessagingException::class);
        app(UpdatesService::class)->reply($this->member(), $brand->id, $item->id, 'hi', 'upd-test-0002');
    }

    // ---- N2N shell ----------------------------------------------------------------------------------------------------------

    public function test_the_nav_lists_only_destinations_that_exist(): void
    {
        $keys = array_column(N2NNav::items($this->member()), 'key');
        $this->assertContains('chats', $keys);
        $this->assertContains('people', $keys);
        $this->assertContains('updates', $keys);
        $this->assertNotContains('calls', $keys, 'Calls has no route yet, so it must not appear');
        $this->assertLessThanOrEqual(4, count(N2NNav::bar(N2NNav::items($this->member()))));
    }

    public function test_the_chat_page_renders_inside_its_own_shell_with_rail_bar_and_more(): void
    {
        $u = $this->member();
        $this->actingAs($u)->get(route('n2n.chat'))->assertOk()
            ->assertSee('class="nn"', false)->assertSee('nn__bar', false)->assertSee('nn__rail', false)->assertSee('aria-label="More"', false)->assertSee('New chat');
        $this->get(route('n2n.people'))->assertOk()->assertSee('Add by username');
        $this->get(route('n2n.updates'))->assertOk()->assertSee('No updates yet');
    }

    public function test_the_composer_is_docked_in_the_thread_and_the_bar_hides_via_the_thread_marker(): void
    {
        [$a, $b] = [$this->member(), $this->member()];
        $this->credit($a);
        app(\App\Services\N2N\Messaging\MessageService::class)->send($a, ['to' => $b->naara_id], 'dock-test-0001', 'Hello');
        $conv = \App\Models\N2N\Conversation::first();
        $this->actingAs($a)->get(route('n2n.chat', ['c' => $conv->id]))->assertOk()->assertSee('data-n2n-thread', false)->assertSee('nc__dock', false)->assertSee('naara-composer', false);
    }

    public function test_the_brand_owner_can_hide_their_updates(): void
    {
        $owner = $this->member();
        $brand = $this->proBrand($owner);
        $this->actingAs($owner);
        Livewire::test(\App\Livewire\N2N\BrandProfileManage::class)->call('setUpdatesVisibility', 'nobody')->assertSet('message', 'Saved.');
        $this->assertSame('nobody', $brand->fresh()->n2n_updates_visibility);
        Livewire::test(\App\Livewire\N2N\BrandProfileManage::class)->call('setUpdatesVisibility', 'everyone');
        $this->assertSame('nobody', $brand->fresh()->n2n_updates_visibility, 'unknown values are ignored');
    }
}
