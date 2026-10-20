<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduled media (N2N, MASTER-ONLY): a queued message may carry already-staged uploads (photos, video, voice, documents). The attachments stay
 * staged (their expiry is pushed past the send time) and are claimed by the ordinary send path at delivery, so Pro, size, sniffing, blocks, privacy
 * and the credit meter are all judged then. Nothing is charged at queue time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('n2n_scheduled_messages', function (Blueprint $t) {
            $t->json('attachment_ids')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('n2n_scheduled_messages', function (Blueprint $t) {
            $t->dropColumn('attachment_ids');
        });
    }
};
