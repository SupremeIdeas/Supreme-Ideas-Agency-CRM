<?php

namespace Tests\Feature;

use App\Support\MasterOnlyModules;
use PHPUnit\Framework\TestCase;

/** Owner rule: NaaraBusiness Suite, NaaraBrain and NaaraChat (N2N) are never part of a white-label build. */
class BusinessSuiteMasterOnlyTest extends TestCase
{
    public function test_every_business_suite_path_is_refused_for_a_package(): void
    {
        foreach ([
            'app/Livewire/Business.php', 'app/Livewire/BusinessChannels.php', 'app/Support/Business/BusinessSettings.php', 'app/Services/Business/AiVoice/Relay.php',
            'app/Http/Middleware/EnsureBusinessSuiteEnabled.php', 'app/Http/Middleware/EnsureBusinessPlan.php', 'resources/views/livewire/business.blade.php',
            'resources/views/components/layouts/business.blade.php', 'resources/views/marketing/business-suite.blade.php', 'app/Livewire/CallFlowBuilder.php',
            'database/migrations/2026_10_02_100000_create_business_phone_core_tables.php', 'app/Console/Commands/BusinessDoctor.php', 'public/images/business/workspace-hero.webp',
            'app/Http/Controllers/Api/V1/Business/ContactsController.php', 'app/Jobs/Business/BusinessBrainJob.php', 'app/Models/BusinessBrainRun.php', 'app/Services/Business/Brain/BusinessBrain.php',
            'database/migrations/2026_12_14_100000_create_business_brain_runs.php', 'resources/views/livewire/admin/business-brain-economics.blade.php',
        ] as $path) {
            $this->assertTrue(MasterOnlyModules::matches($path), $path);
        }
    }

    public function test_the_other_master_only_products_are_still_refused(): void
    {
        foreach (['app/Livewire/N2N/Chat.php', 'app/Support/Brain/BrainSettings.php', 'resources/views/livewire/n2n/chat.blade.php'] as $path) {
            $this->assertTrue(MasterOnlyModules::matches($path), $path);
        }
    }

    public function test_unrelated_shared_files_are_not_caught(): void
    {
        foreach (['app/Support/BusinessRegistration.php', 'public/images/audiences/naara-business-traveler.webp', 'app/Livewire/Catalogue.php', 'app/Support/BottomNav.php'] as $path) {
            $this->assertFalse(MasterOnlyModules::matches($path), $path);
        }
    }
}
