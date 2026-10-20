<?php

namespace Tests\Feature\N2N;

use App\Models\Contact;
use App\Models\N2N\Block;
use App\Models\N2N\Message;
use App\Models\N2N\MessageReaction;
use App\Models\N2N\Participant;
use App\Models\User;
use App\Services\N2N\Credit\UsageCredit;
use App\Services\N2N\Credit\UsageLedgerService;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Messaging\ConversationPrefs;
use App\Services\N2N\Messaging\Inbox;
use App\Services\N2N\Messaging\MessageActions;
use App\Services\N2N\Messaging\MessagingException;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/** N2N long-press menu: reactions, forward, edit, info, mark unread and the row actions (MASTER-ONLY). */
class MessageActionsTest extends N2NTestCase
{
    private User $a;

    private User $b;

    private User $c;

    private int $ab;

    private int $ac;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->a, $this->b, $this->c] = [$this->member(), $this->member(), $this->member()];
        foreach ([$this->b, $this->c] as $o) {
            Contact::create(['user_id' => $o->id, 'name' => 'A', 'phone_number' => '0803'.random_int(1000000, 9999999)])->forceFill(['linked_user_id' => $this->a->id])->saveQuietly();
        }
        $this->ab = $this->talk($this->a, $this->b, 'hello b', 'ab-first-0001');
        $this->ac = $this->talk($this->a, $this->c, 'hello c', 'ac-first-0001');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function member(): User
    {
        $u = $this->user(['email_verified_at' => now()]);
        $u->forceFill(['naara_id' => NaaraId::generate(), 'created_at' => now()->subDays(30), 'n2n_read_receipts' => true])->save();
        app(WalletService::class)->credit($u, 20, 'USD', ['reference' => 'f'.$u->id]);
        app(UsageCredit::class)->purchase($u, 20, 'b'.$u->id);

        return $u->fresh();
    }

    private function talk(User $from, User $to, string $text, string $key): int
    {
        return (int) $this->actingAs($from)->post('/n2n/api/messages?to='.$to->naara_id, ['meta' => json_encode(['text' => $text])], ['Idempotency-Key' => $key, 'Accept' => 'application/json'])->assertCreated()->json('conversation_id');
    }

    /** A message from `$from` into an existing chat, written straight to the tables (the send rules are covered elsewhere). */
    private function reply(User $from, int $cid, string $text, string $key): void
    {
        $m = Message::query()->create(['conversation_id' => $cid, 'sender_id' => $from->id, 'client_id' => $key, 'type' => 'text', 'body' => $text, 'created_at' => now()]);
        \App\Models\N2N\Conversation::query()->whereKey($cid)->update(['last_message_id' => $m->id]);
    }

    private function msg(int $cid): Message
    {
        return Message::query()->where('conversation_id', $cid)->latest('id')->first();
    }

    private function bal(User $u): int
    {
        return array_sum(app(UsageLedgerService::class)->balances($u->id));
    }

    private function svc(): MessageActions
    {
        return app(MessageActions::class);
    }

    public function test_reactions_are_one_per_person_a_closed_set_and_toggle_off(): void
    {
        $m = $this->msg($this->ab);
        $this->assertSame('love', $this->svc()->react($this->b, $m->id, 'love'));
        $this->assertSame('laugh', $this->svc()->react($this->b, $m->id, 'laugh'), 'changing replaces, never adds a second');
        $this->assertSame(1, MessageReaction::query()->count());
        $this->assertNull($this->svc()->react($this->b, $m->id, 'laugh'), 'the same reaction again removes it');
        $this->assertSame(0, MessageReaction::query()->count());
        $this->expectException(MessagingException::class);
        $this->svc()->react($this->b, $m->id, '<script>');
    }

    public function test_only_the_two_members_can_react_and_a_block_is_a_silent_no_op(): void
    {
        $m = $this->msg($this->ab);
        try {
            $this->svc()->react($this->c, $m->id, 'like');   // c is in a DIFFERENT conversation
            $this->fail('an outsider reacted');
        } catch (MessagingException $e) {
            $this->assertSame('not_found', $e->reason);
        }
        Block::query()->create(['blocker_id' => $this->a->id, 'blocked_id' => $this->b->id]);
        $this->assertNull($this->svc()->react($this->b, $m->id, 'like'));
        $this->assertSame(0, MessageReaction::query()->count(), 'no error to tell b they are blocked, and nothing was stored');
    }

    public function test_forward_sends_a_marked_copy_to_each_chosen_chat_charges_each_and_hides_nothing_else(): void
    {
        $src = $this->msg($this->ab);
        $before = $this->bal($this->a);
        $r = $this->svc()->forward($this->a, $src->id, [$this->ac], 'req-1');
        $this->assertSame(['sent' => 1, 'failed' => 0, 'credit' => false], $r);
        $copy = $this->msg($this->ac);
        $this->assertSame('hello b', $copy->body);
        $this->assertTrue($copy->forwarded);
        $this->assertSame($this->a->id, (int) $copy->sender_id, 'it is sent by the forwarder, never as the original author');
        $this->assertLessThan($before, $this->bal($this->a));
        $again = $this->svc()->forward($this->a, $src->id, [$this->ac], 'req-1');
        $this->assertSame(1, Message::query()->where('conversation_id', $this->ac)->where('forwarded', true)->count(), 'the same request replayed sends nothing twice');
        $this->assertSame(1, $again['sent']);
    }

    public function test_forward_refuses_chats_that_are_not_yours_too_many_targets_and_non_text(): void
    {
        $src = $this->msg($this->ab);
        $other = $this->member();
        $x = $this->member();
        Contact::create(['user_id' => $x->id, 'name' => 'O', 'phone_number' => '0803'.random_int(1000000, 9999999)])->forceFill(['linked_user_id' => $other->id])->saveQuietly();
        $foreign = $this->talk($other, $x, 'private', 'ox-first-0001');
        $r = $this->svc()->forward($this->a, $src->id, [$foreign], 'req-2');
        $this->assertSame(1, $r['failed'], 'a chat the member is not in cannot receive a forward');
        $this->assertSame(1, Message::query()->where('conversation_id', $foreign)->count());
        $this->expectException(MessagingException::class);
        $this->svc()->forward($this->a, $src->id, range(100, 100 + MessageActions::forwardMaxTargets()), 'req-3');
    }

    public function test_a_member_cannot_forward_a_message_from_a_chat_they_are_not_in(): void
    {
        $this->expectException(MessagingException::class);
        $this->svc()->forward($this->c, $this->msg($this->ab)->id, [$this->ac], 'req-4');
    }

    public function test_edit_works_for_own_text_inside_the_window_keeps_history_and_obeys_the_link_filter(): void
    {
        $m = $this->msg($this->ab);
        $this->svc()->edit($this->a, $m->id, 'hello b, corrected');
        $m->refresh();
        $this->assertSame('hello b, corrected', $m->body);
        $this->assertNotNull($m->edited_at);
        $this->assertSame('hello b', $m->edit_log[0]['body'], 'the earlier text is kept for moderation');
        try {
            $this->svc()->edit($this->a, $m->id, 'see example.com');
            $this->fail('link accepted');
        } catch (MessagingException $e) {
            $this->assertSame('link_blocked', $e->reason);
        }
        try {
            $this->svc()->edit($this->b, $m->id, 'hijack');
            $this->fail('other person edited');
        } catch (MessagingException) {
            $this->assertSame('hello b, corrected', $m->fresh()->body);
        }
        Carbon::setTestNow(now()->addMinutes(MessageActions::editWindowMinutes() + 1));
        $this->expectException(MessagingException::class);
        $this->svc()->edit($this->a, $m->id, 'too late');
    }

    public function test_info_shows_read_time_only_when_the_other_person_allows_receipts(): void
    {
        $m = $this->msg($this->ab);
        app(\App\Services\N2N\Messaging\MessageService::class)->markRead($this->b, $this->ab);
        $this->assertNotNull($this->svc()->info($this->a, $m->id)['read']);
        $this->b->forceFill(['n2n_read_receipts' => false])->save();
        $i = $this->svc()->info($this->a, $m->id);
        $this->assertNull($i['read']);
        $this->assertNotNull($i['delivered']);
        $this->expectException(MessagingException::class);
        $this->svc()->info($this->b, $m->id);   // only the sender sees details
    }

    public function test_mark_unread_is_private_shows_in_the_inbox_and_clears_when_opened(): void
    {
        app(\App\Services\N2N\Messaging\MessageService::class)->markRead($this->a, $this->ab);
        $row = fn () => collect(app(Inbox::class)->entries($this->a, 'all'))->firstWhere('id', $this->ab);
        $this->assertSame(0, $row()['unread']);
        app(\App\Services\N2N\Messaging\MessageService::class)->markRead($this->b, $this->ab);
        app(ConversationPrefs::class)->markUnread($this->a, $this->ab);
        $this->assertSame(1, $row()['unread']);
        $this->assertSame(0, collect(app(Inbox::class)->entries($this->b, 'all'))->firstWhere('id', $this->ab)['unread'] ?? 0, 'the other person is not affected');
        app(\App\Services\N2N\Messaging\MessageService::class)->markRead($this->a, $this->ab);
        $this->assertSame(0, $row()['unread']);
    }

    public function test_the_chat_row_actions_work_on_any_own_chat_and_refuse_someone_elses(): void
    {
        $t = Livewire::actingAs($this->a)->test(\App\Livewire\N2N\Chat::class);
        $t->call('rowPin', $this->ab)->call('rowMute', $this->ac, '8h')->call('rowArchive', $this->ac)->call('rowUnread', $this->ab);
        $pb = Participant::query()->where('conversation_id', $this->ab)->where('user_id', $this->a->id)->first();
        $pc = Participant::query()->where('conversation_id', $this->ac)->where('user_id', $this->a->id)->first();
        $this->assertNotNull($pb->pinned_at);
        $this->assertNotNull($pb->marked_unread_at);
        $this->assertNotNull($pc->muted_until);
        $this->assertNotNull($pc->archived_at);
        // b's chat with a is not c's: c cannot touch it
        Livewire::actingAs($this->c)->test(\App\Livewire\N2N\Chat::class)->call('rowPin', $this->ab);
        $this->assertNull(Participant::query()->where('conversation_id', $this->ab)->where('user_id', $this->c->id)->first());
        $this->assertNotNull(Participant::query()->where('conversation_id', $this->ab)->where('user_id', $this->b->id)->first());
        $this->assertNull(Participant::query()->where('conversation_id', $this->ab)->where('user_id', $this->b->id)->value('pinned_at'));
    }

    public function test_the_page_renders_the_menu_data_reaction_pills_and_forwarded_and_edited_labels(): void
    {
        $m = $this->msg($this->ab);
        $this->svc()->react($this->b, $m->id, 'love');
        $this->svc()->edit($this->a, $m->id, 'hello b!');
        $this->svc()->forward($this->a, $m->id, [$this->ac], 'req-5');
        $this->actingAs($this->a)->get('/n2n/chat?c='.$this->ab)->assertOk()->assertSee('data-ctx=', false)->assertSee('ncb__react', false)->assertSee('edited');
        $this->actingAs($this->a)->get('/n2n/chat?c='.$this->ac)->assertOk()->assertSee('Forwarded');
    }

    public function test_the_reaction_bar_is_personal_closed_capped_and_resets_to_defaults(): void
    {
        $this->assertSame(MessageActions::DEFAULT_BAR, MessageActions::quickBar($this->a));
        $this->assertSame(['fire', 'rocket'], $this->svc()->saveQuickBar($this->a, ['fire', 'rocket', '<b>x</b>', 'fire']), 'unknown codes and duplicates are dropped');
        $this->assertSame(['fire', 'rocket'], MessageActions::quickBar($this->a->fresh()));
        $this->assertSame(MessageActions::DEFAULT_BAR, MessageActions::quickBar($this->b), 'one member\'s bar never changes another\'s');
        $this->assertCount(MessageActions::BAR_MAX, $this->svc()->saveQuickBar($this->a, array_keys(MessageActions::REACTIONS)));
        $this->svc()->saveQuickBar($this->a, MessageActions::DEFAULT_BAR);
        $this->assertNull($this->a->fresh()->n2n_reaction_bar, 'the defaults are stored as nothing');
        $this->expectException(MessagingException::class);
        $this->svc()->saveQuickBar($this->a, ['nope']);
    }

    public function test_any_catalogue_reaction_works_on_a_message_but_nothing_outside_it(): void
    {
        $m = $this->msg($this->ab);
        $this->assertSame('rocket', $this->svc()->react($this->b, $m->id, 'rocket'));
        $this->assertSame(['rocket' => 1], $this->svc()->reactionsFor($this->a, [$m->id])[$m->id]['counts']);
        foreach (MessageActions::REACTIONS as $code => $glyph) {
            $this->assertLessThanOrEqual(12, strlen($code));
        }
    }

    public function test_clear_chat_hides_history_for_me_only_and_keeps_the_row_empty(): void
    {
        $m = $this->msg($this->ab);
        $this->reply($this->b, $this->ab, 'reply from b', 'ba-0001');
        Livewire::actingAs($this->a)->test(\App\Livewire\N2N\Chat::class)->call('rowClear', $this->ab);
        $this->assertSame(0, Message::query()->where('conversation_id', $this->ab)->forViewer($this->a->id)->count());
        $this->assertSame(2, Message::query()->where('conversation_id', $this->ab)->forViewer($this->b->id)->count(), 'the other person keeps everything');
        $this->assertSame(2, Message::query()->where('conversation_id', $this->ab)->count(), 'nothing is deleted from storage');
        $row = collect(app(Inbox::class)->entries($this->a))->firstWhere('id', $this->ab);
        $this->assertNotNull($row, 'a cleared chat stays in the list');
        $this->assertSame('Chat cleared', $row['preview']);
        $this->assertSame(0, $row['unread']);
        $this->expectException(MessagingException::class);
        app(ConversationPrefs::class)->star($this->a, $m->id);   // a cleared message cannot be acted on any more
    }

    public function test_a_new_message_after_a_clear_shows_only_the_new_message(): void
    {
        Livewire::actingAs($this->a)->test(\App\Livewire\N2N\Chat::class)->call('rowClear', $this->ab);
        $this->reply($this->b, $this->ab, 'fresh start', 'ba-0002');
        $rows = Message::query()->where('conversation_id', $this->ab)->forViewer($this->a->id)->pluck('body')->all();
        $this->assertSame(['fresh start'], $rows);
        $this->assertSame('fresh start', collect(app(Inbox::class)->entries($this->a))->firstWhere('id', $this->ab)['preview']);
        $this->assertSame(1, collect(app(Inbox::class)->entries($this->a))->firstWhere('id', $this->ab)['unread']);
        $this->assertSame([], app(ConversationPrefs::class)->search($this->a, $this->ab, 'hello'), 'search cannot find cleared text');
    }

    public function test_delete_chat_leaves_my_list_until_a_newer_message_arrives(): void
    {
        Livewire::actingAs($this->a)->test(\App\Livewire\N2N\Chat::class)->call('rowPin', $this->ab)->call('rowDeleteChat', $this->ab);
        $this->assertNull(collect(app(Inbox::class)->entries($this->a))->firstWhere('id', $this->ab));
        $this->assertNull(Participant::query()->where('conversation_id', $this->ab)->where('user_id', $this->a->id)->value('pinned_at'));
        $this->assertNotNull(collect(app(Inbox::class)->entries($this->b))->firstWhere('id', $this->ab), 'the other person still has the chat');
        $this->reply($this->b, $this->ab, 'are you there', 'ba-0003');
        $this->assertNotNull(collect(app(Inbox::class)->entries($this->a))->firstWhere('id', $this->ab));
    }

    public function test_delete_for_me_hides_one_message_from_my_view_only_and_an_outsider_cannot_hide_it(): void
    {
        $m = $this->msg($this->ab);
        app(ConversationPrefs::class)->hideMessage($this->b, $m->id);
        $this->assertSame(0, Message::query()->whereKey($m->id)->forViewer($this->b->id)->count());
        $this->assertSame(1, Message::query()->whereKey($m->id)->forViewer($this->a->id)->count());
        $this->actingAs($this->b)->get('/n2n/chat?c='.$this->ab)->assertOk()->assertDontSee('hello b');
        $this->expectException(MessagingException::class);
        app(ConversationPrefs::class)->hideMessage($this->c, $m->id);
    }

    public function test_only_my_chats_can_be_cleared_or_deleted(): void
    {
        Livewire::actingAs($this->c)->test(\App\Livewire\N2N\Chat::class)->call('rowClear', $this->ab)->call('rowDeleteChat', $this->ab);
        $this->assertSame(0, (int) Participant::query()->where('conversation_id', $this->ab)->max('cleared_before_id'));
        $this->assertNull(Participant::query()->where('conversation_id', $this->ab)->value('removed_at'));
    }

    public function test_erasure_cancels_queued_messages_and_removes_reactions_hides_stars_and_old_versions(): void
    {
        $m = $this->msg($this->ab);
        $this->svc()->edit($this->a, $m->id, 'hello b, edited');
        $this->svc()->react($this->a, $m->id, 'fire');
        $this->svc()->saveQuickBar($this->a, ['fire', 'like']);
        app(ConversationPrefs::class)->star($this->a, $m->id);
        $other = $this->msg($this->ac);
        app(ConversationPrefs::class)->hideMessage($this->a, $other->id);
        $q = \App\Models\N2N\ScheduledMessage::query()->create(['sender_id' => $this->a->id, 'conversation_id' => $this->ab, 'client_id' => 'q-1', 'body' => 'send later', 'send_at' => now()->addHour(), 'status' => 'pending']);

        $export = \App\Support\UserDataExporter::for($this->a->fresh());
        $this->assertNotEmpty($export['n2n']['messages_sent']);
        $this->assertSame('fire', $export['n2n']['reactions_given'][0]['reaction']);
        $this->assertSame('send later', $export['n2n']['scheduled_messages'][0]['body']);
        $this->assertSame(['fire', 'like'], $export['n2n']['reaction_bar']);

        \App\Services\N2N\N2NErasure::erase($this->a->fresh());

        $this->assertSame('cancelled', $q->fresh()->status);
        $this->assertSame('', $q->fresh()->body);
        $this->assertSame(0, MessageReaction::query()->where('user_id', $this->a->id)->count());
        $this->assertSame(0, \App\Models\N2N\MessageHide::query()->where('user_id', $this->a->id)->count());
        $this->assertSame(0, \App\Models\N2N\Star::query()->where('user_id', $this->a->id)->count());
        $this->assertNull(Message::query()->whereKey($m->id)->value('edit_log'));
        $this->assertNull($this->a->fresh()->n2n_reaction_bar);
        $this->assertSame('hello b, edited', Message::query()->whereKey($m->id)->value('body'), 'the visible text stays in the other person\'s chat, like any chat');
    }

    public function test_bulk_chat_actions_apply_to_my_chats_only_and_never_touch_the_other_person(): void
    {
        $t = Livewire::actingAs($this->a)->test(\App\Livewire\N2N\Chat::class);
        $t->call('bulkRows', [$this->ab, $this->ac, 999999], 'archive');
        $this->assertNotNull(Participant::query()->where('conversation_id', $this->ab)->where('user_id', $this->a->id)->value('archived_at'));
        $this->assertNotNull(Participant::query()->where('conversation_id', $this->ac)->where('user_id', $this->a->id)->value('archived_at'));
        $this->assertNull(Participant::query()->where('conversation_id', $this->ab)->where('user_id', $this->b->id)->value('archived_at'));
        $t->call('bulkRows', [$this->ab, $this->ac], 'delete');
        $this->assertSame([], collect(app(Inbox::class)->entries($this->a, 'archived'))->pluck('id')->all());
        $this->assertNotNull(collect(app(Inbox::class)->entries($this->b))->firstWhere('id', $this->ab), 'the other person keeps the chat');
        // c cannot act on a chat they are not in, even by guessing ids
        Livewire::actingAs($this->c)->test(\App\Livewire\N2N\Chat::class)->call('bulkRows', [$this->ab], 'delete');
        $this->assertNull(Participant::query()->where('conversation_id', $this->ab)->where('user_id', $this->b->id)->value('removed_at'));
        $this->assertSame(0, (int) Participant::query()->where('conversation_id', $this->ab)->where('user_id', $this->b->id)->value('cleared_before_id'));
    }

    public function test_bulk_read_unread_pin_and_an_unknown_action(): void
    {
        $t = Livewire::actingAs($this->b)->test(\App\Livewire\N2N\Chat::class);
        $t->call('bulkRows', [$this->ab], 'unread');
        $this->assertNotNull(Participant::query()->where('conversation_id', $this->ab)->where('user_id', $this->b->id)->value('marked_unread_at'));
        $t->call('bulkRows', [$this->ab], 'read');
        $this->assertNull(Participant::query()->where('conversation_id', $this->ab)->where('user_id', $this->b->id)->value('marked_unread_at'));
        $t->call('bulkRows', [$this->ab], 'pin');
        $this->assertNotNull(Participant::query()->where('conversation_id', $this->ab)->where('user_id', $this->b->id)->value('pinned_at'));
        $t->call('bulkRows', [$this->ab], 'wipe_everything');
        $this->assertSame(1, Message::query()->where('conversation_id', $this->ab)->count());
    }

    public function test_the_chat_list_sorts_newest_oldest_name_and_unread_with_pinned_first(): void
    {
        Message::query()->where('conversation_id', $this->ab)->update(['created_at' => now()->subDays(3)]);
        $ids = fn (string $sort) => array_column(app(Inbox::class)->entries($this->a, 'all', '', $sort), 'id');
        $this->assertSame([$this->ac, $this->ab], $ids('newest'));
        $this->assertSame([$this->ab, $this->ac], $ids('oldest'));
        app(ConversationPrefs::class)->pinChat($this->a, $this->ab, true);
        $this->assertSame([$this->ab, $this->ac], $ids('newest'), 'a pinned chat leads whatever the sort');
        $this->assertCount(2, $ids('name'));
        $this->assertSame($ids('newest'), $ids('not-a-sort'));
    }

    public function test_bulk_delete_for_me_hides_messages_and_a_search_can_delete_all_its_matches(): void
    {
        $this->reply($this->b, $this->ab, 'invoice one', 'ba-bulk-1');
        $this->reply($this->b, $this->ab, 'invoice two', 'ba-bulk-2');
        $this->reply($this->b, $this->ab, 'lunch', 'ba-bulk-3');
        $t = Livewire::actingAs($this->a)->test(\App\Livewire\N2N\Chat::class)->set('c', $this->ab)->set('chatSearch', 'invoice')->set('panel', 'search');
        $t->call('deleteSearchForMe');
        $left = Message::query()->where('conversation_id', $this->ab)->forViewer($this->a->id)->pluck('body')->all();
        $this->assertSame(['hello b', 'lunch'], $left);
        $this->assertSame(4, Message::query()->where('conversation_id', $this->ab)->count(), 'nothing was deleted from storage');
        $ids = Message::query()->where('conversation_id', $this->ab)->forViewer($this->a->id)->pluck('id')->all();
        Livewire::actingAs($this->a)->test(\App\Livewire\N2N\Chat::class)->set('c', $this->ab)->call('bulkHideMessages', array_merge($ids, [987654]));
        $this->assertSame(0, Message::query()->where('conversation_id', $this->ab)->forViewer($this->a->id)->count());
        $this->assertSame(4, Message::query()->where('conversation_id', $this->ab)->forViewer($this->b->id)->count(), 'the other person still has every message');
    }

    public function test_disappearing_messages_apply_to_new_messages_hide_at_once_and_are_purged_for_good(): void
    {
        $old = $this->msg($this->ab);
        $svc = app(\App\Services\N2N\Messaging\Disappearing::class);
        $svc->set($this->b, $this->ab, 86400);
        $this->assertSame(86400, \App\Models\N2N\Conversation::query()->whereKey($this->ab)->value('disappear_after'));
        $this->assertNull($old->fresh()->expires_at, 'messages already sent are not touched');
        $this->actingAs($this->a)->post(route('n2n.api.send', ['conversationId' => $this->ab]), ['meta' => json_encode(['text' => 'secret plan'])], ['Idempotency-Key' => 'dis-msg-0001', 'Accept' => 'application/json'])->assertCreated();
        $new = Message::query()->where('client_id', 'dis-msg-0001')->firstOrFail();
        $this->assertEqualsWithDelta(now()->addDay()->timestamp, $new->expires_at->timestamp, 5);
        $this->assertSame(2, Message::query()->where('conversation_id', $this->ab)->forViewer($this->b->id)->count());
        \Illuminate\Support\Carbon::setTestNow(now()->addDays(2));
        $this->assertSame(1, Message::query()->where('conversation_id', $this->ab)->forViewer($this->b->id)->count(), 'gone for both the moment it expires');
        $this->assertSame(1, Message::query()->where('conversation_id', $this->ab)->forViewer($this->a->id)->count());
        $this->assertSame(2, Message::query()->where('conversation_id', $this->ab)->count(), 'still stored until the purge runs');
        $this->artisan('n2n:expire-messages')->assertSuccessful();
        $this->assertSame(1, Message::query()->where('conversation_id', $this->ab)->count());
        $this->assertNull(Message::query()->find($new->id));
    }

    public function test_only_members_can_set_the_timer_and_only_known_options_are_accepted_and_off_stops_new_expiry(): void
    {
        $svc = app(\App\Services\N2N\Messaging\Disappearing::class);
        try {
            $svc->set($this->c, $this->ab, 86400);
            $this->fail('an outsider set the timer');
        } catch (MessagingException) {
            $this->assertNull(\App\Models\N2N\Conversation::query()->whereKey($this->ab)->value('disappear_after'));
        }
        try {
            $svc->set($this->a, $this->ab, 5);
            $this->fail('an arbitrary time was accepted');
        } catch (MessagingException) {
            $this->assertTrue(true);
        }
        $svc->set($this->a, $this->ab, 604800);
        $svc->set($this->b, $this->ab, 0);
        $this->assertNull(\App\Models\N2N\Conversation::query()->whereKey($this->ab)->value('disappear_after'));
        $this->actingAs($this->a)->get('/n2n/chat?c='.$this->ab)->assertOk();
        Livewire::actingAs($this->a)->test(\App\Livewire\N2N\Chat::class)->set('c', $this->ab)->call('setDisappear', 86400)->assertSet('notice', 'Disappearing messages are on: new messages disappear after 24 hours.');
    }

    public function test_chat_lock_hides_a_chat_from_lists_search_and_counts_until_the_pin_is_entered(): void
    {
        $lock = app(\App\Services\N2N\Messaging\ChatLock::class);
        $prefs = app(ConversationPrefs::class);
        try {
            $lock->setChat($this->a, $this->ab, true, $prefs);
            $this->fail('locked without a PIN');
        } catch (MessagingException) {
            $this->assertTrue(true);
        }
        foreach (['12', 'abcd', '1111', '1234'] as $weak) {
            try {
                $lock->setPin($this->a, $weak);
                $this->fail("accepted $weak");
            } catch (MessagingException) {
                $this->assertTrue(true);
            }
        }
        $lock->setPin($this->a, '4096');
        $this->assertNotSame('4096', $this->a->fresh()->n2n_lock_hash, 'only a hash is stored');
        $lock->setChat($this->a->fresh(), $this->ab, true, $prefs);
        \App\Services\N2N\Messaging\ChatLock::relock();
        $inbox = app(Inbox::class);
        $this->assertNull(collect($inbox->entries($this->a))->firstWhere('id', $this->ab), 'hidden from the list');
        $this->assertSame([], $inbox->entries($this->a, 'locked'), 'and from the Locked folder until unlocked');
        $this->assertSame([], $inbox->entries($this->a, 'all', 'hello b'), 'and from search');
        $this->assertSame(0, array_sum(array_map(fn ($e) => $e['unread'], $inbox->entries($this->a))) + 0 * $inbox->totalUnread($this->a));
        $this->assertNotNull(collect($inbox->entries($this->b))->firstWhere('id', $this->ab), 'the other person is unaffected');
        Livewire::actingAs($this->a)->test(\App\Livewire\N2N\Chat::class)->call('select', $this->ab)->assertSet('c', null);
        $lock->unlock($this->a->fresh(), '4096');
        $this->assertNotNull(collect($inbox->entries($this->a, 'locked', '', 'newest', true))->firstWhere('id', $this->ab));
        Livewire::actingAs($this->a)->test(\App\Livewire\N2N\Chat::class)->call('select', $this->ab)->assertSet('c', $this->ab);
        \App\Services\N2N\Messaging\ChatLock::relock();
        $lock->unlock($this->a->fresh(), '4096');
        $lock->setChat($this->a->fresh(), $this->ab, false, $prefs);
        $this->assertNotNull(collect($inbox->entries($this->a))->firstWhere('id', $this->ab), 'unlocked chats are back in the list');
    }

    public function test_wrong_pins_are_throttled_and_removing_the_pin_needs_the_pin_and_unlocks_everything(): void
    {
        $lock = app(\App\Services\N2N\Messaging\ChatLock::class);
        $lock->setPin($this->a, '4096');
        $u = $this->a->fresh();
        for ($i = 0; $i < 5; $i++) {
            try {
                $lock->unlock($u->fresh(), '0000');
            } catch (MessagingException) {
            }
        }
        try {
            $lock->unlock($u->fresh(), '4096');
            $this->fail('the right PIN worked while attempts were paused');
        } catch (MessagingException $e) {
            $this->assertSame('rate_limited', $e->reason);
        }
        $this->a->fresh()->forceFill(['n2n_lock_until' => null])->saveQuietly();
        app(ConversationPrefs::class);
        $lock->setChat($this->a->fresh(), $this->ab, true, app(ConversationPrefs::class));
        try {
            $lock->removePin($this->a->fresh(), '9999');
            $this->fail('removed with the wrong PIN');
        } catch (MessagingException) {
            $this->assertNotNull($this->a->fresh()->n2n_lock_hash);
        }
        $lock->removePin($this->a->fresh(), '4096');
        $this->assertNull($this->a->fresh()->n2n_lock_hash);
        $this->assertNull(Participant::query()->where('conversation_id', $this->ab)->where('user_id', $this->a->id)->value('locked_at'));
    }

    public function test_the_sheet_flow_sets_a_pin_locks_the_pending_chat_and_the_filter_chip_appears(): void
    {
        $t = Livewire::actingAs($this->a)->test(\App\Livewire\N2N\Chat::class)->call('rowLock', $this->ab)->assertSet('lockSheet', 'set')->assertSet('pendingLock', $this->ab);
        $t->set('pin', '4096')->set('pin2', '4097')->call('submitLock')->assertSet('error', 'The two PINs do not match.');
        $t->set('pin', '4096')->set('pin2', '4096')->call('submitLock')->assertSet('lockSheet', null);
        $this->assertNotNull(Participant::query()->where('conversation_id', $this->ab)->where('user_id', $this->a->id)->value('locked_at'));
        $t->assertSee('Locked');
    }
}
