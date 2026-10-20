<?php

namespace Tests\Feature;

use App\Livewire\Admin\MasterMarketing\Installers;
use App\Models\MasterMarketingInstaller;
use App\Models\User;
use App\Support\AppExport;
use App\Support\MasterMarketing\InstallerCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Admin > Installers and the desktop/browser section of /download (MASTER ONLY). */
class MasterMarketingInstallersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('super_admin');
        $u->forceFill(['two_factor_secret' => encrypt('SECRETKEY'), 'two_factor_confirmed_at' => now()])->save();

        return $u;
    }


    /** A real zip (stored, so the size is predictable) with the given entries. */
    private function zipBytes(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'z');
        $z = new \ZipArchive;
        $z->open($path, \ZipArchive::OVERWRITE | \ZipArchive::CREATE);
        foreach ($entries as $n => $c) {
            $z->addFromString($n, $c);
            $z->setCompressionName($n, \ZipArchive::CM_STORE);
        }
        $z->close();
        $b = file_get_contents($path);
        @unlink($path);

        return $b;
    }

    private function machoZip(string $cpu): string
    {
        return $this->zipBytes(['Naara.app/Contents/MacOS/Naara' => "\xCF\xFA\xED\xFE".($cpu === 'arm64' ? "\x0C\x00\x00\x01" : "\x07\x00\x00\x01").str_repeat("\0", 64)]);
    }

    private function fake(string $name, string $bytes): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(\App\Support\MediaStorage::disk());
        AppExport::save(['app_export_enabled' => true]);
    }

    public function test_upload_records_size_and_sha_and_stays_hidden_until_published(): void
    {
        $file = UploadedFile::fake()->createWithContent('Naara-Setup.exe', 'MZ'.str_repeat('A', 2046));

        $this->actingAs($this->admin())->post(route('admin.installers.upload', 'windows'), ['installer' => $file])->assertSessionHasNoErrors();

        $row = MasterMarketingInstaller::where('platform', 'windows')->firstOrFail();
        $this->assertSame(hash('sha256', 'MZ'.str_repeat('A', 2046)), $row->sha256);
        $this->assertSame(2048, $row->size_bytes);
        Storage::disk(\App\Support\MediaStorage::disk())->assertExists($row->file_path);
        $this->assertFalse($row->isLive(), 'uploaded but not published');
        $this->get(route('download'))->assertOk()->assertDontSee('data-installer-card="windows"', false);
    }

    public function test_publishing_shows_the_card_and_the_download_counts_and_redirects(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.installers.upload', 'linux'), ['installer' => UploadedFile::fake()->createWithContent('naara.AppImage', "\x7FELF".str_repeat("\0", 40))]);

        Livewire::actingAs($admin)->test(Installers::class)
            ->set('form.linux.version', '1.2.3')->set('form.linux.is_published', true)->call('save', 'linux')->assertSee('Live');

        $this->get(route('download'), ['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) Firefox/120'])
            ->assertOk()->assertSee('data-installer-card="linux"', false)->assertSee('Your computer')->assertSee('Version 1.2.3');
        $this->get(route('download.installer', 'linux'))->assertRedirect();
        $this->assertSame(1, MasterMarketingInstaller::where('platform', 'linux')->value('downloads'));
    }

    public function test_an_unpublished_or_empty_platform_cannot_be_downloaded(): void
    {
        MasterMarketingInstaller::create(['platform' => 'macos', 'external_url' => 'https://example.com/n.dmg', 'is_published' => false]);

        $this->get(route('download.installer', 'macos'))->assertNotFound();
        $this->get(route('download.installer', 'windows'))->assertNotFound();
    }

    public function test_a_pasted_link_replaces_an_uploaded_file(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.installers.upload', 'chrome'), ['installer' => UploadedFile::fake()->createWithContent('ext.zip', $this->zipBytes(['manifest.json' => '{"manifest_version":3,"version":"1.0.0"}']))]);
        $path = MasterMarketingInstaller::where('platform', 'chrome')->value('file_path');

        Livewire::actingAs($admin)->test(Installers::class)
            ->set('form.chrome.external_url', 'https://chromewebstore.google.com/detail/naara/abc')->set('form.chrome.is_published', true)->call('save', 'chrome');

        $row = MasterMarketingInstaller::where('platform', 'chrome')->first();
        $this->assertNull($row->file_path);
        Storage::disk(\App\Support\MediaStorage::disk())->assertMissing($path);
        $this->assertTrue($row->isLive());
        $this->get(route('download.installer', 'chrome'))->assertRedirect('https://chromewebstore.google.com/detail/naara/abc');
    }

    public function test_wrong_file_types_and_non_admins_are_refused(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.installers.upload', 'windows'), ['installer' => UploadedFile::fake()->createWithContent('evil.php', '<?php')])->assertSessionHasErrors('installer');
        $this->assertSame(0, MasterMarketingInstaller::count());

        $plain = User::factory()->create();
        $plain->assignRole('user');
        $this->actingAs($plain)->post(route('admin.installers.upload', 'windows'), ['installer' => UploadedFile::fake()->createWithContent('a.exe', 'x')])->assertStatus(404);
    }

    public function test_android_upload_feeds_the_existing_apk_setting(): void
    {
        $this->actingAs($this->admin())->post(route('admin.installers.upload', 'android'), ['installer' => UploadedFile::fake()->createWithContent('naara.apk', $this->zipBytes(['AndroidManifest.xml' => 'x', 'classes.dex' => 'y']))]);

        $this->assertStringContainsString('installers/android/', (string) AppExport::get('android_apk_url'));
        $this->assertTrue(AppExport::androidDownloadable());
    }

    public function test_browser_detection(): void
    {
        $this->assertSame('windows', InstallerCatalog::detect('Mozilla/5.0 (Windows NT 10.0; Win64)'));
        $this->assertSame('macos', InstallerCatalog::detect('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)'));
        $this->assertNull(InstallerCatalog::detect('Mozilla/5.0 (Linux; Android 14)'));
        $this->assertNull(InstallerCatalog::detect('Mozilla/5.0 (iPhone; CPU iPhone OS 17 like Mac OS X)'));
    }

    public function test_numbered_parts_are_joined_checked_and_stored_as_one_file(): void
    {
        $whole = $this->zipBytes(['Naara.exe' => random_bytes(4500)]);
        $parts = [
            UploadedFile::fake()->createWithContent('Naara-1.0.0-win-x64.zip.part01', substr($whole, 2000, 2000)),
            UploadedFile::fake()->createWithContent('Naara-1.0.0-win-x64.zip.part00', substr($whole, 0, 2000)),
            UploadedFile::fake()->createWithContent('Naara-1.0.0-win-x64.zip.part02', substr($whole, 4000)),
        ];

        $this->actingAs($this->admin())->post(route('admin.installers.upload', 'windows'), ['parts' => $parts, 'expected_sha256' => hash('sha256', $whole)])->assertSessionHasNoErrors();

        $row = MasterMarketingInstaller::where('platform', 'windows')->firstOrFail();
        $this->assertSame(hash('sha256', $whole), $row->sha256);
        $this->assertSame(strlen($whole), $row->size_bytes);
        $this->assertSame($whole, Storage::disk(\App\Support\MediaStorage::disk())->get($row->file_path));
        $this->assertStringEndsWith('.zip', $row->file_name);
    }

    public function test_missing_wrong_or_mismatched_parts_save_nothing(): void
    {
        $admin = $this->admin();
        $p = fn ($n, $c) => UploadedFile::fake()->createWithContent($n, $c);

        $this->actingAs($admin)->post(route('admin.installers.upload', 'windows'), ['parts' => [$p('a.zip.part00', 'x'), $p('a.zip.part02', 'z')]])->assertSessionHasErrors('installer');
        $this->actingAs($admin)->post(route('admin.installers.upload', 'windows'), ['parts' => [$p('a.zip.part00', 'x'), $p('b.zip.part01', 'z')]])->assertSessionHasErrors('installer');
        $this->actingAs($admin)->post(route('admin.installers.upload', 'windows'), ['parts' => [$p('a.zip', 'x')]])->assertSessionHasErrors('installer');
        $this->actingAs($admin)->post(route('admin.installers.upload', 'windows'), ['parts' => [$p('a.zip.part00', 'x'), $p('a.zip.part01', 'y')], 'expected_sha256' => str_repeat('a', 64)])->assertSessionHasErrors('installer');
        $this->actingAs($admin)->post(route('admin.installers.upload', 'windows'), ['parts' => [$p('a.php.part00', 'x')]])->assertSessionHasErrors('installer');

        $this->assertSame(0, MasterMarketingInstaller::count());
    }

    public function test_guess_sorts_the_real_release_file_names(): void
    {
        $g = fn (string $n) => InstallerCatalog::guess($n);
        $this->assertSame('windows', $g('Naara-1.0.1-win-x64.zip.part00')['platform']);
        $this->assertSame('macos', $g('Naara-1.0.1-mac-arm64.zip.part01')['platform']);
        $this->assertSame('macos_intel', $g('Naara-1.0.1-mac-x64.zip')['platform']);
        $this->assertSame('linux', $g('Naara-1.0.1-linux-x86_64.AppImage')['platform']);
        $this->assertSame('linux_deb', $g('Naara-1.0.1-linux-amd64.deb')['platform']);
        $this->assertSame('chrome', $g('naara-chrome-extension-1.0.1.zip')['platform']);
        $this->assertSame('android', $g('Naara-1.0.0-android-TEST-SIGNED.apk')['platform']);
        $this->assertNull($g('join.sh')['platform']);
        $this->assertSame('1.0.1', $g('Naara-1.0.1-win-x64.zip.part00')['version']);
        $this->assertSame('Naara-1.0.1-win-x64.zip', $g('Naara-1.0.1-win-x64.zip.part00')['base']);
    }

    private function sendSlices(User $admin, string $platform, string $id, array $slices): void
    {
        foreach ($slices as $i => $bytes) {
            $this->actingAs($admin)->postJson(route('admin.installers.chunk', $platform), ['upload_id' => $id, 'index' => $i, 'chunk' => UploadedFile::fake()->createWithContent('slice', $bytes)])->assertOk();
        }
    }

    public function test_sliced_upload_is_joined_verified_published_and_keeps_its_dots(): void
    {
        $admin = $this->admin();
        $whole = $this->machoZip('x64');
        $id = 'abcdef0123456789abcdef0123456789';
        $this->sendSlices($admin, 'macos_intel', $id, str_split($whole, 200));

        $r = $this->actingAs($admin)->postJson(route('admin.installers.finalize', 'macos_intel'), [
            'upload_id' => $id, 'total' => count(str_split($whole, 200)), 'name' => 'Naara-1.0.1-mac-x64.zip.part00', 'expected_sha256' => hash('sha256', $whole), 'version' => '1.0.1', 'publish' => true,
        ])->assertOk()->assertJson(['ok' => true, 'verified' => true, 'sha256' => hash('sha256', $whole)]);

        $row = MasterMarketingInstaller::where('platform', 'macos_intel')->firstOrFail();
        $this->assertTrue($row->isLive());
        $this->assertSame('1.0.1', $row->version);
        $this->assertStringStartsWith('Naara-1.0.1-mac-x64-', $row->file_name);
        $this->assertSame($whole, Storage::disk(\App\Support\MediaStorage::disk())->get($row->file_path));
        $this->assertDirectoryDoesNotExist(storage_path('app/installer-chunks/'.$id));
    }

    public function test_sliced_upload_with_a_wrong_checksum_or_missing_slice_saves_nothing(): void
    {
        $admin = $this->admin();
        $id = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $this->sendSlices($admin, 'windows', $id, ['aaaa', 'bbbb']);
        $this->actingAs($admin)->postJson(route('admin.installers.finalize', 'windows'), ['upload_id' => $id, 'total' => 2, 'name' => 'a.zip', 'expected_sha256' => str_repeat('a', 64)])
            ->assertStatus(422)->assertJson(['ok' => false]);

        $id2 = 'cccccccccccccccccccccccccccccccc';
        $this->sendSlices($admin, 'windows', $id2, ['aaaa']);
        $this->actingAs($admin)->postJson(route('admin.installers.finalize', 'windows'), ['upload_id' => $id2, 'total' => 2, 'name' => 'a.zip'])->assertStatus(422);
        $this->assertNull(MasterMarketingInstaller::where('platform', 'windows')->first());
    }

    public function test_slicing_is_admin_only_and_refuses_unsafe_names(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('admin.installers.chunk', 'windows'), ['upload_id' => 'abcdef0123456789abcdef0123456789', 'index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('s', 'x')])->assertStatus(404);
        $admin = $this->admin();
        $this->actingAs($admin)->postJson(route('admin.installers.chunk', 'windows'), ['upload_id' => '../../etc', 'index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('s', 'x')])->assertStatus(422);
        $id = 'dddddddddddddddddddddddddddddddd';
        $this->sendSlices($admin, 'windows', $id, ['x']);
        $this->actingAs($admin)->postJson(route('admin.installers.finalize', 'windows'), ['upload_id' => $id, 'total' => 1, 'name' => 'evil.php'])->assertStatus(422);
    }

    public function test_download_page_offers_both_mac_builds_and_both_linux_builds(): void
    {
        foreach (['macos', 'macos_intel', 'linux', 'linux_deb'] as $k) {
            MasterMarketingInstaller::create(['platform' => $k, 'external_url' => 'https://example.com/'.$k, 'is_published' => true, 'version' => '1.0.1']);
        }
        $this->get(route('download'))->assertOk()->assertSee('data-download="macos"', false)->assertSee('data-download="macos_intel"', false)
            ->assertSee('data-download="linux"', false)->assertSee('data-download="linux_deb"', false);
    }

    public function test_the_inspector_refuses_the_wrong_file_in_a_slot(): void
    {
        $admin = $this->admin();
        $send = function (string $platform, string $name, string $bytes, array $extra = []) use ($admin) {
            $id = substr(md5($platform.$name.microtime()), 0, 32);
            $this->sendSlices($admin, $platform, $id, [$bytes]);

            return $this->actingAs($admin)->postJson(route('admin.installers.finalize', $platform), ['upload_id' => $id, 'total' => 1, 'name' => $name] + $extra);
        };

        // an Intel Mac build in the Apple silicon slot
        $send('macos', 'Naara-1.0.2-mac-x64.zip', $this->machoZip('x64'))->assertStatus(422)->assertJsonFragment(['ok' => false]);
        $send('macos', 'Naara-1.0.2-mac-arm64.zip', $this->machoZip('arm64'))->assertOk()->assertJsonPath('facts.arch', 'arm64');
        // a program that is not a Windows program, a zip with no manifest, a Play bundle on the download page
        $send('windows', 'Naara-Setup.exe', 'this is text')->assertStatus(422);
        $send('chrome', 'ext.zip', $this->zipBytes(['readme.txt' => 'x']))->assertStatus(422);
        $send('android', 'Naara-1.0.2.aab', $this->zipBytes(['BundleConfig.pb' => 'x']))->assertStatus(422);
        $this->assertNull(MasterMarketingInstaller::where('platform', 'windows')->first());
    }

    public function test_an_older_build_does_not_silently_replace_a_newer_one_and_a_test_signed_apk_is_flagged(): void
    {
        $admin = $this->admin();
        $id1 = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';
        $exe = 'MZ'.str_repeat('B', 200);
        $this->sendSlices($admin, 'windows', $id1, [$exe]);
        $this->actingAs($admin)->postJson(route('admin.installers.finalize', 'windows'), ['upload_id' => $id1, 'total' => 1, 'name' => 'Naara-Setup-1.0.2.exe', 'version' => '1.0.2', 'publish' => true])->assertOk();

        $id2 = 'ffffffffffffffffffffffffffffffff';
        $this->sendSlices($admin, 'windows', $id2, [$exe]);
        $r = $this->actingAs($admin)->postJson(route('admin.installers.finalize', 'windows'), ['upload_id' => $id2, 'total' => 1, 'name' => 'Naara-Setup-1.0.1.exe', 'version' => '1.0.1'])->assertStatus(422)->assertJsonPath('needs_confirm', true);
        $this->assertSame('1.0.2', MasterMarketingInstaller::where('platform', 'windows')->value('version'));
        // the slices are kept, so confirming does not re-upload anything
        $this->actingAs($admin)->postJson(route('admin.installers.finalize', 'windows'), ['upload_id' => $id2, 'total' => 1, 'name' => 'Naara-Setup-1.0.1.exe', 'version' => '1.0.1', 'force' => true])->assertOk();
        $this->assertSame('1.0.1', MasterMarketingInstaller::where('platform', 'windows')->value('version'));

        $id3 = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $this->sendSlices($admin, 'android', $id3, [$this->zipBytes(['AndroidManifest.xml' => 'x', 'classes.dex' => 'y'])]);
        $this->actingAs($admin)->postJson(route('admin.installers.finalize', 'android'), ['upload_id' => $id3, 'total' => 1, 'name' => 'Naara-1.0.2-android-TEST-SIGNED.apk'])->assertOk()->assertJsonFragment(['signing' => 'test']);
    }

    public function test_consistency_flags_a_stale_slot(): void
    {
        MasterMarketingInstaller::create(['platform' => 'windows', 'external_url' => 'https://e.com/a', 'version' => '1.0.1']);
        MasterMarketingInstaller::create(['platform' => 'macos', 'external_url' => 'https://e.com/b', 'version' => '1.0.2']);
        $c = InstallerCatalog::consistency();
        $this->assertFalse($c['aligned']);
        $this->assertSame('1.0.2', $c['latest']);
        $this->assertSame(['windows'], $c['stale']);
    }
}
