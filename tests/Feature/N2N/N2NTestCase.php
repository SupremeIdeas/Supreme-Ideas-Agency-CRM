<?php

namespace Tests\Feature\N2N;

use App\Models\Setting;
use App\Models\User;
use App\Support\N2N\N2NSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Shared fixtures for the N2N suite (MASTER-ONLY). */
abstract class N2NTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::setValue(N2NSettings::FLAG, true);
        config()->set('n2n.otp.driver', 'log');
    }

    protected function user(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['is_active' => true, 'country_code' => 'NG'], $attrs));
    }
}
