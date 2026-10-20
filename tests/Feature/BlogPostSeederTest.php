<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Database\Seeders\BlogPostSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Owner-supplied launch blog series (2026-09-21) — 40 posts across 16
 * categories. Locks in the seeder's contract so a future re-run or edit
 * can't silently drop a post, break a slug, or leave one unpublished.
 */
class BlogPostSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        User::factory()->create(['email' => 'supremeideasz@gmail.com'])->assignRole('super_admin');
    }

    public function test_it_seeds_exactly_forty_published_posts_with_unique_slugs(): void
    {
        $this->seed(BlogPostSeeder::class);

        $this->assertSame(40, Post::count());
        $this->assertSame(40, Post::where('status', 'published')->count());
        $this->assertSame(40, Post::distinct('slug')->count('slug'));
        $this->assertSame(0, Post::whereNull('published_at')->count());
        $this->assertTrue(Post::published()->count() === 40, 'every seeded post must pass the public scopePublished() filter');
    }

    public function test_every_post_has_the_fields_the_public_pages_render(): void
    {
        $this->seed(BlogPostSeeder::class);

        Post::all()->each(function (Post $post) {
            $this->assertNotEmpty($post->title);
            $this->assertNotEmpty($post->category);
            $this->assertNotEmpty($post->excerpt);
            $this->assertNotEmpty($post->body);
            $this->assertNotEmpty($post->meta_title);
            $this->assertNotEmpty($post->meta_description);
            $this->assertNotNull($post->author_id);
        });
    }

    public function test_the_body_renderer_never_receives_unconverted_markdown_bold(): void
    {
        $this->seed(BlogPostSeeder::class);

        // x-prose has no inline-bold support — a stray "**" would render as a
        // literal asterisk pair on the public page, which is exactly the bug
        // the batch→body converter exists to prevent.
        Post::all()->each(function (Post $post) {
            $this->assertStringNotContainsString('**', $post->body, "post '{$post->slug}' still has raw markdown bold in its body");
        });
    }

    public function test_re_seeding_is_idempotent_and_never_duplicates(): void
    {
        $this->seed(BlogPostSeeder::class);
        $this->seed(BlogPostSeeder::class);

        $this->assertSame(40, Post::count());
    }

    public function test_the_public_blog_index_renders_seeded_posts(): void
    {
        $this->seed(BlogPostSeeder::class);

        // The index orders newest-first, 9/page — assert on the most recent
        // seeded post so this doesn't depend on pagination depth.
        $this->get('/blog')
            ->assertOk()
            ->assertSee("How NaaraSim's Failover System Actually Keeps You Connected When One Provider Fails");
    }

    public function test_a_seeded_post_detail_page_renders_its_heading_and_body(): void
    {
        $this->seed(BlogPostSeeder::class);

        $post = Post::where('slug', 'esim-vs-physical-sim-what-nobody-tells-you')->first();

        $this->get(route('blog.show', $post))
            ->assertOk()
            ->assertSee($post->title)
            ->assertSee('What an eSIM actually is')
            ->assertDontSee('**');
    }

    // ── Cover images (2026-10-02): every post ships its own image ──────────

    public function test_every_post_has_a_committed_cover_image_that_exists_on_disk(): void
    {
        $this->seed(BlogPostSeeder::class);

        $this->assertSame(0, Post::whereNull('cover_image_url')->count());

        Post::all()->each(function (Post $post) {
            $path = public_path('images/blog/'.$post->slug.'.webp');
            $this->assertFileExists($path, "cover missing for {$post->slug}");
            // Served as an absolute URL even though the seed stores a host-independent path.
            $this->assertStringStartsWith('http', $post->cover_image_url);
            $this->assertStringEndsWith('/images/blog/'.$post->slug.'.webp', $post->cover_image_url);
        });
    }

    public function test_the_six_posts_with_a_second_image_show_it_inline_once(): void
    {
        $this->seed(BlogPostSeeder::class);

        $withSecond = collect(glob(public_path('images/blog/*-2.webp')))->map(fn ($f) => basename($f, '-2.webp'));
        $this->assertCount(6, $withSecond, 'all six second variants must be shipped');

        foreach ($withSecond as $slug) {
            $this->assertSame(1, substr_count(Post::where('slug', $slug)->value('body'), "/images/blog/{$slug}-2.webp"));
        }

        // Re-seeding must not stack a second copy.
        $this->seed(BlogPostSeeder::class);
        foreach ($withSecond as $slug) {
            $this->assertSame(1, substr_count(Post::where('slug', $slug)->value('body'), "/images/blog/{$slug}-2.webp"));
        }
    }

    public function test_a_post_page_renders_the_cover_and_the_inline_image(): void
    {
        $this->seed(BlogPostSeeder::class);

        $html = $this->get('/blog/naarasim-vs-airalo-africa-coverage-comparison')->assertOk()->getContent();

        $this->assertStringContainsString('/images/blog/naarasim-vs-airalo-africa-coverage-comparison.webp', $html);
        $this->assertStringContainsString('/images/blog/naarasim-vs-airalo-africa-coverage-comparison-2.webp', $html);
    }

    public function test_the_prose_renderer_only_accepts_local_or_https_images(): void
    {
        $html = \Illuminate\Support\Facades\Blade::render(
            '<x-prose :body="$b" />',
            ['b' => "![ok](/images/blog/x.webp)\n\n![evil](javascript:alert(1))\n\n![http](http://insecure.test/a.png)\n\n![fine](https://cdn.test/a.png)"],
        );

        $this->assertStringContainsString('/images/blog/x.webp', $html);
        $this->assertStringContainsString('https://cdn.test/a.png', $html);
        $this->assertStringNotContainsString('src="javascript:', $html);
        $this->assertStringNotContainsString('src="http://insecure.test', $html);
    }
}
