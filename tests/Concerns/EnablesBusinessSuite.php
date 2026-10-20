<?php

namespace Tests\Concerns;

use App\Models\Setting;
use App\Support\Business\BusinessSettings;
use App\Support\Business\BusinessSuiteSettings;

/**
 * Business Suite tests run with the master switch on (it ships off) and the Suite open to everyone (production defaults to Naara Pro plans only, which has its own
 * tests: BusinessPlanAccessTest). Laravel calls setUpEnablesBusinessSuite() automatically.
 */
trait EnablesBusinessSuite
{
    protected function setUpEnablesBusinessSuite(): void
    {
        BusinessSuiteSettings::set(true);
        Setting::setValue(BusinessSettings::ACCESS.'model', 'open');
    }
}
