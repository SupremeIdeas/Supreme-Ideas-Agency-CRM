<?php

namespace Tests\Feature\N2N;

use App\Livewire\N2N\StatusComposer;
use App\Models\BrandPartner;
use App\Models\N2N\StatusMention;
use App\Models\N2N\StatusPost;
use App\Models\Setting;
use App\Models\User;
use App\Services\N2N\Connections\Connections;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Updates\StatusService;
use App\Services\N2N\Updates\UpdatesService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/** Status mentions and the in-app camera switches (Naara Chat, MASTER-ONLY). */
class StatusMentionsTest extends N2NTestCase
{
    private function member(string $name): User
    {
        return $this->user(['name' => $name, 'naara_id' => NaaraId::generate(), 'username' => 'u'.substr(md5(uniqid()), 0, 8)]);
    }

    private function pro(string $name = 'Poster'): User
    {
        $u = $this->member($name);
        BrandPartner::create(['owner_user_id' => $u->id, 'brand_name' => 'Brand '.$u->id, 'listing_status' => 'active', 'is_active' => true, 'pro_granted_at' => now()]);

        return $u;
    }

    private function connect(User $a, User $b): void
    {
        app(Connections::class)->follow($a, $b);
        app(Connections::class)->follow($b, $a);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Setting::setValue('n2n.status.mode', 'on', 'n2n');
    }

    public function test_only_connected_visible_people_whose_name_is_in_the_text_are_tagged_and_each_is_told_once(): void
    {
        Notification::fake();
        $owner = $this->pro();
        $ada = $this->member('Ada Obi');
        $bola = $this->member('Bola Ade');
        $hidden = $this->member('Hidden Hana');
        $stranger = $this->member('Stranger Sam');
        $absent = $this->member('Absent Abe');
        foreach ([$ada, $bola, $hidden, $absent] as $m) {
            $this->connect($owner, $m);
        }
        app(StatusService::class)->setHiddenFrom($owner, [$hidden->id]);

        $post = app(StatusService::class)->postText($owner, 'Dinner with @Ada Obi and @Bola Ade and @Hidden Hana and @Stranger Sam', '#0A6E6E', 'sans', [$ada->id, $bola->id, $hidden->id, $stranger->id, $absent->id, $ada->id]);

        $tagged = StatusMention::query()->where('status_id', $post->id)->pluck('user_id')->all();
        sort($tagged);
        $this->assertSame([$ada->id, $bola->id], $tagged, 'a hidden member, a stranger and a name not in the text are all refused, and a repeat is counted once');
        Notification::assertSentToTimes($ada, \App\Notifications\EventNotification::class, 1);
        Notification::assertNotSentTo($hidden, \App\Notifications\EventNotification::class);
        Notification::assertNotSentTo($stranger, \App\Notifications\EventNotification::class);
    }

    public function test_at_most_five_people_can_be_tagged(): void
    {
        Notification::fake();
        $owner = $this->pro();
        $people = [];
        $text = 'Hello';
        for ($i = 1; $i <= 7; $i++) {
            $p = $this->member('Friend Number'.$i);
            $this->connect($owner, $p);
            $people[] = $p->id;
            $text .= ' @Friend Number'.$i;
        }
        $post = app(StatusService::class)->postText($owner, $text, null, null, $people);
        $this->assertSame(StatusService::MAX_MENTIONS, StatusMention::query()->where('status_id', $post->id)->count());
    }

    public function test_the_switch_turns_tagging_off_without_blocking_the_post(): void
    {
        $owner = $this->pro();
        $ada = $this->member('Ada Obi');
        $this->connect($owner, $ada);
        Setting::setValue('n2n.status.mentions', false, 'n2n');
        $post = app(StatusService::class)->postText($owner, 'Hi @Ada Obi', null, null, [$ada->id]);
        $this->assertSame('Hi @Ada Obi', $post->body);
        $this->assertSame(0, StatusMention::query()->count());
    }

    public function test_a_photo_caption_can_tag_and_the_tagged_viewer_sees_it_flagged_while_others_do_not(): void
    {
        Notification::fake();
        $owner = $this->pro();
        $ada = $this->member('Ada Obi');
        $bola = $this->member('Bola Ade');
        $this->connect($owner, $ada);
        $this->connect($owner, $bola);
        $im = imagecreatetruecolor(20, 20);
        ob_start();
        imagepng($im);
        $png = UploadedFile::fake()->createWithContent('a.png', (string) ob_get_clean());
        $post = app(StatusService::class)->postMedia($owner, $png, 'image', 'Beach day with @Ada Obi', null, [$ada->id]);
        $brandId = BrandPartner::query()->where('owner_user_id', $owner->id)->value('id');

        $forAda = app(UpdatesService::class)->items($ada, $brandId, 'status');
        $forBola = app(UpdatesService::class)->items($bola, $brandId, 'status');
        $this->assertTrue($forAda[0]['mentioned_you']);
        $this->assertFalse($forBola[0]['mentioned_you']);
        $this->assertSame(1, $forBola[0]['mentions']);
        $this->assertSame([$ada->id], array_column(app(StatusService::class)->mentionsOf($post->id), 'id'));
    }

    public function test_mentions_go_with_the_post_and_with_either_members_erasure(): void
    {
        Notification::fake();
        $owner = $this->pro();
        $ada = $this->member('Ada Obi');
        $this->connect($owner, $ada);
        $post = app(StatusService::class)->postText($owner, 'Hi @Ada Obi', null, null, [$ada->id]);
        $this->assertSame(1, StatusMention::query()->count());
        app(StatusService::class)->destroy($post);
        $this->assertSame(0, StatusMention::query()->count());

        $again = app(StatusService::class)->postText($owner, 'Again @Ada Obi', null, null, [$ada->id]);
        $this->assertSame(1, StatusMention::query()->where('status_id', $again->id)->count());
        app(\App\Services\N2N\N2NErasure::class)->erase($ada);
        $this->assertSame(0, StatusMention::query()->count());
    }

    public function test_the_composer_passes_tags_through_and_offers_only_people_who_can_see_the_status(): void
    {
        Notification::fake();
        $owner = $this->pro();
        $ada = $this->member('Ada Obi');
        $hana = $this->member('Hidden Hana');
        $this->connect($owner, $ada);
        $this->connect($owner, $hana);
        app(StatusService::class)->setHiddenFrom($owner, [$hana->id]);
        $c = Livewire::actingAs($owner)->test(StatusComposer::class);
        $names = collect($c->viewData('mentionables'))->pluck('name')->all();
        $this->assertSame(['Ada Obi'], $names);
        $c->set('text', 'Hi @Ada Obi')->set('mentionIds', [$ada->id])->call('postText');
        $this->assertSame(1, StatusMention::query()->where('user_id', $ada->id)->count());
        $this->assertSame([], $c->get('mentionIds'), 'the tags are cleared after posting');
    }

    public function test_the_camera_switch_only_changes_what_is_offered(): void
    {
        $owner = $this->pro();
        $this->actingAs($owner);
        Livewire::test(StatusComposer::class)->assertSee('data-status-camera', false)->assertSee('Take photo');
        Setting::setValue('n2n.status.camera', false, 'n2n');
        Livewire::test(StatusComposer::class)->assertSee('The camera is not offered right now.')->assertSee('Choose from gallery');
        $this->assertSame(0, StatusPost::query()->count());
    }
}
