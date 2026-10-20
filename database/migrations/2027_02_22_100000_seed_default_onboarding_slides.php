<?php

use App\Support\AppExport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * First-run onboarding (the slides people click through before login in the installed app) used to ship empty, so /get-started silently skipped to the login page.
 * This adds four on-brand slides on a fresh install, and on an upgrade only when the admin has not set any of their own. Admin > App Builder still replaces them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }
        if (AppExport::get('onboarding_slides', []) !== []) {
            return;
        }

        AppExport::save([
            'onboarding_enabled' => true,
            'onboarding_slides' => [
                ['image' => '/images/onboarding/slide-1-destination.svg', 'title' => 'Choose where you are going', 'subtitle' => '190+ countries. Pick your destination and we take care of the rest.'],
                ['image' => '/images/onboarding/slide-2-plan.svg', 'title' => 'Pick a data plan', 'subtitle' => 'Local data at fair prices. No roaming shocks and no SIM swaps.'],
                ['image' => '/images/onboarding/slide-3-activate.svg', 'title' => 'Activate in seconds', 'subtitle' => 'Scan the QR code and you are online before you land.'],
                ['image' => '/images/onboarding/slide-4-numbers.svg', 'title' => 'Numbers and calls too', 'subtitle' => 'Verification numbers and virtual lines, all from one wallet.'],
            ],
        ]);
    }

    public function down(): void
    {
        // slides are content the admin may have edited; never removed on rollback
    }
};
