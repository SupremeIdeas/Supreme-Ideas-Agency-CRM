<?php

namespace Tests\Feature\N2N;

use Tests\TestCase;

class GlobalCallLayerTest extends TestCase
{
    public function test_the_call_layer_is_mounted_once_in_the_app_layout_and_persisted(): void
    {
        $app = file_get_contents(resource_path('views/components/layouts/app.blade.php'));
        $this->assertStringContainsString("@persist('n2n-call-layer')", $app);
        $this->assertStringContainsString('global-calls.js', $app);
        $this->assertStringContainsString('N2NSettings::enabled()', $app);
        $n2n = file_get_contents(resource_path('views/components/layouts/n2n.blade.php'));
        $this->assertStringNotContainsString("include('n2n.call-overlay')", $n2n, 'a second overlay would duplicate #n2n-call');
        $this->assertStringContainsString('global-calls.js', file_get_contents(base_path('vite.config.js')));
    }
}
