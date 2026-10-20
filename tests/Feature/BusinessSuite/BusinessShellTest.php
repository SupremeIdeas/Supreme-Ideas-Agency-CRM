<?php

namespace Tests\Feature\BusinessSuite;

use App\Livewire\Messages;
use App\Models\MessageThread;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletGroup;
use App\Models\WalletGroupMember;
use App\Services\Permissions\WorkspaceRoleService;
use App\Support\Business\BusinessNav;
use App\Support\Permissions\BusinessRole;
use App\Support\Workspace;
use Database\Seeders\BusinessRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The Business shell (rail, workspace chip, bottom bar) and the omni-channel inbox in the Naara chat look. */
class BusinessShellTest extends TestCase
{
    use \Tests\Concerns\EnablesBusinessSuite;
    use RefreshDatabase;

    private User $owner;

    private WalletGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(BusinessRoleSeeder::class);
        foreach (['inbox', 'call_flows', 'recording', 'ai_voice'] as $m) {
            Setting::setValue("business.module.$m", true, 'business');
        }
        config(['services.twilio.account_sid' => 'AC_test', 'services.twilio.auth_token' => 'secret_token']);
        $this->owner = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $this->group = WalletGroup::create(['owner_user_id' => $this->owner->id, 'name' => 'Acme Ltd', 'kind' => WalletGroup::KIND_BUSINESS]);
        app(WorkspaceRoleService::class)->assignRole($this->group, $this->owner, BusinessRole::OWNER);
    }

    private function member(string $role): User
    {
        $u = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        WalletGroupMember::create(['wallet_group_id' => $this->group->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active', 'invited_at' => now(), 'accepted_at' => now()]);
        app(WorkspaceRoleService::class)->assignRole($this->group, $u, $role);

        return $u;
    }

    private function inWorkspace(User $u)
    {
        return $this->actingAs($u)->withSession(['active_wallet_group_id' => $this->group->id]);
    }

    public function test_workspace_pages_wear_the_business_shell(): void
    {
        foreach (['/business/workspace', '/business/call-flows', '/business/recordings', '/business/channels', '/business/assistant'] as $url) {
            $r = $this->inWorkspace($this->owner)->get($url); 
            $r->assertOk()->assertSee('nb__wschip', false)->assertSee('Acme Ltd')->assertSee('aria-label="Business navigation"', false);
        }
    }

    public function test_the_inbox_wears_the_shell_inside_a_workspace_and_the_numbers_look_outside_one(): void
    {
        $this->inWorkspace($this->owner)->get('/numbers/messages')->assertOk()->assertSee('data-bz-chat', false)->assertSee('nb__rail', false)->assertSee('Acme Ltd');
        $this->flushSession();
        $this->actingAs($this->owner)->get('/numbers/messages')->assertOk()->assertSee('data-bz-chat', false)->assertDontSee('nb__rail', false);
    }

    public function test_nav_hides_admin_only_destinations_from_agents_and_modules_that_are_off(): void
    {
        $keys = fn (User $u) => array_column(BusinessNav::items($u, $this->group), 'key');
        $this->assertEqualsCanonicalizing(['overview', 'inbox', 'calls', 'recordings', 'channels', 'assistant', 'guides'], $keys($this->owner));
        $this->assertEqualsCanonicalizing(['overview', 'inbox', 'calls', 'guides'], $keys($this->member(BusinessRole::AGENT)));

        Setting::setValue('business.module.recording', false, 'business');
        Setting::setValue('business.module.ai_voice', false, 'business');
        $this->assertEqualsCanonicalizing(['overview', 'inbox', 'calls', 'channels', 'guides'], $keys($this->owner));
    }

    public function test_the_bar_has_at_most_four_destinations_and_the_inbox_carries_the_unread_count(): void
    {
        MessageThread::create(['user_id' => $this->owner->id, 'channel' => 'whatsapp', 'wallet_group_id' => $this->group->id, 'counterpart_number' => '2348011112222', 'last_body' => 'hi', 'last_direction' => 'in', 'last_at' => now(), 'unread_count' => 3]);
        $items = BusinessNav::items($this->owner, $this->group);
        $this->assertLessThanOrEqual(4, count(BusinessNav::bar($items)));
        $this->assertSame(3, collect($items)->firstWhere('key', 'inbox')['badge']);
        $this->assertSame(0, BusinessNav::unread(null));
    }

    public function test_the_channel_strip_shows_per_channel_unread_and_channel_replies_use_the_naara_composer(): void
    {
        MessageThread::create(['user_id' => $this->owner->id, 'channel' => 'telegram', 'wallet_group_id' => $this->group->id, 'counterpart_number' => '5551', 'last_body' => 'hello', 'last_direction' => 'in', 'last_at' => now(), 'unread_count' => 2]);
        $this->inWorkspace($this->owner);
        Workspace::setActive($this->group);

        Livewire::actingAs($this->owner)->test(Messages::class)
            ->assertSee('Telegram')->assertSee('bz-chan__n', false)
            ->call('openThread', '5551', 'telegram')
            ->assertSee('naara-composer', false)->assertSee('data-ch="telegram"', false);
    }

    public function test_the_inbox_has_a_channel_slider_a_list_toggle_a_docked_composer_and_the_skin_mapped_send_button_css(): void
    {
        MessageThread::create(['user_id' => $this->owner->id, 'channel' => 'telegram', 'wallet_group_id' => $this->group->id, 'counterpart_number' => '5551', 'last_body' => 'hello', 'last_direction' => 'in', 'last_at' => now()]);

        Livewire::actingAs($this->owner)->test(Messages::class)->assertSee('bz-slide', false)->assertSee('Scroll channels forward', false)
            ->call('openThread', '5551', 'telegram')->assertSee('bz-listbtn', false)->assertSee('nc__foot', false)->assertSee('naara-composer', false);

        $css = file_get_contents(resource_path('css/nx-business.css'));
        $this->assertStringContainsString('.bz-msg .cc-send {', $css);
        $this->assertDoesNotMatchRegularExpression('/\.bz-msg \.cc-send[^{]*\{[^}]*background:\s*var\(--cc-border\)/', $css, 'the send button must never be the faded grey disc');
    }

    public function test_each_channel_header_wears_its_own_brand_aurora_and_respects_reduced_motion(): void
    {
        $css = file_get_contents(resource_path('css/nx-business.css'));
        foreach (['telegram', 'whatsapp', 'instagram'] as $ch) {
            $this->assertStringContainsString('.bz-msg .nc__thread[data-ch="'.$ch.'"] { --au0:', $css, "$ch has its own aurora palette");
        }
        $this->assertStringContainsString('.nc__thead::before', $css);
        $this->assertStringContainsString('prefers-reduced-motion: reduce) { .bz-msg .nc__thread[data-ch] .nc__thead::before { animation: none; }', $css);

        // the thread carries the real channel, so the header palette follows the conversation and never another channel
        $thread = MessageThread::create(['user_id' => $this->owner->id, 'channel' => 'whatsapp', 'wallet_group_id' => $this->group->id, 'counterpart_number' => '2348012345678', 'last_body' => 'hi', 'last_direction' => 'in', 'last_at' => now()]);
        Livewire::actingAs($this->owner)->test(Messages::class)->call('openThread', $thread->counterpart_number, 'whatsapp')
            ->assertSeeHtml('nc__thread bz-msg__panel bz-msg__thread')->assertSeeHtml('data-ch="whatsapp"')->assertDontSeeHtml('<section class="nc__thread bz-msg__panel bz-msg__thread" :class="pane === \'list\' ? \'is-hidden\' : \'\'" aria-label="Conversation" data-ch="instagram"');
    }

    public function test_composer_buttons_can_set_their_own_colour(): void
    {
        $css = file_get_contents(resource_path('css/composer.css'));
        $this->assertStringContainsString('.cc :where(button) {', $css, 'the base button rule must stay zero-specificity or it overrides .cc-gi / .cc-gs-play icon colours');
        $this->assertDoesNotMatchRegularExpression('/^\.cc button \{/m', $css);
    }

    public function test_compose_deep_link_opens_the_new_message_composer(): void
    {
        Livewire::actingAs($this->owner)->withQueryParams(['compose' => 1])->test(Messages::class)->assertDispatched('open-send-message');
    }
}
