<?php

use App\Support\AppExport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * MASTER ONLY. The last two onboarding slides introduce NaaraChat (N2N) and the Business Suite, so they exist only where those modules do. They are appended to the
 * four default slides on a fresh install, and on an upgrade only when the admin is still on the untouched defaults: custom slides are never changed.
 */
return new class extends Migration
{
    private const DEFAULT_IMAGES = [
        '/images/onboarding/slide-1-destination.svg', '/images/onboarding/slide-2-plan.svg', '/images/onboarding/slide-3-activate.svg', '/images/onboarding/slide-4-numbers.svg',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('settings') || ! class_exists(\App\Support\N2N\N2NSettings::class) || ! class_exists(\App\Support\Business\BusinessSuiteSettings::class)) {
            return;
        }
        $slides = AppExport::onboardingSlides();
        if (array_column($slides, 'image') !== self::DEFAULT_IMAGES) {
            return;
        }

        AppExport::save(['onboarding_slides' => array_merge($slides, [
            ['image' => '/images/onboarding/slide-5-n2n-chat.svg', 'title' => 'Chat and call on Naara', 'subtitle' => 'Message friends, family and your team by Naara ID, with voice and video calls built in.'],
            ['image' => '/images/onboarding/slide-6-business-suite.svg', 'title' => 'One inbox for your business', 'subtitle' => 'WhatsApp, Instagram, Telegram, SMS and web chat in one shared inbox.'],
        ])]);
    }

    public function down(): void
    {
    }
};
