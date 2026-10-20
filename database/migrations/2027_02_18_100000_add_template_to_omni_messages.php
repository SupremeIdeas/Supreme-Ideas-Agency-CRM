<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Naara Respond (MASTER-ONLY), additive: an outbound message can be an approved WhatsApp template (name, language, body parameters) instead of free text. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('omni_messages') && ! Schema::hasColumn('omni_messages', 'template')) {
            Schema::table('omni_messages', function (Blueprint $t) {
                $t->json('template')->nullable()->after('media');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('omni_messages', 'template')) {
            Schema::table('omni_messages', function (Blueprint $t) {
                $t->dropColumn('template');
            });
        }
    }
};
