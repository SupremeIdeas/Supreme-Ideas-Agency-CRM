<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Discovery by phone is opt-in (engineering blueprint 4.3): new rows default to hidden. Existing members keep the choice they already have. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('n2n_user_phones', function (Blueprint $t) {
            $t->boolean('discoverable')->default(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('n2n_user_phones', function (Blueprint $t) {
            $t->boolean('discoverable')->default(true)->change();
        });
    }
};
