<?php

namespace Tests\Feature\N2N;

use App\Models\BrandPartner;
use App\Models\BrandPartnerImage;
use App\Models\N2N\StatusPost;
use App\Models\Setting;
use App\Models\User;
use App\Services\N2N\Connections\Connections;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Media\NaaraPro;
use App\Services\N2N\Messaging\MessagingException;
use App\Services\N2N\Updates\StatusService;
use App\Services\N2N\Updates\UpdatesService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Naara Chat status (MASTER-ONLY): switch, limits, 24-hour purge that really deletes the stored file, Highlights vs Latest, and who can fetch the media. */
class StatusTest extends N2NTestCase
{
    private function member(): User
    {
        return $this->user(['naara_id' => NaaraId::generate(), 'username' => 'u'.substr(md5(uniqid()), 0, 8)]);
    }

    private function pro(int $images = 2): array
    {
        $u = $this->member();
        $b = BrandPartner::create(['owner_user_id' => $u->id, 'brand_name' => 'Brand '.$u->id, 'listing_status' => 'active', 'is_active' => true, 'pro_granted_at' => now()]);
        for ($i = 0; $i < $images; $i++) {
            BrandPartnerImage::create(['brand_partner_id' => $b->id, 'image_path' => "https://cdn.test/{$u->id}-{$i}.webp", 'caption' => "Cap $i", 'sort_order' => $i]);
        }

        return [$u, $b];
    }

    private function connect(User $a, User $b): void
    {
        app(Connections::class)->follow($a, $b);
        app(Connections::class)->follow($b, $a);
    }

    private function on(): void
    {
        Setting::setValue('n2n.status.mode', 'on', 'n2n');
    }

    private function png(): UploadedFile
    {
        $im = imagecreatetruecolor(20, 20);
        ob_start();
        imagepng($im);

        return UploadedFile::fake()->createWithContent('a.png', (string) ob_get_clean());
    }

    public function test_a_reply_to_a_status_carries_a_thumbnail_card_for_each_kind(): void
    {
        [$owner, $brand] = $this->pro(0);
        $viewer = $this->member();
        $this->on();
        $this->connect($owner, $viewer);
        app(\App\Services\N2N\Credit\UsageLedgerService::class)->apply([[$viewer->id, 'shareable', 100000, 'seed', 'seed:'.$viewer->id.uniqid(), null, []]]);
        $svc = app(StatusService::class);
        $img = $svc->postMedia($owner, $this->png(), 'image', 'Sunset', null);
        $txt = $svc->postText($owner, 'Open for orders today', '#0A6E6E', null);
        $up = app(\App\Services\N2N\Updates\UpdatesService::class);

        $r = $up->reply($viewer, $brand->id, $img->id, 'Gorgeous', 'upd-card-0001', null, 'status');
        $q = $r['message']->quote_json;
        $this->assertSame('status', $q['source']);
        $this->assertSame('image', $q['type']);
        $this->assertSame('/n2n/status/'.$img->id.'/media', $q['media']);

        $r2 = $up->reply($viewer, $brand->id, $txt->id, 'Count me in', 'upd-card-0002', null, 'status');
        $q2 = $r2['message']->quote_json;
        $this->assertNull($q2['media']);
        $this->assertSame('Open for orders today', $q2['swatch']['text']);
        $this->assertSame('#0A6E6E', $q2['swatch']['bg']);
    }

    public function test_auto_mode_is_off_without_cloud_storage_and_on_mode_overrides_it(): void
    {
        [$u] = $this->pro();
        $svc = app(StatusService::class);
        $this->assertFalse($svc->canPost($u), 'auto + no cloud store = off');
        $this->on();
        $this->assertTrue($svc->canPost($u));
        $this->assertSame(['text', 'image', 'voice'], \App\Support\N2N\N2NStatusSettings::allowedKinds(), 'no cloud: no video');
        Setting::setValue('n2n.status.mode', 'off', 'n2n');
        $this->assertFalse($svc->canPost($u));
    }

