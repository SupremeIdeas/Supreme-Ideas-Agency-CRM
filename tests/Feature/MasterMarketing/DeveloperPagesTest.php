<?php

namespace Tests\Feature\MasterMarketing;

use App\Support\MasterMarketing\DeveloperCodeHighlighter;
use App\Support\MasterMarketing\DeveloperDocs;
use App\Support\MasterMarketing\DeveloperSamples;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The master-only developer marketing page, grouped docs, highlighter and samples. */
class DeveloperPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_landing_page_renders_with_its_mockups_and_links_to_the_docs(): void
    {
        config(['app.url' => 'https://naarasim.example']);

        $this->get('/developers')->assertOk()
            ->assertSee('Sell connectivity from your own app', false)
            ->assertSee('Terminal: placing an eSIM order', false)
            ->assertSee('Browser window showing the developer area', false)
            ->assertSee('Browser window showing widget analytics', false)
            ->assertSee('A phone showing a merchant shop', false)
            ->assertSee(route('developers.docs', 'quick-start'), false)
            ->assertSee('naarasim.example', false)
            ->assertDontSee('YOUR-DOMAIN', false);
    }

    public function test_every_documentation_page_renders_and_the_sidebar_lists_them_all(): void
    {
        foreach (DeveloperDocs::pages() as $p) {
            $res = $this->get(route('developers.docs', $p['slug']))->assertOk()->assertSee($p['title'], false)->assertDontSee('YOUR-DOMAIN', false);
            foreach (DeveloperDocs::pages() as $other) {
                $res->assertSee(route('developers.docs', $other['slug']), false);
            }
        }
        $this->get('/developers/docs')->assertOk()->assertSee('Introduction', false);
        $this->get('/developers/docs/does-not-exist')->assertNotFound();
    }

    public function test_an_endpoint_page_shows_its_method_path_scope_and_four_languages(): void
    {
        $this->get(route('developers.docs', 'create-order'))->assertOk()
            ->assertSee('data-dv-endpoint', false)->assertSee('/api/v1/orders', false)->assertSee('Scope:', false)
            ->assertSee('cURL', false)->assertSee('PHP', false)->assertSee('Node.js', false)->assertSee('Python', false)
            ->assertSee('dv-t-key', false);   // the JSON response is highlighted
    }

    public function test_the_docs_cover_every_endpoint_scope_and_status_in_the_canonical_reference(): void
    {
        $all = json_encode(DeveloperDocs::pages());
        foreach (['/catalogue', '/quote', '/orders', '/orders/{reference}', 'catalogue', 'quote', 'order', 'status', 'has_voice', 'duplicate_in_progress', 'insufficient_balance', 'not_found', 'unavailable', '300 requests per minute'] as $needle) {
            $this->assertStringContainsString(str_replace('/', '\\/', $needle), $all, "docs lack {$needle}");
        }
        $md = (string) file_get_contents(base_path('docs/DEVELOPER-API.md'));
        foreach (['402', '409', '429', '502'] as $status) {
            $this->assertStringContainsString($status, $md);
            $this->assertStringContainsString($status, $all);
        }
    }

    public function test_the_single_page_reference_is_still_reachable(): void
    {
        $this->get(route('developers.reference'))->assertOk()->assertSee('Build on NaaraSim', false);
    }

    public function test_highlighter_colours_tokens_and_never_lets_markup_through(): void
    {
        $json = DeveloperCodeHighlighter::highlight('{ "a": "<script>alert(1)</script>", "n": 1.5, "t": true }', 'json');
        $this->assertStringContainsString('<span class="dv-t-key">&quot;a&quot;</span>', $json);
        $this->assertStringContainsString('<span class="dv-t-num">1.5</span>', $json);
        $this->assertStringContainsString('<span class="dv-t-kw">true</span>', $json);
        $this->assertStringNotContainsString('<script>', $json);

        $sh = DeveloperCodeHighlighter::highlight("curl -X POST https://x.test/a \\\n  -H \"A: \$K\" # note", 'bash');
        $this->assertStringContainsString('dv-t-flag', $sh);
        $this->assertStringContainsString('dv-t-url', $sh);
        $this->assertStringContainsString('dv-t-com', $sh);

        $this->assertSame('plain &amp; text', DeveloperCodeHighlighter::highlight('plain & text', 'unknown'));
    }

    public function test_samples_agree_across_languages(): void
    {
        $s = DeveloperSamples::request('POST', '/orders', ['type' => 'esim', 'plan_id' => 12, 'reference' => 'r1']);
        $this->assertSame(['curl', 'php', 'node', 'python'], array_keys($s));
        foreach ($s as $lang => $code) {
            $this->assertStringContainsString($lang === 'php' ? "post('orders'" : '/orders', $code);   // Guzzle posts relative to its base_uri
            $this->assertStringContainsString('NAARA_KEY', $code);
            $this->assertStringContainsString('r1', $code);
        }
        $this->assertStringContainsString('"plan_id":12', $s['curl']);
        $this->assertStringContainsString("'plan_id' => 12", $s['php']);
        $this->assertStringContainsString('plan_id: 12', $s['node']);
        $this->assertStringContainsString('"plan_id": 12', $s['python']);
        $this->assertStringNotContainsString('-d', DeveloperSamples::request('GET', '/catalogue')['curl']);
    }

    public function test_inline_formatting_escapes_everything_first(): void
    {
        $this->assertSame('<code>a</code> and <strong>b</strong> &lt;i&gt;', DeveloperDocs::inline('`a` and **b** <i>'));
    }
}
