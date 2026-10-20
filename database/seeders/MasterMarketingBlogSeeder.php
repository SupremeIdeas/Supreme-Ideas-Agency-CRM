<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * MASTER-ONLY extra blog posts (2026-10-19): articles about what the platform gained since the 40 launch posts (Naara Chat, Brands, Naara Pro, Business Suite,
 * Respond, handbooks, White Label, widgets, developer API, calls, rewards, wallet). Each post is a Markdown file in `database/seeders/master_marketing_blog/`
 * with a small front-matter block (title, category, excerpt, published). Idempotent on slug. A cover is attached when `public/images/blog/{slug}.webp` exists;
 * photo sources and licences are in docs/BLOG-PHOTO-CREDITS.md. Lives under a "MasterMarketing" name so the white-label boundary guards keep it out of forks.
 */
class MasterMarketingBlogSeeder extends Seeder
{
    public function run(): void
    {
        // An upgrade passes seed.only_missing so admin edits to existing rows are never overwritten; a plain db:seed still refreshes everything.
        $op = config('seed.only_missing') ? 'firstOrCreate' : 'updateOrCreate';
        $authorId = User::where('email', 'supremeideasz@gmail.com')->value('id') ?? User::role('super_admin')->value('id');
        foreach (glob(__DIR__.'/master_marketing_blog/*.md') ?: [] as $file) {
            $post = self::parse((string) file_get_contents($file), basename($file, '.md'));
            if ($post === null) {
                continue;
            }
            if (is_file(public_path("images/blog/{$post['slug']}.webp"))) {
                $post['cover_image_url'] = "/images/blog/{$post['slug']}.webp";
            }
            Post::{$op}(['slug' => $post['slug']], $post + ['author_id' => $authorId, 'status' => 'published']);
        }
    }

    /** @return array<string,mixed>|null */
    public static function parse(string $raw, string $slug): ?array
    {
        if (! preg_match('/\A---\R(.*?)\R---\R(.*)\z/s', $raw, $m)) {
            return null;
        }
        $meta = [];
        foreach (preg_split('/\R/', trim($m[1])) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $meta[trim($k)] = trim($v);
            }
        }
        foreach (['title', 'category', 'excerpt', 'published'] as $required) {
            if (($meta[$required] ?? '') === '') {
                return null;
            }
        }
        $body = trim($m[2]);
        $excerpt = $meta['excerpt'];

        return [
            'title' => $meta['title'], 'slug' => $slug, 'category' => $meta['category'], 'excerpt' => $excerpt, 'body' => $body,
            'meta_title' => mb_substr($meta['title'], 0, 70), 'meta_description' => mb_substr($excerpt, 0, 160), 'published_at' => $meta['published'],
        ];
    }
}
