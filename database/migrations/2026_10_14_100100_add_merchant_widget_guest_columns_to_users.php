<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Naara Widgets §4.3 — MASTER-ONLY. Purely additive: existing sign-up paths keep both columns at their defaults. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_widget_guest')->default(false)->after('merchant_id');
            $t->string('signup_channel')->nullable()->after('is_widget_guest');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['is_widget_guest', 'signup_channel']);
        });
    }
};
