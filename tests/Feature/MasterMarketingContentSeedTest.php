<?php

namespace Tests\Feature;

use App\Models\FaqCategory;
use App\Models\FaqItem;
use App\Models\Post;
use Database\Seeders\MasterMarketingBlogSeeder;
use Database\Seeders\MasterMarketingFaqSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MASTER-ONLY extra blog posts and FAQ topics: complete, idempotent, readable, and visible on the public pages. */
class MasterMarketingContentSeedTest extends TestCase
{
    use RefreshDatabase;

    private function seedAll(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(MasterMarketingBlogSeeder::class);
        $this->seed(MasterMarketingFaqSeeder::class);
    }

    public function test_every_post_file_parses_and_is_a_real_article(): void
    {
        $files = glob(database_path('seeders/master_marketing_blog/*.md'));
        $this->assertGreaterThanOrEqual(16, count($files));
        foreach ($files as $f) {
            $p = MasterMarketingBlogSeeder::parse((string) file_get_contents($f), basename($f, '.md'));
            $this->assertNotNull($p, basename($f).' needs title, category, excerpt and published');
            $this->assertGreaterThan(250, str_word_count(strip_tags($p['body'])), basename($f).' is too short');
            $this->assertLessThanOrEqual(230, mb_strlen($p["excerpt"]), 'excerpt reads as a summary, not an essay');
            $this->assertSame(0, preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $p['title'].$p['body']), basename($f).' must not contain emoji');
            $this->assertStringNotContainsString('TODO', $p['body']);
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $p['slug']);
        }
    }

    public function test_seeding_is_idempotent_and_published(): void
    {
        $this->seedAll();
        $first = Post::count();
        $this->seed(MasterMarketingBlogSeeder::class);
        $this->assertSame($first, Post::count());
        $this->assertSame(0, Post::where('status', '!=', 'published')->count());
        $this->assertSame(count(glob(database_path('seeders/master_marketing_blog/*.md'))), Post::count());
        $faqItems = FaqItem::count();
        $this->seed(MasterMarketingFaqSeeder::class);
        $this->assertSame($faqItems, FaqItem::count());
    }

    public function test_the_faq_has_the_new_topics_with_real_answers(): void
    {
        $this->seedAll();
        $this->assertSame(7, FaqCategory::count());
        $this->assertGreaterThanOrEqual(55, FaqItem::count());
        foreach (FaqItem::all() as $i) {
            $this->assertGreaterThan(30, mb_strlen(strip_tags($i->answer)), $i->question.' needs a real answer');
            $this->assertStringEndsWith('?', $i->question);
        }
        $this->assertTrue(FaqItem::where('question', 'like', '%Connect and message%')->exists());
    }

    public function test_new_posts_show_on_the_blog_and_an_article_opens(): void
    {
        $this->seedAll();
        $this->get('/blog')->assertOk()->assertSee('Naara Chat');
        $post = Post::where('slug', 'naara-respond-one-inbox-for-every-channel')->firstOrFail();
        $this->get('/blog/'.$post->slug)->assertOk()->assertSee('Naara Respond');
    }

    public function test_the_cover_is_attached_only_when_the_image_exists(): void
    {
        $this->seedAll();
        $covers = 0;
        foreach (Post::all() as $p) {
            if ($p->getRawOriginal('cover_image_url')) {
                $covers++;
                $this->assertFileExists(public_path(ltrim((string) $p->getRawOriginal('cover_image_url'), '/')));
            }
        }
        $this->assertGreaterThanOrEqual(14, $covers, 'almost every new post has a cover photo');
    }
}
