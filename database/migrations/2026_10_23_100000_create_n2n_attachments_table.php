<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** N2N Batch 7 (MASTER-ONLY): media attached to messages. Objects live on the private storage disk under random keys, never at a public URL. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('n2n_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('message_id')->nullable()->constrained('n2n_messages')->cascadeOnDelete();
            $t->foreignId('uploader_id')->constrained('users')->cascadeOnDelete();
            $t->string('kind', 8);                              // image | voice | video
            $t->string('disk', 40);
            $t->string('storage_key', 160)->unique();           // randomised; the original filename is never part of a key
            $t->string('thumb_key', 160)->nullable();
            $t->string('mime', 60);                             // the SNIFFED type, never the client's claim
            $t->unsignedBigInteger('bytes');                    // measured on the server
            $t->unsignedInteger('duration_s')->nullable();      // voice/video: client-declared, clamped, checked with ffprobe where present
            $t->unsignedSmallInteger('width')->nullable();
            $t->unsignedSmallInteger('height')->nullable();
            $t->string('status', 10)->default('staged');        // staged | attached | deleted
            $t->json('meta')->nullable();                       // voice transcript, sanitiser notes
            $t->timestamp('expires_at')->nullable()->index();   // staged uploads that never get attached are cleaned up
            $t->timestamps();
            $t->index(['message_id']);
            $t->index(['uploader_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('n2n_attachments');
    }
};
