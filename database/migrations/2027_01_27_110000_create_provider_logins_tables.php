<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provider login manager (MASTER-ONLY): a map of WHERE and HOW the owner signs in to each third-party dashboard. Deliberately has no password column
 * and nothing that could hold one: it is a reminder (login link, which email, which sign-in route), never a vault.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_logins', function (Blueprint $t) {
            $t->id();
            $t->string('provider_key', 60)->unique();
            $t->string('name', 120);
            $t->string('category', 40)->default('other');
            $t->string('login_url', 300)->nullable();
            $t->string('login_method', 24)->default('unconfirmed');   // google | email_password | username_password | sso | unconfirmed
            $t->string('email_hint', 190)->nullable();
            $t->string('username_hint', 120)->nullable();
            $t->string('notes', 300)->nullable();
            $t->boolean('is_custom')->default(false);
            $t->timestamp('confirmed_at')->nullable();   // null = still the seeded default, please confirm
            $t->timestamps();
        });

        Schema::create('provider_login_emails', function (Blueprint $t) {
            $t->id();
            $t->string('email', 190)->unique();
            $t->string('label', 80)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_login_emails');
        Schema::dropIfExists('provider_logins');
    }
};
