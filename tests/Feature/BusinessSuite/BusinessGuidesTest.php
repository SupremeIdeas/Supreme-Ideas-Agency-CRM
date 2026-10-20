<?php

namespace Tests\Feature\BusinessSuite;

use App\Livewire\BusinessGuides;
use App\Models\User;
use App\Support\Business\BusinessGuideLibrary;
use App\Support\Business\BusinessSuiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/** The Business Suite guide directory: every guide renders, the content is well-formed, and nothing in it points at a page or icon that does not exist. */
class BusinessGuidesTest extends TestCase
{
    use \Tests\Concerns\EnablesBusinessSuite;
    use RefreshDatabase;

    private function member(): User
    {
        return User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    }

    public function test_the_index_lists_every_guide_grouped_by_track(): void
    {
        $r = $this->actingAs($this->member())->get(route('business.guides'))->assertOk();
        foreach (BusinessGuideLibrary::all() as $g) {
            $r->assertSee($g['title'], false);
        }
        foreach (BusinessGuideLibrary::TRACKS as $t) {
            $r->assertSee($t);
        }
    }

    public function test_every_guide_page_renders_with_its_steps_and_a_way_on(): void
    {
        $u = $this->member();
        foreach (BusinessGuideLibrary::all() as $slug => $g) {
            $r = $this->actingAs($u)->get(route('business.guides', ['slug' => $slug]))->assertOk()->assertSee($g['title'], false);
            foreach ($g['blocks'] as $b) {
                if (isset($b['steps'])) {
                    $r->assertSee($b['steps'][0][0], false);
                }
            }
        }
    }

    public function test_an_unknown_guide_is_a_404(): void
    {
        $this->actingAs($this->member())->get('/business/guides/not-a-guide')->assertNotFound();
    }

    public function test_a_visitor_must_sign_in_first(): void
    {
        $this->get(route('business.guides'))->assertRedirect();
    }

    public function test_with_the_suite_switched_off_the_guides_are_not_reachable(): void
    {
        BusinessSuiteSettings::set(false);
        $this->actingAs($this->member())->get(route('business.guides'))->assertDontSee('Everything you need to connect WhatsApp');   // a friendly coming-soon sheet, never the guides
    }

    public function test_the_content_is_well_formed_and_every_reference_is_real(): void
    {
        $sprite = (string) file_get_contents(resource_path('views/partials/icon-sprite.blade.php'));
        $types = ['h', 'p', 'list', 'steps', 'note', 'warn', 'table', 'check', 'cta'];
        $slugs = [];
        foreach (BusinessGuideLibrary::all() as $slug => $g) {
            $slugs[] = $slug;
            $this->assertArrayHasKey($g['track'], BusinessGuideLibrary::TRACKS, $slug);
            $this->assertContains($g['audience'], ['everyone', 'admin'], $slug);
            $this->assertTrue(str_contains($sprite, 'id="i-'.$g['icon'].'"'), "icon {$g['icon']} of {$slug} is not in the sprite");
            $this->assertNotEmpty($g['blocks'], $slug);
            foreach ($g['blocks'] as $b) {
                $this->assertCount(1, array_intersect(array_keys($b), $types), "{$slug}: a block must have exactly one known type");
                if (isset($b['cta'])) {
                    $this->assertTrue(Route::has($b['cta'][1]), "{$slug}: route {$b['cta'][1]} does not exist");
                }
                if (isset($b['table'])) {
                    $cols = count($b['table'][0]);
                    foreach ($b['table'][1] as $row) {
                        $this->assertCount($cols, $row, "{$slug}: a table row has the wrong number of cells");
                    }
                }
                if (isset($b['steps'])) {
                    foreach ($b['steps'] as $st) {
                        $this->assertGreaterThanOrEqual(2, count($st), $slug);
                    }
                }
            }
        }
        $this->assertSame($slugs, array_unique($slugs));
        foreach (['telegram', 'whatsapp', 'instagram'] as $must) {
            $this->assertContains($must, $slugs, 'the three channel guides are the point of the directory');
        }
    }

    public function test_no_guide_ever_contains_a_real_looking_secret(): void
    {
        $text = json_encode(BusinessGuideLibrary::all());
        $this->assertDoesNotMatchRegularExpression('/EAA[A-Za-z0-9]{20,}/', $text);   // a Meta token
        $this->assertDoesNotMatchRegularExpression('/\d{8,10}:AA[A-Za-z0-9_-]{20,}/', $text);   // a Telegram bot token
    }

    public function test_the_guides_are_in_the_business_navigation_and_the_component_mounts(): void
    {
        $this->assertArrayHasKey('guides', \App\Support\Business\BusinessNav::CATALOG);
        Livewire::actingAs($this->member())->test(BusinessGuides::class, ['slug' => 'whatsapp'])->assertSee('24-hour rule');
    }
}
