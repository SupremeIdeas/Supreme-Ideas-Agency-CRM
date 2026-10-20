<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Brand-Growth Phase 4 (MASTER-ONLY): the free Naara Pro trial rides on the same subscription row (status 'trialing'). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_subscriptions', function (Blueprint $table) {
            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->foreignId('convert_to_plan_id')->nullable()->constrained('brand_subscription_plans')->nullOnDelete();
            $table->decimal('locked_price_usd', 10, 2)->nullable();
            $table->boolean('auto_convert')->default(true);
            $table->timestamp('notice_ack_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('unfunded_since')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->unsignedSmallInteger('reminders_sent')->default(0);
            $table->string('end_reason', 32)->nullable();   // converted | cancelled | unfunded | admin_grant | admin_ended
            $table->string('email_key', 64)->nullable()->index();
            $table->string('phone_key', 64)->nullable()->index();
            $table->index(['status', 'trial_ends_at']);
            $table->index(['status', 'next_retry_at']);
        });
    }

    public function down(): void
    {
        Schema::table('brand_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['convert_to_plan_id']);
            $table->dropIndex(['status', 'trial_ends_at']);
            $table->dropIndex(['status', 'next_retry_at']);
            $table->dropIndex(['email_key']);
            $table->dropIndex(['phone_key']);
            $table->dropColumn(['trial_started_at', 'trial_ends_at', 'convert_to_plan_id', 'locked_price_usd', 'auto_convert', 'notice_ack_at', 'converted_at', 'unfunded_since', 'next_retry_at', 'retry_count', 'reminders_sent', 'end_reason', 'email_key', 'phone_key']);
        });
    }
};
