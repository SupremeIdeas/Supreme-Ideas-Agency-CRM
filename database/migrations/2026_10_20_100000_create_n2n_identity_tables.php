<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** N2N Batch 3 (MASTER-ONLY): verified phone link + OTP log. users.phone stays an unverified claim and is never overloaded. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('n2n_user_phones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();   // v1: one linked number per account
            $t->string('e164', 20)->unique();                                       // one verified number -> one account
            $t->string('country_code', 2)->nullable();
            $t->timestamp('verified_at');
            $t->boolean('discoverable')->default(true);
            $t->timestamps();
        });

        Schema::create('n2n_phone_verifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('e164', 20)->index();
            $t->string('code_hash', 64);
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->boolean('suppressed')->default(false);      // number already linked elsewhere: no SMS sent, caller is told nothing
            $t->string('ip', 45)->nullable();
            $t->decimal('cost_usd', 10, 5)->nullable();      // measured per send, reported to the owner (never guessed)
            $t->timestamp('expires_at');
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'created_at']);
            $t->index(['ip', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('n2n_phone_verifications');
        Schema::dropIfExists('n2n_user_phones');
    }
};
