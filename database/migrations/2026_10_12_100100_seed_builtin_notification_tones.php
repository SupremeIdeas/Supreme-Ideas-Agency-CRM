<?php

use App\Support\NotificationTones;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Puts the built-in library (original synthesis, scripts/gen_tones.py) in place. Idempotent; never touches admin-added tones. */
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;   // tests build their own library; never write sample files to a disk from a test migration
        }
        try {
            NotificationTones::seedBuiltin();
        } catch (\Throwable $e) {
            // The media disk may not be reachable at deploy time (e.g. Wasabi keys not set yet). Re-run: php artisan sounds:seed
            report($e);
        }
    }

    public function down(): void
    {
        // Tones are content; a rollback of the schema drops the table in the previous migration.
    }
};
