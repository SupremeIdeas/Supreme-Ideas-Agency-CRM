<?php

namespace Tests\Feature;

use Tests\TestCase;

class DevicePermissionsPrimerTest extends TestCase
{
    public function test_primer_renders_every_real_request_and_is_skippable(): void
    {
        $html = view('partials.device-permissions')->render();

        foreach (['getUserMedia', 'getCurrentPosition', 'Notification.requestPermission', 'nx-open-permissions', 'nx_perm_primer_v1'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        $this->assertStringContainsString('Not now', $html);
        $this->assertStringNotContainsString('contacts.select', $html, 'no WebView exposes contacts to a website, so we must not pretend to ask');
    }

    public function test_customer_layout_includes_the_primer(): void
    {
        $this->assertStringContainsString("partials.device-permissions", file_get_contents(resource_path('views/components/layouts/customer.blade.php')));
    }

    public function test_android_patch_declares_every_permission_the_site_uses(): void
    {
        $patch = file_get_contents(base_path('app-export/scripts/patch-android.mjs'));
        foreach (['CAMERA', 'RECORD_AUDIO', 'ACCESS_FINE_LOCATION', 'POST_NOTIFICATIONS', 'BLUETOOTH_CONNECT', 'MODIFY_AUDIO_SETTINGS'] as $p) {
            $this->assertStringContainsString("'$p'", $patch);
        }
    }

    public function test_mac_desktop_build_can_actually_reach_the_microphone_and_camera(): void
    {
        $pkg = json_decode(file_get_contents(base_path('wrappers/master-marketing/desktop/package.json')), true)['build']['mac'];
        $this->assertArrayHasKey('NSMicrophoneUsageDescription', $pkg['extendInfo']);
        $this->assertArrayHasKey('NSCameraUsageDescription', $pkg['extendInfo']);
        $plist = file_get_contents(base_path('wrappers/master-marketing/desktop/'.$pkg['entitlements']));
        $this->assertStringContainsString('com.apple.security.device.audio-input', $plist);
        $this->assertStringContainsString('com.apple.security.device.camera', $plist);
    }
}
