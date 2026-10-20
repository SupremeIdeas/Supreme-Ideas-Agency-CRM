<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Engineering blueprint Phase 0: browser-side error beacon (explains "hangs") and the stored results of `naara:verify`. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_errors')) {
            Schema::create('client_errors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('kind', 16);                  // error | rejection | livewire
                $table->string('fingerprint', 40)->index();  // sha1(kind + message + first stack line): groups repeats
                $table->string('route', 160)->nullable();    // path only, never the query string
                $table->string('component', 120)->nullable(); // Livewire component name when known
                $table->string('message', 500);
                $table->text('stack')->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->unsignedSmallInteger('viewport_w')->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        }

        if (! Schema::hasTable('verify_runs')) {
            Schema::create('verify_runs', function (Blueprint $table) {
                $table->id();
                $table->string('area', 32)->index();
                $table->string('check', 80);
                $table->string('status', 8);                 // pass | warn | fail
                $table->string('detail', 500)->nullable();
                $table->json('evidence')->nullable();
                $table->string('trigger', 16)->default('cli'); // cli | admin | schedule
                $table->timestamp('ran_at')->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('verify_runs');
        Schema::dropIfExists('client_errors');
    }
};
