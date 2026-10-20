<?php

use App\Support\NotificationTones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tones can carry a credit line (shown beside the name in Admin and in Account > Sounds), the library gains the "beep" category, and the built-in set gains the
     * three Zayon Whyte ringtones (NAARA Circus, 2026) and six beeps. Idempotent: seedBuiltin() only adds what is missing and never touches admin-added tones.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('notification_tones', 'credit')) {
            Schema::table('notification_tones', function (Blueprint $t) {
                $t->string('credit', 160)->nullable()->after('label');
            });
        }
        if (app()->runningUnitTests()) {
            return;
        }
        try {
            NotificationTones::seedBuiltin();
        } catch (\Throwable $e) {
            report($e);   // re-run later with: php artisan sounds:seed
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('notification_tones', 'credit')) {
            Schema::table('notification_tones', function (Blueprint $t) {
                $t->dropColumn('credit');
            });
        }
    }
};
