<?php

namespace Tests\Feature\Omni;

use App\Livewire\Admin\OmniSetupGuide;
use App\Models\User;
use App\Support\Omni\CredentialFormatChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Admin > Respond setup guide: every section renders with real addresses, samples are fake, the checker catches the common slips, and only admins open it. */
class OmniSetupGuideTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin');
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole('admin');

        return $u;
    }

    public function test_the_guide_content_is_complete_and_internally_consistent(): void
    {
        $g = config('omni_guides');
        $this->assertSame(array_keys($g['sections']), $g['order'], 'every section is in the order list exactly once');
        foreach ($g['sections'] as $key => $s) {
            foreach (['title', 'minutes', 'intro', 'steps', 'mistakes', 'verify'] as $f) {
                $this->assertArrayHasKey($f, $s, "$key needs $f");
            }
            $this->assertNotEmpty($s['steps'], "$key has steps");
            foreach ($s['steps'] as $st) {
                $this->assertNotEmpty($st['title']);
                $this->assertGreaterThan(30, strlen($st['body']), "$key / {$st['title']} needs a real explanation");
                if (! empty($st['sample'])) {
                    $this->assertArrayHasKey($st['sample'], $g['samples']);
                }
                if (! empty($st['url'])) {
                    $this->assertContains($st['url'], ['whatsapp_platform', 'whatsapp_workspace']);
                }
            }
            foreach ($s['samples'] as $sk) {
                $this->assertArrayHasKey($sk, $g['samples']);
            }
        }
    }

    public function test_every_fake_sample_is_recognised_by_the_checker_as_the_right_kind(): void
    {
        $expect = ['telegram_token' => 'Telegram bot token', 'meta_app_id' => 'Meta ID', 'meta_app_secret' => 'Meta App Secret', 'ig_id' => 'Instagram account ID', 'anthropic_key' => 'Anthropic API key', 'domain_txt' => 'domain verification'];
        foreach ($expect as $key => $kind) {
            $r = CredentialFormatChecker::check(config('omni_guides.samples.'.$key.'.fake'));
            $this->assertStringContainsString($kind, (string) $r['kind'], $key);
        }
        $this->assertSame('Meta access token', CredentialFormatChecker::check('EAAJ'.str_repeat('x', 120))['kind']);
    }

    public function test_the_checker_catches_the_slips_people_actually_make(): void
    {
        $this->assertStringContainsString('phone number', CredentialFormatChecker::check('+2348012345678')['message']);
        $this->assertStringContainsStringIgnoringCase('not an App Secret', CredentialFormatChecker::check('2195469598059043')['message']);
        $half = CredentialFormatChecker::check('123456789:AAH-abc');
        $this->assertFalse($half['ok']);
        $this->assertStringContainsString('cut off', $half['message']);
        $quoted = CredentialFormatChecker::check('  "a1b2c3d4e5f60718293a4b5c6d7e8f90"  ');
        $this->assertFalse($quoted['ok']);
        $this->assertGreaterThanOrEqual(2, count($quoted['fixes']));
        $bearer = CredentialFormatChecker::check('Bearer EAAJ'.str_repeat('x', 120));
        $this->assertSame('Meta access token', $bearer['kind']);
        $this->assertNotEmpty($bearer['fixes']);
        $this->assertTrue(CredentialFormatChecker::check('a1b2c3d4e5f60718293a4b5c6d7e8f90')['ok']);
        $this->assertSame('Web address', CredentialFormatChecker::check('https://naara.app/webhooks/whatsapp')['kind']);
        $this->assertStringContainsString('Nothing to check', CredentialFormatChecker::check('   ')['message']);
    }

    public function test_only_admins_open_the_page_and_the_sidebar_links_to_it(): void
    {
        $this->get('/'.config('admin.path', 'adminmaster').'/respond-setup-guide')->assertRedirect();
        $this->actingAs(User::factory()->create(['is_active' => true]))->get(route('admin.respond-setup-guide'))->assertNotFound();
        $this->actingAs($this->admin())->get(route('admin.respond-setup-guide'))->assertOk()->assertSee('Respond setup guide')->assertSee('Respond Setup Guide');
    }

    public function test_every_section_renders_and_shows_the_real_callback_address(): void
    {
        config(['app.url' => 'https://naara.app']);
        $t = Livewire::actingAs($this->admin())->test(OmniSetupGuide::class);
        foreach (config('omni_guides.order') as $key) {
            $t->call('open', $key)->assertSee(config("omni_guides.sections.$key.title"), false);
        }
        $t->call('open', 'whatsapp')->assertSee('https://naara.app/webhooks/whatsapp')->assertSee('Mistakes that break this setup')->assertSee('EAA', false);
        $t->call('open', 'meta_app')->assertSee('2195469598059043')->assertSee('Where your credentials stand right now');
    }

    public function test_a_non_https_app_url_gets_a_loud_warning(): void
    {
        config(['app.url' => 'http://localhost:8000']);
        Livewire::actingAs($this->admin())->test(OmniSetupGuide::class)->assertSee('Meta and Telegram only accept an https address');
        config(['app.url' => 'https://naara.app']);
        Livewire::actingAs($this->admin())->test(OmniSetupGuide::class)->assertDontSee('Meta and Telegram only accept an https address');
    }

    public function test_the_pasted_value_is_cleared_and_never_echoed_back(): void
    {
        $secret = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';
        $t = Livewire::actingAs($this->admin())->test(OmniSetupGuide::class)->set('probe', $secret)->call('checkValue')->assertSet('probe', '')->assertSee('Meta App Secret');
        $this->assertStringNotContainsString($secret, json_encode($t->get('result')));
    }

    public function test_the_admin_can_save_upload_rules_and_they_are_clamped(): void
    {
        Livewire::actingAs($this->admin())->test(OmniSetupGuide::class)->assertSee('Upload rules for business knowledge')
            ->set('ruleValues.min_bytes', 300)->set('ruleValues.max_files', 8)->call('saveRules')->assertHasNoErrors()->assertSee('Saved');
        $this->assertSame(300, \App\Services\Omni\Kb\DocumentLimits::all()['min_bytes']);
        $this->assertSame(8, \App\Services\Omni\Kb\DocumentLimits::maxFiles());
        Livewire::actingAs($this->admin())->test(OmniSetupGuide::class)->set('ruleValues.max_mb', 50)->call('saveRules')->assertHasErrors('ruleValues.max_mb');
    }

    public function test_the_guide_maps_every_meta_value_to_its_screen_and_covers_social_login(): void
    {
        $where = config('omni_guides.sections.meta_where');
        $text = json_encode($where);
        foreach (['App ID', 'App Secret', 'Embedded Signup', 'Configuration ID', 'verify token', 'System users', 'WhatsApp Business Account ID', 'Phone number ID', 'API Keys'] as $needle) {
            $this->assertStringContainsStringIgnoringCase($needle, $text, $needle);
        }
        $social = json_encode(config('omni_guides.sections.social_login'), JSON_UNESCAPED_SLASHES);
        foreach (['Facebook', 'Google', '/auth/google/callback', '/auth/facebook/callback'] as $needle) {
            $this->assertStringContainsString($needle, $social, $needle);
        }
        $map = json_encode(config('omni_guides.sections.meta_map'), JSON_UNESCAPED_SLASHES);
        foreach (['Configurations', 'Settings', 'Unpublished', 'oauth.php', '/auth/facebook/callback', 'One app'] as $needle) {
            $this->assertStringContainsStringIgnoringCase($needle, $map, $needle);
        }
        Livewire::actingAs($this->admin())->test(OmniSetupGuide::class)->call('open', 'meta_map')->assertSee('Meta at a glance')
            ->call('open', 'social_login')->assertSee('/auth/google/callback', false);
    }
}