    public function test_only_naara_pro_members_post_and_the_daily_limit_applies(): void
    {
        $this->on();
        Setting::setValue('n2n.status.daily_limit', 2, 'n2n');
        $svc = app(StatusService::class);
        $plain = $this->member();
        try {
            $svc->postText($plain, 'hi', null, null);
            $this->fail('a non-Pro member posted');
        } catch (MessagingException $e) {
            $this->assertSame('not_pro', $e->reason);
        }
        [$u] = $this->pro();
        $svc->postText($u, 'one', '#112233', 'serif');
        $svc->postText($u, 'two', 'not-a-colour', 'nope');
        $this->assertSame('#0A6E6E', StatusPost::query()->latest('id')->first()->bg);
        $this->expectException(MessagingException::class);
        $svc->postText($u, 'three', null, null);
    }

    public function test_purge_deletes_the_stored_file_before_the_row_and_only_for_expired_posts(): void
    {
        $this->on();
        Storage::fake(\App\Support\N2N\N2NStatusSettings::disk());
        [$u] = $this->pro();
        $svc = app(StatusService::class);
        $keep = $svc->postMedia($u, $this->png(), 'image', null);
        $old = $svc->postMedia($u, $this->png(), 'image', null);
        $disk = Storage::disk($old->disk);
        $this->assertTrue($disk->exists($old->storage_key));
        $old->forceFill(['expires_at' => now()->subMinute()])->save();

        $r = $svc->purgeExpired();
        $this->assertSame(['deleted' => 1, 'failed' => 0], $r);
        $this->assertFalse($disk->exists($old->storage_key), 'the object is gone, not just the row');
        $this->assertNull(StatusPost::find($old->id));
        $this->assertTrue($disk->exists($keep->storage_key));
        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('n2n:purge-status'));
    }

    public function test_a_rejected_upload_is_never_stored(): void
    {
        $this->on();
        Storage::fake(\App\Support\N2N\N2NStatusSettings::disk());
        [$u] = $this->pro();
        $bad = UploadedFile::fake()->createWithContent('x.png', '<?php echo 1; ?> not an image at all, long enough');
        try {
            app(StatusService::class)->postMedia($u, $bad, 'image', null);
            $this->fail('accepted');
        } catch (MessagingException $e) {
            $this->assertSame('type_not_allowed', $e->reason);
        }
        $this->assertSame([], Storage::disk(\App\Support\N2N\N2NStatusSettings::disk())->allFiles());
    }

    public function test_latest_shows_the_status_and_falls_back_to_the_gallery_while_highlights_always_show(): void
    {
        $this->on();
        [$owner, $brand] = $this->pro(2);
        $viewer = $this->member();
        $this->connect($viewer, $owner);
        $up = app(UpdatesService::class);

        $rings = $up->rings($viewer);
        $this->assertSame('gallery', $rings[0]['source'], 'no status today: the gallery keeps the Latest row alive');
        $this->assertCount(2, $up->items($viewer, $brand->id));

        app(StatusService::class)->postText($owner, 'Open late tonight', '#2F5BD9', 'bold');
        $rings = $up->rings($viewer);
        $this->assertSame('status', $rings[0]['source']);
        $items = $up->items($viewer, $brand->id);
        $this->assertCount(1, $items);
        $this->assertSame('text', $items[0]['type']);
        $this->assertCount(2, $up->items($viewer, $brand->id, 'gallery'), 'the gallery is still there as Highlights');
        $this->assertSame(2, $up->highlights($viewer)[0]['count']);

        Setting::setValue('n2n.status.mode', 'off', 'n2n');   // switched off: highlights and the gallery Latest row carry on
        $this->assertSame('gallery', $up->rings($viewer)[0]['source']);
        $this->assertCount(1, $up->highlights($viewer));
    }

    public function test_an_expired_status_disappears_even_before_the_purge_runs(): void
    {
        $this->on();
        [$owner, $brand] = $this->pro(0);
        $viewer = $this->member();
        $this->connect($viewer, $owner);
        $p = app(StatusService::class)->postText($owner, 'gone soon', null, null);
        $this->assertCount(1, app(UpdatesService::class)->rings($viewer));
        $p->forceFill(['expires_at' => now()->subSecond()])->save();
        $this->assertSame([], app(UpdatesService::class)->rings($viewer));
    }

    public function test_status_media_is_served_only_to_the_owner_or_a_connected_viewer(): void
    {
        $this->on();
        Storage::fake(\App\Support\N2N\N2NStatusSettings::disk());
        [$owner] = $this->pro();
        $friend = $this->member();
        $stranger = $this->member();
        $this->connect($friend, $owner);
        $post = app(StatusService::class)->postMedia($owner, $this->png(), 'image', null);
        $url = route('n2n.status.media', $post->id);
        $this->actingAs($owner)->get($url)->assertOk();
        $this->actingAs($friend)->get($url)->assertOk();
        $this->actingAs($stranger)->get($url)->assertNotFound();
        $post->forceFill(['expires_at' => now()->subSecond()])->save();
        $this->actingAs($owner)->get($url)->assertNotFound();
    }

    public function test_owner_delete_removes_the_file_now(): void
    {
        $this->on();
        Storage::fake(\App\Support\N2N\N2NStatusSettings::disk());
        [$u] = $this->pro();
        $svc = app(StatusService::class);
        $p = $svc->postMedia($u, $this->png(), 'image', null);
        $this->assertTrue($svc->delete($u, $p->id));
        $this->assertSame([], Storage::disk(\App\Support\N2N\N2NStatusSettings::disk())->allFiles());
        $this->assertFalse($svc->delete($this->member(), $p->id), 'not yours');
    }

    public function test_the_top_plan_is_unlimited_and_a_plan_override_wins(): void
    {
        $this->on();
        Setting::setValue('n2n.status.daily_limit', 3, 'n2n');
        [$u, $b] = $this->pro();
        $low = \App\Models\BrandSubscriptionPlan::create(['name' => 'Starter', 'price_usd_per_month' => 5, 'handles_included' => 1, 'guaranteed_followers_per_handle_per_month' => 0, 'video_previews_allowed' => 0, 'credit_reward_per_follow' => 1, 'is_active' => true, 'sort_order' => 1]);
        $top = \App\Models\BrandSubscriptionPlan::create(['name' => 'Elite', 'price_usd_per_month' => 50, 'handles_included' => 5, 'guaranteed_followers_per_handle_per_month' => 0, 'video_previews_allowed' => 0, 'credit_reward_per_follow' => 1, 'is_active' => true, 'sort_order' => 2]);
        $sub = \App\Models\BrandSubscription::create(['brand_partner_id' => $b->id, 'plan_id' => $low->id, 'status' => 'active', 'started_at' => now()]);
        $this->assertSame(3, app(StatusService::class)->limitFor($u));
        $sub->forceFill(['plan_id' => $top->id])->save();
        $this->assertSame(StatusService::UNLIMITED, app(StatusService::class)->limitFor($u->fresh()));
        $top->forceFill(['capabilities' => ['status_per_day' => 9]])->save();
        $this->assertSame(9, app(StatusService::class)->limitFor($u->fresh()));
    }

    public function test_the_updates_tab_and_composer_render_for_a_pro_member_and_post_text(): void
    {
        $this->on();
        [$owner] = $this->pro();
        \Livewire\Livewire::actingAs($owner)->test(\App\Livewire\N2N\Updates::class)->assertSee('Add status');
        \Livewire\Livewire::actingAs($owner)->test(\App\Livewire\N2N\StatusComposer::class)
            ->set('text', 'Hello Naara')->set('bg', '#B33FB3')->set('font', 'script')->call('postText')->assertHasNoErrors()->assertDispatched('status-posted');
        $this->assertSame('Hello Naara', StatusPost::first()->body);
        \Livewire\Livewire::actingAs($this->member())->test(\App\Livewire\N2N\StatusComposer::class)->assertStatus(404);
    }

    public function test_the_owner_sees_who_viewed_and_reacted_and_a_viewer_reacts_once_replaceably(): void
    {
        $this->on();
        [$owner, $brand] = $this->pro();
        $viewer = $this->member();
        $this->connect($viewer, $owner);
        $post = app(StatusService::class)->postText($owner, 'Hello', null, null);
        $up = app(UpdatesService::class);
        \Livewire\Livewire::actingAs($viewer)->test(\App\Livewire\N2N\Updates::class)->call('view', $brand->id, 'status')->call('reactStatus', $post->id, '❤️')->call('reactStatus', $post->id, '😂');
        $this->assertSame(1, \App\Models\N2N\StatusReaction::count(), 'one reaction per viewer, replaced');
        $this->assertSame('😂', $up->items($viewer, $brand->id, 'status')[0]['reaction']);
        $v = app(StatusService::class)->viewers($owner, $post->id);
        $this->assertSame($viewer->name, $v[0]['name']);
        $this->assertSame('😂', $v[0]['emoji']);
        $this->assertSame([], app(StatusService::class)->viewers($viewer, $post->id), 'only the owner sees the list');
        $this->assertFalse($up->reactToStatus($viewer, $brand->id, $post->id, 'not-an-emoji'));
        $stranger = $this->member();
        $this->assertFalse($up->reactToStatus($stranger, $brand->id, $post->id, '❤️'), 'an unconnected member cannot react');
    }

    public function test_an_owner_can_hide_their_status_from_a_member_who_then_sees_the_gallery_instead(): void
    {
        $this->on();
        [$owner, $brand] = $this->pro(2);
        [$a, $b] = [$this->member(), $this->member()];
        $this->connect($a, $owner);
        $this->connect($b, $owner);
        $post = app(StatusService::class)->postText($owner, 'Secret sale', null, null);
        $svc = app(StatusService::class);
        $svc->setHiddenFrom($owner, [$b->id, 999999]);   // a non-connection id is ignored
        $this->assertSame([$b->id], $svc->hiddenFrom($owner));
        $up = app(UpdatesService::class);
        $this->assertSame('status', $up->rings($a)[0]['source']);
        $this->assertSame('gallery', $up->rings($b)[0]['source'], 'the hidden member just sees the profile gallery');
        $this->assertCount(2, $up->items($b, $brand->id));
        Storage::fake(\App\Support\N2N\N2NStatusSettings::disk());
        $media = $svc->postMedia($owner, $this->png(), 'image', null);
        $this->actingAs($b)->get(route('n2n.status.media', $media->id))->assertNotFound();
        $this->actingAs($a)->get(route('n2n.status.media', $media->id))->assertOk();
        $svc->setHiddenFrom($owner, []);
        $this->assertSame('status', $up->rings($b)[0]['source']);
    }

    public function test_the_composer_shows_views_and_saves_the_audience(): void
    {
        $this->on();
        [$owner, $brand] = $this->pro();
        $friend = $this->member();
        $this->connect($friend, $owner);
        $post = app(StatusService::class)->postText($owner, 'Hi all', null, null);
        app(StatusService::class)->markViewed($friend, $post->id);
        \Livewire\Livewire::actingAs($owner)->test(\App\Livewire\N2N\StatusComposer::class)->assertSee('1 view')->call('toggleViewers', $post->id)->assertSee($friend->name)
            ->set('audienceOpen', true)->set('hidden', [$friend->id])->call('saveAudience');
        $this->assertSame([$friend->id], app(StatusService::class)->hiddenFrom($owner));
    }
}
