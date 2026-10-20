<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** N2N 1:1 video calls (MASTER-ONLY): a call is a video call from the moment it is placed; the caller is billed the (admin-set) video rate. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('n2n_calls', function (Blueprint $t) {
            $t->boolean('video')->default(false)->after('suppressed');
        });
    }

    public function down(): void
    {
        Schema::table('n2n_calls', function (Blueprint $t) {
            $t->dropColumn('video');
        });
    }
};
