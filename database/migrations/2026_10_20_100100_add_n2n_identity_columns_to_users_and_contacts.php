<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** N2N Batch 3 (MASTER-ONLY): Naara ID, username, discovery switches, and the contact -> registered-user link. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('naara_id', 16)->nullable()->unique();           // immutable, non-sequential, checksummed; never reused
            $t->string('username', 20)->nullable()->unique();           // stored lower-case
            $t->timestamp('username_changed_at')->nullable();
            $t->boolean('n2n_hide_me')->default(false);                 // excluded from phone/email/username discovery
            $t->boolean('n2n_find_by_email')->default(false);           // opt-in: email is never a discovery key by default
        });

        Schema::table('contacts', function (Blueprint $t) {
            $t->string('e164', 20)->nullable()->index();
            $t->foreignId('linked_user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $t->timestamp('linked_at')->nullable();
            $t->timestamp('imported_at')->nullable();                   // set only on rows created by an import, so they can be deleted together
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $t) {
            $t->dropConstrainedForeignId('linked_user_id');
            $t->dropColumn(['e164', 'linked_at', 'imported_at']);
        });
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['naara_id', 'username', 'username_changed_at', 'n2n_hide_me', 'n2n_find_by_email']);
        });
    }
};
