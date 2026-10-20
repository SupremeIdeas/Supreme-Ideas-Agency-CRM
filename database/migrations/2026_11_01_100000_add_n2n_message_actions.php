<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N2N message and chat actions (MASTER-ONLY): reactions (one per person per message, a closed set of codes), forwarded and edited markers, the
 * private "mark as unread" flag. Nothing here widens who can see what: reactions are visible to the two members of the conversation only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('n2n_messages', function (Blueprint $t) {
            $t->boolean('forwarded')->default(false);
            $t->timestamp('edited_at')->nullable();
            $t->json('edit_log')->nullable();                   // earlier versions, kept for moderation; never shown to the other member
        });
        Schema::table('n2n_participants', function (Blueprint $t) {
            $t->timestamp('marked_unread_at')->nullable();
        });
        Schema::create('n2n_message_reactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('message_id')->constrained('n2n_messages')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('code', 12);                             // like | love | laugh | wow | sad | pray: never free text
            $t->timestamps();
            $t->unique(['message_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('n2n_message_reactions');
        Schema::table('n2n_participants', fn (Blueprint $t) => $t->dropColumn('marked_unread_at'));
        Schema::table('n2n_messages', fn (Blueprint $t) => $t->dropColumn(['forwarded', 'edited_at', 'edit_log']));
    }
};
