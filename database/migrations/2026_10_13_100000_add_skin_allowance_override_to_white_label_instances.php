<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MASTER ONLY (Prompt 22): an admin's per-instance skin allowance, beating the tier default. Null = use the tier default. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('white_label_instances', function (Blueprint $table) {
            $table->unsignedTinyInteger('skin_allowance_override')->nullable()->after('entitlement_level');
        });
    }

    public function down(): void
    {
        Schema::table('white_label_instances', function (Blueprint $table) {
            $table->dropColumn('skin_allowance_override');
        });
    }
};
