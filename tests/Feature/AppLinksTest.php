<?php

namespace Tests\Feature;

use App\Support\AppExport;
use App\Support\AppStudio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Android App Links and iOS Universal Links files: only published once configured, and only with valid values. */
class AppLinksTest extends TestCase
{
    use RefreshDatabase;

    private const FP = '14:6D:E9:83:C5:73:06:50:D8:EE:B9:95:2F:34:FC:64:16:A0:83:42:E6:1D:BE:A8:8A:04:96:B2:3F:CF:44:E5';

    private function studio(array $v): void
    {
        \App\Models\Setting::setValue(AppStudio::SETTING_KEY, array_merge(AppStudio::all(), $v), 'appstudio');
    }

    public function test_nothing_is_published_until_configured(): void
    {
        $this->get('/.well-known/assetlinks.json')->assertNotFound();
        $this->get('/.well-known/apple-app-site-association')->assertNotFound();
    }

    public function test_assetlinks_carries_the_package_and_fingerprint(): void
    {
        $this->studio(['android_package_name' => 'app.naara.mobile', 'android_sha256' => strtolower(self::FP)]);

        $r = $this->get('/.well-known/assetlinks.json')->assertOk();
        $this->assertSame('app.naara.mobile', $r->json('0.target.package_name'));
        $this->assertSame([self::FP], $r->json('0.target.sha256_cert_fingerprints'));
        $this->assertSame('delegate_permission/common.handle_all_urls', $r->json('0.relation.0'));
    }

    public function test_a_malformed_fingerprint_is_ignored_not_published(): void
    {
        $this->studio(['android_sha256' => 'not-a-fingerprint, AA:BB']);
        $this->get('/.well-known/assetlinks.json')->assertNotFound();
    }

    public function test_apple_association_needs_a_valid_team_id(): void
    {
        $this->studio(['ios_team_id' => 'short', 'ios_bundle_id' => 'app.naara.mobile']);
        $this->get('/.well-known/apple-app-site-association')->assertNotFound();

        $this->studio(['ios_team_id' => 'abcde12345', 'ios_bundle_id' => 'app.naara.mobile']);
        $r = $this->get('/.well-known/apple-app-site-association')->assertOk();
        $this->assertSame('ABCDE12345.app.naara.mobile', $r->json('applinks.details.0.appIDs.0'));
        $this->assertSame('ABCDE12345.app.naara.mobile', $r->json('webcredentials.apps.0'));
    }
}
