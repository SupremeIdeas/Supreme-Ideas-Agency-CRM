<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NaaraBusiness Suite entitlements (MASTER-ONLY): what each Naara Pro plan grants beyond the listing itself. One JSON column so the admin can add a
 * capability without a migration: {"features": ["workspace","inbox"], "premium_skins": true, "monthly_credits": 25, "limits": {"seats": 10}}.
 * Null means "grants nothing from the Suite", which is what every existing plan keeps until the admin decides.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('brand_subscription_plans', 'capabilities')) {
            Schema::table('brand_subscription_plans', function (Blueprint $table) {
                $table->json('capabilities')->nullable()->after('credit_reward_per_follow');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('brand_subscription_plans', 'capabilities')) {
            Schema::table('brand_subscription_plans', function (Blueprint $table) {
                $table->dropColumn('capabilities');
            });
        }
    }
};
