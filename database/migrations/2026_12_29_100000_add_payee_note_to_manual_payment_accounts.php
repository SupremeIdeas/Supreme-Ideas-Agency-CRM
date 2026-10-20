<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The line the payer sees above the receiving details (who they are paying). Admin-editable per account, so nothing about the payee is hard-coded. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_payment_accounts', fn (Blueprint $t) => $t->string('payee_note', 300)->nullable()->after('label'));
    }

    public function down(): void
    {
        Schema::table('manual_payment_accounts', fn (Blueprint $t) => $t->dropColumn('payee_note'));
    }
};
