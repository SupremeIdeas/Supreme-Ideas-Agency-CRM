<?php

namespace Tests\Feature\N2N;

use Tests\TestCase;

class ChatLightboxAssetsTest extends TestCase
{
    public function test_lightbox_is_loaded_by_chat_and_wired_in_the_thread(): void
    {
        $this->assertStringContainsString("import './lightbox.js'", file_get_contents(base_path('resources/js/n2n/chat.js')));
        $view = file_get_contents(base_path('resources/views/livewire/n2n/chat.blade.php'));
        $this->assertStringContainsString('data-nlb-scope', $view);
        $this->assertStringContainsString('nimgs--', $view);
        $js = file_get_contents(base_path('resources/js/n2n/lightbox.js'));
        foreach (['Escape', 'ArrowRight', 'aria-modal', 'pointerup', 'wheel', 'scale('] as $needle) {
            $this->assertStringContainsString($needle, $js);
        }
    }
}
