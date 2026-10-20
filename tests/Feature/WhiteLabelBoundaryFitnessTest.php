<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Architecture-fitness guard — surgery Phase 6, MASTER side. The master platform
 * (product `naarasim-core`) is the ONE AND ONLY place that may issue, oversee,
 * or distribute white-label licenses. This is the INVERSE of the white-label
 * forks' guard: it fails the build if an over-eager future cleanup ever strips
 * that capability from master (the forks' guard protects against it reappearing
 * there; this protects against it disappearing here). See
 * docs/architecture/WHITE-LABEL-LICENSE-BOUNDARY.md.
 */
class WhiteLabelBoundaryFitnessTest extends TestCase
{
    public function test_master_retains_the_license_issuer(): void
    {
        $issuer = 'App\\Services\\Updater\\WhiteLabelLicenseService';
        $this->assertTrue(class_exists($issuer), 'the master must retain the license issuer service');
        $this->assertTrue(method_exists($issuer, 'issueLicense'), 'issueLicense() must remain on the master');
        $this->assertTrue(method_exists($issuer, 'revokeLicense'), 'revokeLicense() must remain on the master');
    }

    public function test_master_retains_the_oversight_registry_and_its_route(): void
    {
        $this->assertTrue(class_exists('App\\Livewire\\Admin\\WhiteLabelRegistry'), 'the master must retain the oversight registry');
        $this->assertTrue(Route::has('admin.white-label'), 'the master oversight route must remain navigable');
    }

    public function test_master_retains_the_update_distributor(): void
    {
        $this->assertTrue(class_exists('App\\Http\\Controllers\\Api\\V1\\WhiteLabel\\WhiteLabelUpdateController'), 'the master must retain the update distributor');
        $this->assertTrue(Route::has('white-label.updates.check'), 'the master distribution API must remain registered');
    }
}
