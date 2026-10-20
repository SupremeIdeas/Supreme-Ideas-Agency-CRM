<?php

namespace Tests\Feature\N2N;

use App\Jobs\SendWebPushJob;
use App\Livewire\N2N\Chat;
use App\Models\Contact;
use App\Models\N2N\Attachment;
use App\Models\N2N\AttachmentHide;
use App\Models\N2N\Conversation;
use App\Models\N2N\Message;
use App\Models\N2N\Participant;
use App\Models\N2N\RealtimeEvent;
use App\Models\N2N\Star;
use App\Models\Setting;
use App\Models\User;
use App\Services\N2N\Credit\UsageCredit;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Messaging\ChatExport;
use App\Services\N2N\Messaging\ChatLibrary;
use App\Services\N2N\Messaging\ChatThemes;
use App\Services\N2N\Messaging\ConversationPrefs;
use App\Services\N2N\Messaging\Inbox;
use App\Services\N2N\Messaging\MessageService;
use App\Services\N2N\Messaging\MessagingException;
use App\Services\Wallet\WalletService;
use App\Support\MediaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** N2N Batch 12 (Tier 1): per-member conversation power features (MASTER-ONLY). Every preference is private to the member who sets it. */
class ConversationPowerTest extends N2NTestCase
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

    /** @return array{0: User, 1: User, 2: int} two connected members and their conversation id */
    private function pair(): array
    {
        $a = $this->member('Ada');
        $b = $this->member('Bayo');
        $this->connect($a, $b);
        $this->connect($b, $a);
        $r = app(MessageService::class)->send($a, ['to' => $b->naara_id], 'c'.uniqid().'xxxx', 'hello');

        return [$a, $b, $r['conversation']->id];
    }

    private function say(User $from, int $cid, string $body, ?int $replyTo = null): Message
    {
        return app(MessageService::class)->send($from, ['conversation_id' => $cid], 'c'.uniqid().'xxxx', $body, null, [], null, $replyTo)['message'];
    }

    private function prefs(): ConversationPrefs
    {
        return app(ConversationPrefs::class);
    }

    // ---- mute / archive / pin chat
    public function test_mute_is_private_timed_and_silences_tone_and_push_but_not_the_badge(): void
    {
        [$a, $b, $cid] = $this->pair();
        Queue::fake();   // after the first (un-muted) message, which legitimately pushed
        $this->prefs()->mute($b, $cid, '8h');
        $this->assertTrue(ConversationPrefs::isMuted(Participant::query()->where('conversation_id', $cid)->where('user_id', $b->id)->first()));
        $this->assertFalse(ConversationPrefs::isMuted(Participant::query()->where('conversation_id', $cid)->where('user_id', $a->id)->first()), 'the other person is not muted');

        $m = $this->say($a, $cid, 'ping while muted');
        $event = RealtimeEvent::query()->where('user_id', $b->id)->where('type', 'message.new')->orderByDesc('id')->first();
        $this->assertNull($event->payload['tone'], 'no tone for a muted chat');
        $this->assertTrue($event->payload['muted']);
        Queue::assertNotPushed(SendWebPushJob::class);
        $entry = collect(app(Inbox::class)->entries($b))->firstWhere('id', $cid);
        $this->assertSame(2, $entry['unread'], 'the unread badge still counts');
        $this->assertTrue($entry['muted']);

        $this->prefs()->mute($b, $cid, null);
        $this->say($a, $cid, 'ping after unmute');
        $this->assertSame('message.naara', RealtimeEvent::query()->where('user_id', $b->id)->where('type', 'message.new')->orderByDesc('id')->first()->payload['tone']);
        $this->expectException(MessagingException::class);
        $this->prefs()->mute($b, $cid, '1y');
    }

    public function test_an_archived_chat_leaves_the_inbox_until_a_new_message_but_stays_away_when_muted(): void
    {
        [$a, $b, $cid] = $this->pair();
        $this->prefs()->archive($b, $cid);
        $this->assertNull(collect(app(Inbox::class)->entries($b))->firstWhere('id', $cid), 'archived chats are out of the inbox');
        $this->assertNotNull(collect(app(Inbox::class)->entries($b, 'archived'))->firstWhere('id', $cid), 'and listed under Archived');

        $this->say($a, $cid, 'back again');
        $this->assertNotNull(collect(app(Inbox::class)->entries($b))->firstWhere('id', $cid), 'a new message brings it back');

        $this->prefs()->archive($b, $cid);
        $this->prefs()->mute($b, $cid, 'always');
        $this->say($a, $cid, 'silent one');
        $this->assertNull(collect(app(Inbox::class)->entries($b))->firstWhere('id', $cid), 'archived + muted stays archived');
        $this->assertNotNull($b->fresh() && collect(app(Inbox::class)->entries($b, 'archived'))->firstWhere('id', $cid));
    }

    public function test_a_declined_request_stays_archived_even_when_the_sender_writes_again(): void
    {
        $a = $this->member('Stranger');
        $b = $this->member('Target');
        $r = app(MessageService::class)->send($a, ['to' => $b->naara_id], 'c'.uniqid().'xxxx', 'hi');
        $cid = $r['conversation']->id;
        $this->assertSame(Conversation::REQUEST, $r['conversation']->status);
        app(MessageService::class)->decline($b, $cid);
        app(MessageService::class)->send($a, ['conversation_id' => $cid], 'c'.uniqid().'xxxx', 'hi again');
        $this->assertNotNull(Participant::query()->where('conversation_id', $cid)->where('user_id', $b->id)->value('archived_at'), 'a decline is not undone by another message');
    }

    public function test_pinned_chats_float_to_the_top_are_capped_by_an_admin_setting_and_archiving_unpins(): void
    {
        Setting::setValue('n2n.max_pinned_chats', 1);
        [$a, $b, $c1] = $this->pair();
        $c = $this->member('Cee');
        $this->connect($a, $c);
        $c2 = app(MessageService::class)->send($a, ['to' => $c->naara_id], 'c'.uniqid().'xxxx', 'older chat?')['conversation']->id;
        $this->say($a, $c1, 'newest');   // chat 1 is the newest

        $this->prefs()->pinChat($a, $c2);
        $ids = array_column(app(Inbox::class)->entries($a), 'id');
        $this->assertSame($c2, $ids[0], 'the pinned chat leads even though it is older');
        try {
            $this->prefs()->pinChat($a, $c1);
            $this->fail('the second pin should be refused');
        } catch (MessagingException $e) {
            $this->assertSame('limit', $e->reason);
        }
        $this->prefs()->archive($a, $c2);
        $this->assertNull(Participant::query()->where('conversation_id', $c2)->where('user_id', $a->id)->value('pinned_at'));
    }

    public function test_nobody_can_touch_a_chat_they_are_not_in(): void
    {
        [, , $cid] = $this->pair();
        $outsider = $this->member('Outsider');
        foreach ([fn () => $this->prefs()->mute($outsider, $cid, '8h'), fn () => $this->prefs()->archive($outsider, $cid), fn () => $this->prefs()->setTheme($outsider, $cid, ['accent' => 'rose']),
            fn () => $this->prefs()->search($outsider, $cid, 'hello'), fn () => app(ChatExport::class)->text($outsider, $cid), fn () => app(ChatLibrary::class)->usage($outsider, $cid)] as $try) {
            try {
                $try();
                $this->fail('an outsider got through');
            } catch (MessagingException $e) {
                $this->assertSame('not_participant', $e->reason);
            }
        }
    }

    // ---- reply, stars, pinned message, search
    public function test_a_reply_points_at_a_visible_message_in_the_same_chat_only(): void
    {
        [$a, $b, $cid] = $this->pair();
        $orig = $this->say($b, $cid, 'what time?');
        $reply = $this->say($a, $cid, 'five', $orig->id);
        $this->assertSame($orig->id, $reply->reply_to_id);

        $c = $this->member('Cee');
        $this->connect($a, $c);
        $other = app(MessageService::class)->send($a, ['to' => $c->naara_id], 'c'.uniqid().'xxxx', 'elsewhere');
        $this->expectException(MessagingException::class);   // a message from ANOTHER chat cannot be quoted here
        $this->say($a, $cid, 'sneaky', $other['message']->id);
    }

    public function test_stars_and_the_pinned_message_are_private_and_survive_only_while_visible(): void
    {
        [$a, $b, $cid] = $this->pair();
        $m = $this->say($b, $cid, 'remember this address');
        $this->prefs()->star($a, $m->id);
        $this->assertSame([$m->id], $this->prefs()->starredIds($a, $cid));
        $this->assertSame([], $this->prefs()->starredIds($b, $cid), 'the other person has no idea');
        $this->assertSame('remember this address', $this->prefs()->starred($a, $cid)[0]['body']);

        $this->prefs()->pinMessage($a, $cid, $m->id);
        $pa = Participant::query()->where('conversation_id', $cid)->where('user_id', $a->id)->first();
        $pb = Participant::query()->where('conversation_id', $cid)->where('user_id', $b->id)->first();
        $this->assertSame($m->id, $this->prefs()->pinnedMessage($a, $pa)['id']);
        $this->assertNull($this->prefs()->pinnedMessage($b, $pb), 'a pin is per member');

        app(MessageService::class)->deleteForEveryone($b, $m->id);
        $this->assertNull($this->prefs()->pinnedMessage($a, $pa->fresh()), 'a deleted message is no longer shown as pinned');
        $this->assertSame([], $this->prefs()->starred($a, $cid), 'nor as starred');

        $this->prefs()->star($a, $this->say($b, $cid, 'second')->id);
        $this->assertSame(1, Star::query()->where('user_id', $a->id)->count() - 1 + 0, 'the earlier star row remains but is hidden');
    }

    public function test_search_in_a_conversation_finds_only_what_the_member_may_see(): void
    {
        [$a, $b, $cid] = $this->pair();
        $this->say($a, $cid, 'the invoice is ready');
        $this->say($b, $cid, 'thanks for the INVOICE');
        $this->say($a, $cid, '100% sure_ok');
        $this->assertCount(2, $this->prefs()->search($a, $cid, 'invoice'), 'case-insensitive');
        $this->assertCount(1, $this->prefs()->search($a, $cid, '100%'), 'wildcards in the query are literal');
        $this->assertSame([], $this->prefs()->search($a, $cid, 'i'), 'one letter is not a search');

        // A message dropped by a block is invisible to its would-be reader, so search cannot reveal it either.
        app(MessageService::class)->blockUser($b, $a->id);
        $this->say($a, $cid, 'secretword while blocked');
        $this->assertSame([], $this->prefs()->search($b, $cid, 'secretword'));
        $this->assertCount(1, $this->prefs()->search($a, $cid, 'secretword'), 'its sender still sees it (nothing reveals the block)');
    }

    // ---- themes
    public function test_each_member_sees_only_their_own_theme_and_bad_values_are_refused(): void
    {
        [$a, $b, $cid] = $this->pair();
        $this->prefs()->setTheme($a, $cid, ['accent' => 'rose', 'wallpaper' => 'dunes', 'dim' => 20]);
        $this->prefs()->setTheme($b, $cid, ['accent' => 'ocean', 'wallpaper' => 'topo']);
        $this->assertSame('rose', $this->prefs()->theme($a, $cid)['accent']);
        $this->assertSame('dunes', $this->prefs()->theme($a, $cid)['wallpaper']);
        $this->assertSame('ocean', $this->prefs()->theme($b, $cid)['accent']);
        $this->assertSame('plain', ChatThemes::normalise(null)['wallpaper'], 'no theme is the plain default');

        foreach ([['accent' => 'javascript:alert(1)'], ['wallpaper' => 'url(//evil)'], ['dim' => 99], ['dim' => 'x']] as $bad) {
            try {
                $this->prefs()->setTheme($a, $cid, $bad);
                $this->fail('accepted '.json_encode($bad));
            } catch (MessagingException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame('rose', $this->prefs()->theme($a, $cid)['accent'], 'a refused change leaves the theme alone');
        $this->prefs()->resetTheme($a, $cid);
        $this->assertSame('teal', $this->prefs()->theme($a, $cid)['accent']);
    }

    public function test_every_accent_gives_readable_bubbles_in_light_and_dark_with_evidence(): void
    {
        foreach (array_keys(ChatThemes::ACCENTS) as $accent) {
            $r = ChatThemes::css(['accent' => $accent, 'wallpaper' => 'plain'], '.x');
            foreach ($r['checks'] as $name => $ratio) {
                $this->assertGreaterThanOrEqual(ChatThemes::MIN_RATIO, $ratio, "{$accent} {$name} is {$ratio}:1");
            }
            $this->assertTrue($r['pass']);
        }
        // The worst cases for any palette: mid-tone and near-white accents must be nudged, never shipped unreadable.
        $b = ChatThemes::bubbles('gold', false);
        $this->assertGreaterThanOrEqual(4.5, ChatThemes::ratio($b['out_fg'], $b['out_bg']));
        $d = ChatThemes::bubbles('navy', true);
        $this->assertGreaterThanOrEqual(4.5, ChatThemes::ratio($d['out_fg'], $d['out_bg']), 'a dark accent is lifted on a dark wallpaper');
        $this->assertGreaterThan(ChatThemes::lum(ChatThemes::ACCENTS['navy']['hex']), ChatThemes::lum($d['out_bg']));
    }

    public function test_theme_css_is_built_only_from_allow_listed_values(): void
    {
        $css = ChatThemes::css(['accent' => 'violet', 'wallpaper' => 'ankara', 'dim' => 30], '.nc__msgs[data-nc-theme="7"]', '/n2n/wallpaper/12')['css'];
        $this->assertStringContainsString('html.dark .nc__msgs[data-nc-theme="7"]', $css);
        $this->assertStringNotContainsString('</style', $css);
        $this->assertStringContainsString('/n2n/wallpaper/12', $css);
        $withEvil = ChatThemes::css(['accent' => 'rose'], '.x', '/x");}</style><script>alert(1)</script>')['css'];
        $this->assertStringNotContainsString('<script', $withEvil, 'a photo URL that is not a plain path is ignored');
    }

    public function test_a_wallpaper_photo_is_the_members_own_one_per_chat_and_private(): void
    {
        $disk = MediaStorage::privateDisk();
        Storage::fake($disk);
        [$a, $b, $cid] = $this->pair();
        $png = function () {
            $im = imagecreatetruecolor(60, 40);
            imagefill($im, 0, 0, imagecolorallocate($im, 30, 90, 160));
            ob_start();
            imagepng($im);

            return (string) ob_get_clean();
        };
        $file = function () use ($png) {
            $path = tempnam(sys_get_temp_dir(), 'wp').'.bin';
            file_put_contents($path, $png());

            return new UploadedFile($path, 'w.png', 'image/png', null, true);
        };

        $r1 = $this->actingAs($a)->post(route('n2n.api.wallpaper', $cid), ['photo' => $file()])->assertOk()->json('photo_id');
        $this->assertNotNull($r1);
        $this->get(route('n2n.wallpaper', $r1))->assertOk();
        $this->actingAs($b)->get(route('n2n.wallpaper', $r1))->assertNotFound();      // the other person can never fetch it
        $this->assertSame($r1, $this->prefs()->theme($a, $cid)['photo_id']);
        $this->assertNull($this->prefs()->theme($b, $cid)['photo_id']);

        $r2 = $this->actingAs($a)->post(route('n2n.api.wallpaper', $cid), ['photo' => $file()])->assertOk()->json('photo_id');
        $this->assertNotSame($r1, $r2);
        $this->assertSame(Attachment::DELETED, Attachment::query()->find($r1)->status, 'one per chat: the old photo is removed');
        // Someone else's attachment id cannot be adopted as a wallpaper.
        $this->prefs()->setTheme($b, $cid, ['photo_id' => $r2]);
        $this->assertNull($this->prefs()->theme($b, $cid)['photo_id']);

        $this->actingAs($a)->delete(route('n2n.api.wallpaper.remove', $cid))->assertOk();
        $this->assertNull($this->prefs()->theme($a, $cid)['photo_id']);
        // A non-image is refused.
        $bad = new UploadedFile(tempnam(sys_get_temp_dir(), 'wp'), 'x.png', 'image/png', null, true);
        file_put_contents($bad->getRealPath(), '<?php echo 1;');
        $this->actingAs($a)->post(route('n2n.api.wallpaper', $cid), ['photo' => $bad])->assertStatus(415);
    }

    // ---- gallery, storage, export
    private function attach(User $uploader, int $cid, string $kind = 'image', int $bytes = 2048): Attachment
    {
        $m = $this->say($uploader, $cid, '');
        return $this->mediaFor($m, $uploader, $kind, $bytes);
    }

    private function mediaFor(Message $m, User $uploader, string $kind, int $bytes): Attachment
    {
        $m->forceFill(['type' => $kind])->save();

        return Attachment::query()->create([
            'message_id' => $m->id, 'uploader_id' => $uploader->id, 'kind' => $kind, 'disk' => MediaStorage::privateDisk(), 'storage_key' => 'n2n/media/t/'.uniqid().'.bin', 'mime' => 'image/png',
            'bytes' => $bytes, 'status' => Attachment::ATTACHED,
        ]);
    }

    public function test_the_gallery_lists_media_and_voice_and_only_naara_links_and_hides_for_me(): void
    {
        Storage::fake(MediaStorage::privateDisk());
        [$a, $b, $cid] = $this->pair();
        // A text message with an empty body is refused by the pipeline, so media rows are attached to real text messages here.
        $img = $this->mediaFor($this->say($a, $cid, 'photo caption'), $a, 'image', 3000);
        $vid = $this->mediaFor($this->say($b, $cid, 'clip'), $b, 'video', 9000);
        $voice = $this->mediaFor($this->say($a, $cid, 'note'), $a, 'voice', 1500);
        $lib = app(ChatLibrary::class);

        $this->assertEqualsCanonicalizing([$img->id, $vid->id], array_column($lib->items($a, $cid, 'media'), 'id'));
        $this->assertSame([$voice->id], array_column($lib->items($a, $cid, 'voice'), 'id'));
        $this->assertSame(3, $lib->usage($a, $cid)['count']);
        $this->assertSame(13500, $lib->usage($a, $cid)['bytes']);

        $own = rtrim((string) config('app.url'), '/').'/u/frank';
        $this->say($a, $cid, 'my profile '.$own);
        $links = $lib->links($b, $cid);
        $this->assertCount(1, $links);
        $this->assertSame($own, $links[0]['url']);

        // Delete for me: gone from MY gallery, still in the other person's, object kept until both have removed it.
        $this->assertSame(1, $lib->hideForMe($a, $cid, [$vid->id, 999999]));
        $this->assertNotContains($vid->id, array_column($lib->items($a, $cid, 'media'), 'id'));
        $this->assertContains($vid->id, array_column($lib->items($b, $cid, 'media'), 'id'));
        $this->assertSame(Attachment::ATTACHED, $vid->fresh()->status);
        $this->assertContains($vid->id, $lib->hiddenIds($a, $cid));
        $lib->hideForMe($b, $cid, [$vid->id]);
        $this->assertSame(Attachment::DELETED, $vid->fresh()->status, 'removed from storage only once everybody has removed it');
        $this->assertSame(2, AttachmentHide::query()->where('attachment_id', $vid->id)->count());
    }

    public function test_the_export_is_text_only_private_to_the_asker_and_leaks_no_profile_fields(): void
    {
        [$a, $b, $cid] = $this->pair();
        $b->forceFill(['username' => 'bayo_secret'])->save();
        $this->say($b, $cid, "line one\nline two");
        $mine = $this->say($a, $cid, 'to be deleted');
        app(MessageService::class)->deleteForEveryone($a, $mine->id);
        $txt = app(ChatExport::class)->text($a, $cid);

        $this->assertStringContainsString('Chat with: Bayo', $txt);
        $this->assertStringContainsString('Bayo: line one', $txt);
        $this->assertStringContainsString('    line two', $txt);
        $this->assertStringContainsString('[message deleted]', $txt);
        $this->assertStringNotContainsString('to be deleted', $txt);
        foreach (['bayo_secret', $b->email, (string) $b->naara_id] as $private) {
            $this->assertStringNotContainsString($private, $txt, 'the export carries no profile fields of the other person');
        }
        $this->assertStringContainsString('not included', $txt);

        $res = $this->actingAs($a)->get(route('n2n.chat.export', $cid))->assertOk();
        $this->assertStringContainsString('text/plain', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
        $this->actingAs($this->member('Outsider'))->get(route('n2n.chat.export', $cid))->assertNotFound();
    }

    // ---- the page
    public function test_the_chat_page_exposes_the_actions_and_keeps_them_private(): void
    {
        [$a, $b, $cid] = $this->pair();
        $m = $this->say($b, $cid, 'findable text');

        Livewire::actingAs($a)->test(Chat::class, ['c' => $cid])
            ->call('toggleMute', '1w')->assertSet('notice', 'Muted for 1 week.')
            ->call('toggleStar', $m->id)
            ->set('chatSearch', 'findable')->assertSee('findable text')
            ->call('togglePinMessage', $m->id)
            ->call('saveTheme', 'rose', 'dunes', 10)->assertSet('notice', 'Chat theme saved. Only you can see it.')
            ->call('toggleArchive')->assertSet('c', null);

        $pa = Participant::query()->where('conversation_id', $cid)->where('user_id', $a->id)->first();
        $this->assertTrue(ConversationPrefs::isMuted($pa));
        $this->assertNotNull($pa->archived_at);
        $this->assertSame('rose', $pa->theme_json['accent']);
        $pb = Participant::query()->where('conversation_id', $cid)->where('user_id', $b->id)->first();
        $this->assertNull($pb->theme_json);
        $this->assertNull($pb->archived_at);
        $this->assertSame([], $this->prefs()->starredIds($b, $cid));
    }
}
