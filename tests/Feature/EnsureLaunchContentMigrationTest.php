<?php

namespace Tests\Feature;

use App\Models\FaqCategory;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MASTER-ONLY. `php artisan migrate` alone brings an already-installed site the articles and FAQs it is missing, without touching anything an admin changed. */
class EnsureLaunchContentMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function run_migration(): void
    {
        (require base_path('database/migrations/2027_02_19_100000_ensure_launch_content.php'))->up();
    }

    public function test_a_brand_new_database_is_left_for_the_installers_own_seed(): void
    {
        $this->run_migration();
        $this->assertSame(0, Post::count());
    }

    public function test_an_existing_install_gets_what_is_missing_and_keeps_every_edit(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        User::factory()->create()->assignRole('super_admin');
        $this->seed(\Database\Seeders\BlogPostSeeder::class);
        $old = Post::orderBy('id')->first();
        $old->update(['title' => 'My own edited title']);
        $this->assertSame(40, Post::count(), 'an older install has the original 40');

        $this->run_migration();

        $this->assertGreaterThanOrEqual(56, Post::count(), 'the master articles arrive');
        $this->assertSame('My own edited title', $old->fresh()->title, 'an admin edit is never overwritten');
        $this->assertSame(0, Post::where('status', '!=', 'published')->count());
        $this->assertGreaterThanOrEqual(12, FaqCategory::count());

        // running it again changes nothing
        $count = Post::count();
        $this->run_migration();
        $this->assertSame($count, Post::count());
        $this->assertFalse((bool) config('seed.only_missing'), 'the switch is always put back');
    }

    public function test_a_plain_db_seed_still_refreshes_content(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        User::factory()->create()->assignRole('super_admin');
        $this->seed(\Database\Seeders\BlogPostSeeder::class);
        $p = Post::orderBy('id')->first();
        $p->update(['title' => 'edited']);
        $this->seed(\Database\Seeders\BlogPostSeeder::class);
        $this->assertNotSame('edited', $p->fresh()->title, 'seeding on purpose still refreshes');
    }
}
