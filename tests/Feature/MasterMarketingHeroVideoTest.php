<?php

namespace Tests\Feature;

use App\Livewire\Admin\MasterMarketingVideoSections;
use App\Models\MasterMarketingVideoSection;
use App\Models\User;
use App\Support\MasterMarketing\VideoSlots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The hero video card on every master marketing page (Admin > Marketing videos). */
class MasterMarketingHeroVideoTest extends TestCase
{
    use RefreshDatabase;

    private function video(string $slot, array $over = []): MasterMarketingVideoSection
    {
        return MasterMarketingVideoSection::create($over + [
            'slot' => $slot, 'title' => 'See Naara in two minutes', 'source_type' => 'url', 'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'is_visible' => true, 'position' => 1, 'label' => 'Product tour', 'duration_label' => '2:00',
        ]);
    }

    public function test_every_marketing_page_has_a_hero_slot_and_the_layout_maps_routes_to_them(): void
    {
        foreach (['home', 'about', 'how-it-works', 'contact', 'pricing', 'download', 'products', 'business-suite', 'chat', 'brain', 'gift', 'white-label', 'developers'] as $p) {
            $this->assertTrue(VideoSlots::known($p.'.hero'), $p);
        }
        $this->assertSame('home.hero', VideoSlots::heroFor('home'));
        $this->assertSame('chat.hero', VideoSlots::heroFor('n2n.landing'));
        $this->assertNull(VideoSlots::heroFor('login'));
    }

    public function test_a_hero_video_renders_as_a_card_with_label_poster_fallbacks_and_structured_data(): void
    {
        $this->video('home.hero', ['poster_url' => '/storage/marketing-video/p.webp']);

        $this->get(route('home'))->assertOk()
            ->assertSee('data-video-deck="home.hero"', false)->assertSee('Product tour')->assertSee('See Naara in two minutes')->assertSee('2:00')
            ->assertSee('marketing-video', false)->assertSee('p.webp', false)->assertSee('"@type":"VideoObject"', false)
            ->assertSee('dQw4w9WgXcQ', false);
    }

    public function test_no_video_means_no_card_and_a_hidden_or_other_page_video_never_leaks(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('data-video-deck', false);
        $this->video('about.hero', ['is_visible' => false]);
        $this->video('contact.hero');
        $this->get(route('about'))->assertOk()->assertDontSee('data-video-deck', false);
        $this->get(route('home'))->assertOk()->assertDontSee('data-video-deck', false);
        $this->get(route('contact'))->assertOk()->assertSee('data-video-deck="contact.hero"', false);
    }

    public function test_an_uploaded_file_gets_a_video_element_with_metadata_preload_and_no_autoplay_unless_asked(): void
    {
        $this->video('about.hero', ['source_type' => 'upload', 'video_url' => '/storage/marketing-video/a.mp4', 'autoplay' => false]);
        $html = $this->get(route('about'))->assertOk()->getContent();
        $this->assertStringContainsString('preload="metadata"', $html);
        $this->assertStringContainsString('a.mp4', $html);
        $this->assertStringContainsString('u0022preview\u0022:false', $html);
        $this->assertStringContainsString('nodownload', $html);
    }

    public function test_admin_can_add_a_hero_video_with_label_and_length_and_bad_values_are_refused(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)->test(MasterMarketingVideoSections::class)
            ->set('slot', 'home.hero')->set('title', 'Tour')->set('source', 'url')->set('url', 'https://youtu.be/dQw4w9WgXcQ')->set('label', 'Product tour')->set('duration', '2:30')
            ->call('add')->assertHasNoErrors();
        $row = MasterMarketingVideoSection::firstOrFail();
        $this->assertSame(['home.hero', 'Product tour', '2:30'], [$row->slot, $row->label, $row->duration_label]);

        Livewire::actingAs($admin)->test(MasterMarketingVideoSections::class)
            ->set('slot', 'home.hero')->set('title', 'Bad')->set('source', 'url')->set('url', 'https://youtu.be/dQw4w9WgXcQ')->set('duration', 'long')->call('add')->assertHasErrors('duration');
        Livewire::actingAs($admin)->test(MasterMarketingVideoSections::class)
            ->set('slot', 'home.hero')->set('title', 'No file')->set('source', 'upload')->call('add')->assertHasErrors('fileUrl');
        $this->assertSame(1, MasterMarketingVideoSection::count());
    }
}
