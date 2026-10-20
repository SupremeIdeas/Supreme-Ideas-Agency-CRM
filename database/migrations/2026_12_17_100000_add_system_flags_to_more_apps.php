<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('more_apps', function (Blueprint $t) {
            $t->boolean('is_system')->default(false)->after('is_active');   // built-in apps: editable name/logo, never deletable
            $t->string('system_key', 40)->nullable()->unique()->after('is_system');
            $t->string('route_name', 80)->nullable()->after('system_key');  // in-app named route (SPA navigate) instead of a stored URL
        });

        // Master only: the built-in rows exist only where the module classes do (a white-label fork never gets them).
        if (class_exists(\App\Support\Business\SystemMoreApps::class)) {
            \App\Support\Business\SystemMoreApps::ensure();
        }
    }

    public function down(): void
    {
        Schema::table('more_apps', function (Blueprint $t) {
            $t->dropUnique(['system_key']);
            $t->dropColumn(['is_system', 'system_key', 'route_name']);
        });
    }
};
