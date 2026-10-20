<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wl_funnel_manual_payments', fn (Blueprint $t) => $t->string('method', 12)->default('manual')->after('funnel_instance_id'));
    }

    public function down(): void
    {
        Schema::table('wl_funnel_manual_payments', fn (Blueprint $t) => $t->dropColumn('method'));
    }
};
