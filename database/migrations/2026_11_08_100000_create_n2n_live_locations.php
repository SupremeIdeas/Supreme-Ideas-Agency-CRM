<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live location in Naara chat (N2N, MASTER-ONLY): one row per `live_location` message. Only the latest position is kept (no trail), it is readable by the
 * other member of that chat only, and it is wiped the moment sharing stops or expires (the purge job also clears anything stale). The message row keeps
 * only the words "Live location": coordinates never appear in message bodies, previews, search, exports, reports or moderation excerpts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('n2n_live_locations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('message_id')->unique()->constrained('n2n_messages')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->decimal('lat', 9, 6)->nullable();
            $t->decimal('lng', 9, 6)->nullable();
            $t->unsignedInteger('accuracy_m')->nullable();
            $t->timestamp('expires_at');
            $t->timestamp('stopped_at')->nullable();
            $t->timestamp('last_update_at')->nullable();
            $t->timestamps();
            $t->index(['expires_at', 'stopped_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('n2n_live_locations');
    }
};
