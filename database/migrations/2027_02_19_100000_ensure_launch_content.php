<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * MASTER-ONLY. A fresh install already gets every article and FAQ from the installer's db:seed. An install that was set up earlier only needs `php artisan migrate` on deploy
 * to receive the rest: this adds the launch articles and FAQ topics that are MISSING, by slug and question, and never overwrites or removes anything an admin edited or
 * deleted-and-replaced. Skipped on a database with no admin yet (the installer's own db:seed runs right after). A failure here can never break the migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            if (! Schema::hasTable('posts') || ! Schema::hasTable('faq_categories') || ! Schema::hasTable('faq_items') || ! Schema::hasTable('users')) {
                return;
            }
            if (\App\Models\User::query()->count() === 0) {
                return;   // brand-new database: the installer seeds everything with db:seed
            }
            config(['seed.only_missing' => true]);
            foreach ([\Database\Seeders\BlogPostSeeder::class, \Database\Seeders\FaqSeeder::class, \Database\Seeders\MasterMarketingBlogSeeder::class, \Database\Seeders\MasterMarketingFaqSeeder::class] as $seeder) {
                if (class_exists($seeder)) {
                    app($seeder)->run();
                }
            }
        } catch (\Throwable $e) {
            report($e);
        } finally {
            config(['seed.only_missing' => false]);
        }
    }

    public function down(): void
    {
        // content is never removed on rollback
    }
};
