<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** N2N Batch 4 (MASTER-ONLY): the realtime event log (replay + the whole feed for the poll driver) and presence. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('n2n_realtime_events', function (Blueprint $t) {
            $t->id();                                      // monotonically increasing: this IS the cursor
            $t->unsignedBigInteger('user_id');
            $t->string('type', 64);
            $t->json('payload')->nullable();               // ids and minimal fields only; the client fetches details
            $t->timestamp('created_at')->useCurrent()->index();
            $t->index(['user_id', 'id']);
        });

        Schema::create('n2n_presence', function (Blueprint $t) {
            $t->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $t->timestamp('last_seen_at')->index();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->boolean('n2n_hide_presence')->default(false);   // hide = neither see others' last seen/typing nor be seen
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('n2n_hide_presence'));
        Schema::dropIfExists('n2n_presence');
        Schema::dropIfExists('n2n_realtime_events');
    }
};
