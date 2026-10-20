<?php

namespace Tests\Feature;

use Tests\TestCase;

class NumbersBleedTest extends TestCase
{
    public function test_numbers_pages_flag_the_shell_and_messages_run_edge_to_edge_on_phones(): void
    {
        $this->assertStringContainsString("routeIs('numbers.*')) data-nx-bleed", file_get_contents(resource_path('views/components/app-shell.blade.php')));
        $css = file_get_contents(resource_path('css/nx-layout.css'));
        $this->assertMatchesRegularExpression('/main\[data-nx-bleed\] \.bz-msg--page \{ margin-inline: -16px;/', $css);
    }

    public function test_business_suite_is_in_the_more_menu_once_the_admin_switches_it_on(): void
    {
        $src = file_get_contents(app_path('Support/BottomNav.php'));
        $this->assertStringContainsString("'label' => 'Business Suite'", $src);
        $this->assertStringContainsString('BusinessNav::entryRoute(', $src);
        $this->assertStringContainsString("ComingSoon::isLive('business_suite')", $src);
    }

    public function test_business_pages_use_the_compact_phone_spacing_scale(): void
    {
        $css = file_get_contents(resource_path('css/nx-business.css'));
        $this->assertStringContainsString('.nb__main .mb-6 { margin-bottom: 12px; }', $css);
        $this->assertStringContainsString('.bz-stat b { font-size: 22px; }', $css);
    }

    public function test_workspace_stat_tiles_are_aligned_flex_rows_and_the_view_is_balanced(): void
    {
        $css = file_get_contents(resource_path('css/nx-business.css'));
        $this->assertStringContainsString('.bz-stat { display: flex; align-items: center; justify-content: space-between;', $css);
        $v = file_get_contents(resource_path('views/livewire/business.blade.php'));
        $this->assertSame(substr_count($v, '<div'), substr_count($v, '</div>'), 'unbalanced <div> in business.blade.php breaks the layout shell');
    }

    public function test_business_canvas_is_clean_not_grainy(): void
    {
        $css = file_get_contents(resource_path('css/nx-business.css'));
        $this->assertStringContainsString('.nn__main.nb__main:not(:has([data-n2n-chat]))', $css);
        $block = substr($css, strpos($css, '.nn__main.nb__main:not'));
        $this->assertStringNotContainsString('feTurbulence', $block);
    }
}
