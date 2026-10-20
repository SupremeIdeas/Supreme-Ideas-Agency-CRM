<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N2N disappearing messages and chat lock (MASTER-ONLY). A chat can carry a timer (set by either member): each NEW message gets `expires_at` and is hidden from
 * everyone the moment it passes, then purged for real by n2n:expire-messages. Chat lock is private to one member: a PIN (hashed, throttled) and a per-chat flag that
 * hides the chat from every list and search until the PIN is entered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('n2n_conversations', function (Blueprint $t) {
            $t->unsignedInteger('disappear_after')->nullable();            // seconds; null or 0 = off
            $t->unsignedBigInteger('disappear_by')->nullable();
        });
        Schema::table('n2n_messages', function (Blueprint $t) {
            $t->timestamp('expires_at')->nullable()->index();
        });
        Schema::table('n2n_participants', fn (Blueprint $t) => $t->timestamp('locked_at')->nullable());
        Schema::table('users', function (Blueprint $t) {
            $t->string('n2n_lock_hash')->nullable();
            $t->unsignedTinyInteger('n2n_lock_fails')->default(0);
            $t->timestamp('n2n_lock_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['n2n_lock_hash', 'n2n_lock_fails', 'n2n_lock_until']));
        Schema::table('n2n_participants', fn (Blueprint $t) => $t->dropColumn('locked_at'));
        Schema::table('n2n_messages', fn (Blueprint $t) => $t->dropColumn('expires_at'));
        Schema::table('n2n_conversations', fn (Blueprint $t) => $t->dropColumn(['disappear_after', 'disappear_by']));
    }
};
