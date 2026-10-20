<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One row per Claude call (NaaraBrain Sprint 0, MASTER-ONLY): real tokens, cache hits, cost and latency, so Brain is priced from measurements. No prompt or reply text is stored. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_log', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->string('feature', 80)->index();
            $t->string('model', 80);
            $t->unsignedInteger('input_tokens')->default(0);
            $t->unsignedInteger('output_tokens')->default(0);
            $t->unsignedInteger('cache_write_tokens')->default(0);
            $t->unsignedInteger('cache_read_tokens')->default(0);
            $t->decimal('cost_usd', 12, 6)->default(0);
            $t->unsignedInteger('duration_ms')->default(0);
            $t->unsignedTinyInteger('attempts')->default(1);
            $t->string('status', 16)->default('ok');       // ok | error
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->string('error', 190)->nullable();
            $t->string('request_id', 80)->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_log');
    }
};
