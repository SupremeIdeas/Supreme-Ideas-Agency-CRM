<?php

namespace Tests\Feature\BusinessSuite;

use App\Livewire\Business;
use App\Livewire\BusinessSetupWizard;
use App\Models\ChannelConnection;
use App\Models\User;
use App\Models\WalletGroup;
use App\Support\Business\SetupWizard;
use App\Support\Business\BusinessSettings;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** NaaraBusiness Suite Setup Wizard (master only): resumable steps, live status, plan gating, secrets write-only. */
class BusinessSetupWizardTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): array
    {
        (new \Database\Seeders\BusinessRoleSeeder)->run();
        $u = User::factory()->create(['is_active' => true]);
        $g = app(\App\Services\Wallet\WalletGroupService::class)->findOrCreateOwnGroup($u, WalletGroup::KIND_BUSINESS);
        $g->forceFill(['name' => 'Acme Ltd'])->save();
        app(\App\Services\Permissions\WorkspaceRoleService::class)->assignRole($g, $u, \App\Support\Permissions\BusinessRole::OWNER);
        \App\Models\Setting::setValue(\App\Support\Business\BusinessSuiteSettings::FLAG, true);
        foreach (['inbox', 'call_flows', 'recording', 'ai_voice', 'integrations'] as $m) {
            \App\Models\Setting::setValue('business.module.'.$m, true, 'business');
        }
        \App\Models\Setting::setValue(\App\Support\Business\BusinessSettings::ACCESS.'model', 'open', 'business');
        $this->actingAs($u);
        Workspace::setActive($g);

        return [$u, $g];
    }

    public function test_steps_report_live_status_and_percent_from_what_is_really_saved(): void
    {
        [$u, $g] = $this->workspace();
        $w = app(SetupWizard::class);

        $steps = collect($w->steps($g, $u))->keyBy('key');
        $this->assertSame(SetupWizard::ATTENTION, $steps['profile']['status'], 'a name but no business email');
        $this->assertSame(SetupWizard::TODO, $steps['channels']['status']);

        $g->forceFill(['business_email' => 'owner@acme.example'])->save();
        $this->assertSame(SetupWizard::DONE, collect($w->steps($g->fresh(), $u))->keyBy('key')['profile']['status']);
        $this->assertGreaterThan(0, $w->percent($w->steps($g->fresh(), $u)));
    }

    public function test_saving_the_profile_persists_immediately_and_moves_on(): void
    {
        [$u, $g] = $this->workspace();

        Livewire::test(BusinessSetupWizard::class)
            ->call('launch')
            ->assertSet('open', true)
            ->set('name', 'Acme Trading')->set('businessEmail', 'hello@acme.example')
            ->call('saveProfile')
            ->assertHasNoErrors()
            ->assertSet('step', 'channels');

        $g->refresh();
        $this->assertSame('Acme Trading', $g->name);
        $this->assertSame('hello@acme.example', $g->business_email);
    }

    public function test_a_telegram_token_is_tested_connected_and_wiped_from_the_page(): void
    {
        [$u, $g] = $this->workspace();
        Http::fake([
            'api.telegram.org/*/getMe' => Http::response(['ok' => true, 'result' => ['id' => 777001, 'username' => 'acme_bot', 'first_name' => 'Acme']]),
            'api.telegram.org/*/setWebhook' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $c = Livewire::test(BusinessSetupWizard::class)->call('launch')->call('goTo', 'channels')
            ->set('telegramToken', '123456:SECRET-TOKEN')->call('connectTelegram');

        $c->assertSet('telegramToken', '')->assertSet('error', null);
        $this->assertStringContainsString('Connected', (string) $c->get('ok'));
        $this->assertTrue(ChannelConnection::where('wallet_group_id', $g->id)->where('channel', 'telegram')->where('status', 'active')->exists());
        $this->assertStringNotContainsString('SECRET-TOKEN', $c->html(), 'a secret is never shown again');
    }

    public function test_a_rejected_token_says_so_in_plain_words_and_connects_nothing(): void
    {
        [$u, $g] = $this->workspace();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Unauthorized'], 401)]);

        Livewire::test(BusinessSetupWizard::class)->call('launch')->call('goTo', 'channels')
            ->set('telegramToken', 'bad')->call('connectTelegram')
            ->assertSet('error', 'Telegram did not accept that bot token. Copy it again from BotFather.');

        $this->assertSame(0, ChannelConnection::where('wallet_group_id', $g->id)->count());
    }

    public function test_a_step_the_plan_does_not_include_is_locked_with_an_explanation(): void
    {
        [$u, $g] = $this->workspace();
        \App\Models\Setting::setValue(\App\Support\Business\BusinessSettings::ACCESS.'model', 'pro', 'business');   // plan-gated; this owner has no plan

        $steps = collect(app(SetupWizard::class)->steps($g, $u))->keyBy('key');
        $this->assertSame(SetupWizard::LOCKED, $steps['ai_voice']['status']);

        Livewire::test(BusinessSetupWizard::class)->call('launch')->call('goTo', 'ai_voice')->assertSee('not part of your current Naara Pro plan');
    }

    public function test_the_overview_shows_the_finish_setup_card_and_the_wizard_resumes_at_the_first_open_step(): void
    {
        [$u, $g] = $this->workspace();

        Livewire::test(Business::class)->assertSee('Finish setup')->assertSee('data-setup-card', false);

        // profile still needs attention (no business email), so the wizard resumes there
        Livewire::test(BusinessSetupWizard::class)->call('launch')->assertSet('step', 'profile');

        $g->forceFill(['business_email' => 'owner@acme.example'])->save();
        Livewire::test(BusinessSetupWizard::class)->call('launch')->assertSet('step', 'channels');
    }

    public function test_the_self_test_reports_pass_or_fail_for_each_connected_channel(): void
    {
        [$u, $g] = $this->workspace();
        Http::fake([
            'api.telegram.org/*/getMe' => Http::response(['ok' => true, 'result' => ['id' => 777002, 'username' => 'acme2_bot', 'first_name' => 'A']]),
            'api.telegram.org/*/setWebhook' => Http::response(['ok' => true, 'result' => true]),
        ]);
        Livewire::test(BusinessSetupWizard::class)->call('launch')->set('telegramToken', '1:abc')->call('connectTelegram')
            ->call('goTo', 'done')->call('runSelfTest')
            ->assertSee('Telegram')->assertSee('Telegram answered.');
    }

    public function test_someone_without_a_workspace_cannot_open_it(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]));
        Livewire::test(BusinessSetupWizard::class)->call('launch')->assertStatus(404);
    }

    public function test_the_respond_step_only_exists_while_respond_is_on_and_tracks_real_progress(): void
    {
        [$u, $g] = $this->workspace();
        $w = app(SetupWizard::class);
        $this->assertNotContains('respond', array_column($w->steps($g, $u), 'key'), 'hidden while the omni switch is off');
        \App\Models\Setting::setValue('business.module.omni', true, 'business');
        $steps = collect($w->steps($g, $u))->keyBy('key');
        $this->assertSame(SetupWizard::TODO, $steps['respond']['status']);
        $this->assertTrue($steps['respond']['optional']);
        $this->assertStringContainsString('/business/respond/automation', (string) $steps['respond']['page']);
        app(\App\Services\Omni\Kb\KnowledgeBase::class)->addText($g->id, 'faq', 'Hours', 'We open at nine.');
        $this->assertStringContainsString('knowledge is ready', collect($w->steps($g, $u))->keyBy('key')['respond']['detail']);
        \App\Models\Omni\OmniAgentSetting::create(['workspace_id' => $g->id, 'mode' => 'suggest']);
        $this->assertSame(SetupWizard::DONE, collect($w->steps($g, $u))->keyBy('key')['respond']['status']);
    }
}
