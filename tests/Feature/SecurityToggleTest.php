<?php

namespace Tests\Feature;

use App\Livewire\Admin\Security as SecurityPanel;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\NewDeviceLoginNotification;
use App\Support\SecuritySettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin-toggleable runtime security controls (blueprint Section 30) — the
 * non-technical operator can switch CSP/HSTS off if they clash with their host.
 */
class SecurityToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_an_admin_can_turn_off_the_csp_header_and_it_applies_live(): void
    {
        // On by default.
        $this->get('/login')->assertHeader('Content-Security-Policy');

        // Toggle off — the Setting::saved hook busts the cache, no redeploy.
        Setting::setValue('security.csp_enabled', false);

        $this->assertFalse(SecuritySettings::cspEnabled());
        $this->get('/login')->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_only_a_super_admin_can_change_site_protection(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(SecurityPanel::class)
            ->call('saveSiteProtection')
            ->assertStatus(403);

        $super = User::factory()->create();
        $super->assignRole('super_admin');

        Livewire::actingAs($super)->test(SecurityPanel::class)
            ->set('csp_enabled', false)
            ->set('hsts_enabled', true)
            ->call('saveSiteProtection')
            ->assertHasNoErrors();

        $this->assertFalse(SecuritySettings::cspEnabled());
        $this->assertTrue(SecuritySettings::hstsEnabled());
        $this->assertDatabaseHas('audit_logs', ['action' => 'security.settings_updated']);
    }

    // ---- Phase D: one fixed login-alert visual --------------------------------

    public function test_only_a_super_admin_can_upload_or_remove_the_login_alert_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(SecurityPanel::class)
            ->set('login_alert_image', UploadedFile::fake()->image('alert.png'))
            ->call('saveLoginAlertImage')
            ->assertStatus(403);

        $super = User::factory()->create();
        $super->assignRole('super_admin');

        Livewire::actingAs($super)->test(SecurityPanel::class)
            ->set('login_alert_image', UploadedFile::fake()->image('alert.png'))
            ->call('saveLoginAlertImage')
            ->assertHasNoErrors();

        $this->assertNotNull(SecuritySettings::loginAlertImageUrl());
        $this->assertDatabaseHas('audit_logs', ['action' => 'security.login_alert_image_updated']);

        Livewire::actingAs($admin)->test(SecurityPanel::class)
            ->call('removeLoginAlertImage')
            ->assertStatus(403);
        $this->assertNotNull(SecuritySettings::loginAlertImageUrl());

        Livewire::actingAs($super)->test(SecurityPanel::class)
            ->call('removeLoginAlertImage')
            ->assertHasNoErrors();
        $this->assertNull(SecuritySettings::loginAlertImageUrl());
        $this->assertDatabaseHas('audit_logs', ['action' => 'security.login_alert_image_removed']);
    }

    public function test_the_login_alert_image_is_null_until_an_admin_uploads_one(): void
    {
        $this->assertNull(SecuritySettings::loginAlertImageUrl());
    }

    /**
     * The SAME image on every send — never per-notification custom media
     * (owner decision, content/billing/hot-menu blueprint Phase D). Checked
     * two ways: the notification wires SecuritySettings' value into the mail
     * view data, and the blade actually renders it as an <img> when present.
     */
    public function test_the_new_device_login_notification_carries_the_one_configured_image(): void
    {
        $user = User::factory()->create();

        $mail = (new NewDeviceLoginNotification('1.2.3.4', 'Chrome on macOS'))->toMail($user);
        $this->assertNull($mail->viewData['alertImageUrl']);

        Setting::setValue('security.login_alert_image_url', 'https://cdn.example.com/security-alert.png', 'security');
        SecuritySettings::flush();

        $mail = (new NewDeviceLoginNotification('1.2.3.4', 'Chrome on macOS'))->toMail($user);
        $this->assertSame('https://cdn.example.com/security-alert.png', $mail->viewData['alertImageUrl']);
    }

    public function test_the_new_device_login_blade_only_shows_an_image_when_one_is_configured(): void
    {
        $html = Blade::render('@include(\'emails.new-device-login\', [
            "ipAddress" => "1.2.3.4", "userAgent" => "Chrome on macOS",
            "when" => "1 Jan 2026, 00:00", "url" => "https://x/y", "alertImageUrl" => null,
        ])');
        $this->assertStringNotContainsString('<img', $html);

        $html = Blade::render('@include(\'emails.new-device-login\', [
            "ipAddress" => "1.2.3.4", "userAgent" => "Chrome on macOS",
            "when" => "1 Jan 2026, 00:00", "url" => "https://x/y",
            "alertImageUrl" => "https://cdn.example.com/security-alert.png",
        ])');
        $this->assertStringContainsString('https://cdn.example.com/security-alert.png', $html);
    }
}
