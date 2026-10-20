<?php

namespace Tests\Feature\N2N;

use App\Support\PreloaderSettings;
use Illuminate\Support\Facades\Blade;

/** Naara chat wayfinding (MASTER-ONLY): the preloader says "NaaraChat" (only there) and the conversation list has a floating invite button. */
class ChatWayfindingTest extends N2NTestCase
{
    private function enablePreloader(string $type): void
    {
        PreloaderSettings::saveAssignment($type, array_merge(PreloaderSettings::safeDefault(), ['preset' => 'equalizer', 'enabled' => true]));
    }

    public function test_the_naara_chat_preloader_names_the_app_and_no_other_preloader_does(): void
    {
        $this->enablePreloader('n2n');
        $this->enablePreloader('marketing');

        $chat = Blade::render('<x-brand-preloader page-type="n2n" />');
        $other = Blade::render('<x-brand-preloader page-type="marketing" />');

        $this->assertStringContainsString('nx-pre-appname', $chat);
        $this->assertStringContainsString('NaaraChat', $chat);
        $this->assertStringNotContainsString('NaaraChat', $other);
        $this->assertStringNotContainsString('nx-pre-appname', $other);
    }

    public function test_the_chat_shell_asks_for_the_n2n_preloader(): void
    {
        $this->assertStringContainsString("'preloaderType' => 'n2n'", (string) file_get_contents(resource_path('views/components/layouts/n2n.blade.php')));
    }

    public function test_the_conversation_list_has_a_floating_invite_with_the_members_own_link(): void
    {
        $u = $this->user(['email_verified_at' => now()]);
        $html = $this->actingAs($u)->get(route('n2n.chat'))->assertOk()->assertSee('nc__fab', false)->assertSee('Invite someone to Naara chat')->getContent();

        $this->assertStringContainsString(rawurlencode('?ref='.$u->referral_code), $html, 'the WhatsApp share carries the member\'s own referral link');
        $this->assertStringContainsString(route('n2n.people'), $html, 'and there is a way to search or invite by ID, phone or email');
    }

    public function test_the_invite_button_steps_aside_while_selecting_chats(): void
    {
        $this->assertStringContainsString('x-show="!on"', (string) file_get_contents(resource_path('views/livewire/n2n/chat.blade.php')));
    }
}
