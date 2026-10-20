<?php

namespace Tests\Feature;

use App\Support\AppExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The installed app's first-run slides exist on a fresh install and lead to the login page, never the marketing homepage. */
class OnboardingDefaultSlidesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_install_has_four_slides_whose_images_exist(): void
    {
        $slides = AppExport::onboardingSlides();
        $this->assertCount(6, $slides, 'four platform slides plus NaaraChat and the Business Suite on master');
        foreach ($slides as $s) {
            $this->assertFileExists(public_path($s['image']));
            $this->assertNotSame('', $s['title']);
        }
        $this->assertSame('/get-started', AppExport::startPath());
    }

    public function test_get_started_shows_the_slides_to_guests_and_the_dashboard_to_members(): void
    {
        $this->get('/get-started')->assertOk()->assertSee('Choose where you are going')->assertSee('Create account')->assertSee('I already have an account')->assertSee(str_replace('/', '\\/', route('register')), false);
        $this->actingAs(\App\Models\User::factory()->create())->get('/get-started')->assertRedirect(route('dashboard'));
    }

    public function test_admin_slides_are_never_overwritten_by_the_migration(): void
    {
        AppExport::save(['onboarding_slides' => [['image' => '/mine.webp', 'title' => 'Mine', 'subtitle' => '']]]);
        (require database_path('migrations/2027_02_22_100000_seed_default_onboarding_slides.php'))->up();
        $this->assertSame('Mine', AppExport::onboardingSlides()[0]['title']);
    }

    public function test_the_build_config_carries_the_start_path(): void
    {
        $this->assertSame('/get-started', \App\Support\AppStudio::medianConfig()['general']['startPath']);
    }

    public function test_the_chat_and_business_suite_slides_are_master_only_by_their_paths(): void
    {
        foreach (['public/images/onboarding/slide-5-n2n-chat.svg', 'public/images/onboarding/slide-6-business-suite.svg', 'database/migrations/2027_02_23_100000_add_n2n_chat_and_business_suite_onboarding_slides.php'] as $path) {
            $this->assertTrue(\App\Support\MasterOnlyModules::matches($path), $path.' must be seen by the fork boundary guards');
        }
    }

    public function test_customised_slides_are_left_alone_by_the_chat_and_business_suite_migration(): void
    {
        AppExport::save(['onboarding_slides' => [['image' => '/mine.webp', 'title' => 'Mine', 'subtitle' => '']]]);
        (require database_path('migrations/2027_02_23_100000_add_n2n_chat_and_business_suite_onboarding_slides.php'))->up();
        $this->assertCount(1, AppExport::onboardingSlides());
    }
}
