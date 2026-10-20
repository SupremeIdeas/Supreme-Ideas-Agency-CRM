<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** Empty states and voice notes carry the drawn art and the full player (master only). */
class EmptyStateArtTest extends TestCase
{
    public function test_every_empty_state_illustration_exists(): void
    {
        foreach (['wallet', 'receipts', 'bell', 'referral', 'bank', 'invoice', 'clients', 'help', 'blog', 'book', 'rewards', 'journey', 'brands'] as $k) {
            $this->assertFileExists(public_path("images/illustrations/es-$k.svg"));
        }
    }

    public function test_the_empty_component_shows_art_only_when_the_file_exists(): void
    {
        $with = Blade::render('<x-nx.empty art="es-wallet" title="T" text="x" />');
        $this->assertStringContainsString('es-wallet.svg', $with);
        $this->assertStringContainsString('data-empty-art', $with);
        $this->assertStringNotContainsString('data-empty-art', Blade::render('<x-nx.empty art="no-such-art" title="T" />'));
        $this->assertStringNotContainsString('data-empty-art', Blade::render('<x-nx.empty title="T" />'));
    }

    public function test_the_voice_note_component_renders_the_waveform_player(): void
    {
        $html = Blade::render('<x-voice-note src="/support/voice/1" id="sv1" :mine="true" />');
        $this->assertStringContainsString('data-voice-note', $html);
        $this->assertStringContainsString('nvp__wave', $html);
        $this->assertStringNotContainsString('<audio', $html);
    }
}
