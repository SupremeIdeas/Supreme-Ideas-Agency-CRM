<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * More Apps carousel (master-only — App\Support\FeatureEntitlements::isMaster()).
 * Admin-managed shortcuts (icon + name + link) shown in the mobile "More"
 * sheet, e.g. cross-promoting a companion Supreme Ideas app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('more_apps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon_url');
            $table->string('link_url');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('more_apps');
    }
};
