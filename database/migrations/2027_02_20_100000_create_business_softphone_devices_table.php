<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Native softphone (MASTER-ONLY), additive: one row per phone a member paired. Holds no push token and no secret (Twilio keeps the push registration); it exists so an admin can see what is installed and what has gone quiet. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_softphone_devices')) {
            return;
        }
        Schema::create('business_softphone_devices', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('device_id', 64);                    // chosen by the app, stable per install
            $t->string('platform', 8);                      // ios | android
            $t->string('app_version', 24)->nullable();
            $t->string('os_version', 24)->nullable();
            $t->boolean('push_ready')->default(false);      // the token carried a push credential for this platform
            $t->unsignedBigInteger('token_id')->nullable(); // the Sanctum token issued at pairing (revoked on unpair)
            $t->timestamp('paired_at')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'device_id'], 'bsd_user_device_uq');
            $t->index('last_seen_at', 'bsd_seen_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_softphone_devices');
    }
};
