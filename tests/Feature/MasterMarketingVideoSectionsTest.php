<?php

namespace Tests\Feature;

use App\Livewire\Admin\MasterMarketingVideoSections;
use App\Models\MasterMarketingVideoSection;
use App\Models\User;
use App\Support\MasterMarketing\VideoSlots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MasterMarketingVideoSectionsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('super_admin', 'web');
        $u = User::factory()->create();
        $u->assignRole('super_admin');

        return $u;
    }

    private function make(array $o = []): MasterMarketingVideoSection
    {
        return MasterMarketingVideoSection::create($o + ['slot' => 'chat.intro', 'title' => 'Demo', 'source_type' => 'url', 'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'is_visible' => true, 'position' => 0]);
    }

    public function test_empty_slot_renders_nothing(): void
    {
        $this->assertSame('', trim(view('marketing.master-marketing._video-slot', ['slot' => 'chat.intro'])->render()));
    }

    public function test_live_video_renders_and_hide_takes_effect_immediately(): void
    {
        $v = $this->make();
        $this->assertStringContainsString('Demo', view('marketing.master-marketing._video-slot', ['slot' => 'chat.intro'])->render());
        $this->assertStringContainsString('youtube-nocookie.com', view('marketing.master-marketing._video-slot', ['slot' => 'chat.intro'])->render());

        $v->update(['is_visible' => false]);
        $this->assertSame('', trim(view('marketing.master-marketing._video-slot', ['slot' => 'chat.intro'])->render()));
    }

    public function test_schedule_window_is_respected(): void
    {
        $this->make(['starts_at' => now()->addDay()]);
        $this->assertSame([], VideoSlots::for('chat.intro'));
        MasterMarketingVideoSection::query()->update(['starts_at' => now()->subDay(), 'ends_at' => now()->subHour()]);
        VideoSlots::flush();
        $this->assertSame([], VideoSlots::for('chat.intro'));
    }

    public function test_unknown_slot_and_unsafe_link_are_refused(): void
    {
        $this->assertSame([], VideoSlots::for('nope'));
        Livewire::actingAs($this->admin())->test(MasterMarketingVideoSections::class)
            ->set('slot', 'chat.intro')->set('title', 'X')->set('url', 'javascript:alert(1)')->call('add')
            ->assertHasErrors('url');
        $this->assertSame(0, MasterMarketingVideoSection::count());
    }

    public function test_admin_can_add_toggle_reorder_and_delete(): void
    {
        $c = Livewire::actingAs($this->admin())->test(MasterMarketingVideoSections::class)
            ->set('slot', 'gift.intro')->set('title', 'A')->set('url', 'https://vimeo.com/76979871')->call('add')->assertHasNoErrors();
        $c->set('title', 'B')->set('url', 'https://youtu.be/dQw4w9WgXcQ')->call('add');
        [$a, $b] = MasterMarketingVideoSection::orderBy('id')->get()->all();
        $c->call('move', $b->id, 'up');
        $this->assertSame(['B', 'A'], collect(VideoSlots::for('gift.intro'))->pluck('title')->all());
        $c->call('toggle', $a->id);
        $this->assertCount(1, VideoSlots::for('gift.intro'));
        $c->call('delete', $b->id);
        $this->assertSame([], VideoSlots::for('gift.intro'));
    }

    public function test_non_admin_gets_404(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $this->actingAs(User::factory()->create())->get('/adminmaster/marketing-videos')->assertNotFound();
    }

    public function test_verify_check_flags_unplayable_link_and_missing_poster(): void
    {
        $check = new \App\Support\Verify\Checks\MasterMarketingVideosSound;
        $this->assertSame('pass', $check->run()->status);
        $this->make(['video_url' => 'https://example.com/page.html']);
        $this->assertSame('fail', $check->run()->status);
    }
}
