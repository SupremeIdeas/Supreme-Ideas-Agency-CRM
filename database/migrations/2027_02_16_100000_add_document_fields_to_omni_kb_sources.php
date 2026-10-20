<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Naara Respond (MASTER-ONLY): uploaded documents become knowledge sources with a learning report. Additive and idempotent. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('omni_kb_sources', 'file_hash')) {
            Schema::table('omni_kb_sources', function (Blueprint $t) {
                $t->string('role', 8)->default('facts');          // facts (searched) | voice (style samples, never searched)
                $t->string('file_name', 190)->nullable();
                $t->string('file_hash', 64)->nullable();           // sha256 of the cleaned text: one workspace never learns the same document twice
                $t->unsignedInteger('word_count')->default(0);
                $t->unsignedInteger('chunk_count')->default(0);
                $t->unsignedSmallInteger('check_passed')->nullable();
                $t->unsignedSmallInteger('check_total')->nullable();
                $t->timestamp('checked_at')->nullable();
                $t->json('facts')->nullable();                     // what the reader found: contacts, prices, hours, questions
                $t->json('notes')->nullable();                     // warnings shown to the owner (lines removed, etc.)
                $t->index(['workspace_id', 'file_hash'], 'omni_kbs_ws_hash_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::table('omni_kb_sources', function (Blueprint $t) {
            $t->dropIndex('omni_kbs_ws_hash_idx');
            $t->dropColumn(['role', 'file_name', 'file_hash', 'word_count', 'chunk_count', 'check_passed', 'check_total', 'checked_at', 'facts', 'notes']);
        });
    }
};
