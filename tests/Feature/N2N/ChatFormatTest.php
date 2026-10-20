<?php

namespace Tests\Feature\N2N;

use App\Support\N2N\ChatFormat;
use Tests\TestCase;

/** Naara chat message formatting (MASTER-ONLY): a closed dialect that can never inject markup. */
class ChatFormatTest extends TestCase
{
    private function html(string $s): string
    {
        return (string) ChatFormat::render($s);
    }

    public function test_a_plain_message_renders_exactly_as_escaped_text_with_its_line_breaks(): void
    {
        $this->assertSame('<span class="whitespace-pre-wrap">Hi there'."\n".'Frank &amp; Ada &lt;3</span>', $this->html("Hi there\nFrank & Ada <3"));
    }

    public function test_the_inline_markers_and_blocks_produce_only_the_fixed_tags(): void
    {
        $h = $this->html("**bold** _it_ ~gone~ `code`\n# Big\n> quoted\n- one\n1. first\n[x] done\n[ ] todo");
        foreach (['<strong>bold</strong>', '<em>it</em>', '<s>gone</s>', '<code class="ncf-ic">code</code>', 'ncf-h1', 'ncf-quote', 'ncf-li', 'ncf-task is-done', 'ncf-task"'] as $needle) {
            $this->assertStringContainsString($needle, $h, $needle);
        }
    }

    public function test_banners_use_a_closed_colour_list_and_fenced_code_is_never_interpreted(): void
    {
        $this->assertStringContainsString('ncf-banner--green', $this->html(":::green\nAll **good**\n:::"));
        $this->assertStringNotContainsString('ncf-banner', $this->html(":::evil\nx\n:::"));
        $code = $this->html("```\n**not bold** <b>x</b>\n```");
        $this->assertStringContainsString('<pre class="ncf-code"><code>**not bold** &lt;b&gt;x&lt;/b&gt;</code></pre>', $code);
    }

    public function test_nothing_the_sender_types_can_become_markup_a_link_or_an_attribute(): void
    {
        foreach (['<script>alert(1)</script>', '**<img src=x onerror=alert(1)>**', '> <a href="javascript:x">y</a>', '# <svg onload=x>', '- `<b>`', '[x](javascript:alert(1))', ':::red"><script>:::'] as $evil) {
            $h = $this->html($evil);
            $this->assertStringNotContainsString('<script', $h);
            $this->assertStringNotContainsString('<img', $h);
            $this->assertStringNotContainsString('<svg', $h);
            $this->assertStringNotContainsString('<a ', $h);
            $this->assertStringNotContainsString('onerror=', strip_tags($h) === $h ? $h : preg_replace('/&lt;.*?&gt;/', '', $h));
        }
    }

    public function test_unfinished_markers_stay_literal_and_snake_case_is_not_italic(): void
    {
        $this->assertStringNotContainsString('<em>', $this->html('my_file_name and 2*3*4 and ~5'));
        $this->assertStringNotContainsString('<strong>', $this->html('**unfinished'));
    }

    public function test_plain_strips_the_markers_for_one_line_previews(): void
    {
        $this->assertSame('Big news bold done', ChatFormat::plain("# Big news\n**bold**\n[x] done"));
        $this->assertSame('hello world', ChatFormat::plain('hello world'));
    }
}
