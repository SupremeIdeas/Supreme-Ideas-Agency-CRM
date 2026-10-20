<?php

namespace Tests\Feature\Omni;

use App\Models\ChannelConnection;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletGroup;
use App\Services\Omni\WordPress\WebChatPluginPackager;
use App\Services\Permissions\WorkspaceRoleService;
use App\Support\Permissions\BusinessRole;
use App\Support\Workspace;
use Database\Seeders\BusinessRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

/** The WordPress plugin for web chat: a real zip, pre-set with the public site key only, downloadable only by the owning workspace's owner or admin. */
class WebChatPluginTest extends TestCase
{
    use \Tests\Concerns\EnablesBusinessSuite;
    use RefreshDatabase;

    private User $owner;

    private WalletGroup $ws;

    private ChannelConnection $chat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(BusinessRoleSeeder::class);
        Setting::setValue('business.module.inbox', true, 'business');
        Setting::setValue('business.module.omni', true, 'business');
        $this->owner = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $this->ws = WalletGroup::create(['owner_user_id' => $this->owner->id, 'name' => 'Acme', 'kind' => WalletGroup::KIND_BUSINESS]);
        app(WorkspaceRoleService::class)->assignRole($this->ws, $this->owner, BusinessRole::OWNER);
        Workspace::setActive($this->ws);
        $this->chat = ChannelConnection::create(['wallet_group_id' => $this->ws->id, 'channel' => 'webchat', 'external_id' => 'wc-abc', 'status' => 'active', 'label' => 'Site',
            'credentials' => ['allowed_origins' => ['https://shop.example'], 'greeting' => 'Hi', 'color' => '#0A6E6E'], 'webhook_secret' => 'SUPER-SECRET-SIGNING-VALUE']);
        $this->chat->forceFill(['public_id' => (string) Str::uuid()])->save();
    }

    private function sess(): array
    {
        return ['active_wallet_group_id' => $this->ws->id];
    }

    public function test_the_download_is_a_real_zip_with_the_site_key_and_no_secret(): void
    {
        config(['app.url' => 'https://naara.app']);
        $r = $this->actingAs($this->owner)->withSession($this->sess())->get(route('omni.webchat.plugin', ['connection' => $this->chat->id]));
        $r->assertOk();
        $this->assertSame('application/zip', $r->headers->get('Content-Type'));
        $tmp = tempnam(sys_get_temp_dir(), 'z');
        file_put_contents($tmp, $r->baseResponse->getFile()->getContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        foreach (['naara-respond-chat/naara-respond-chat.php', 'naara-respond-chat/defaults.php', 'naara-respond-chat/readme.txt', 'naara-respond-chat/uninstall.php', 'naara-respond-chat/assets/chat-flow.svg'] as $f) {
            $this->assertContains($f, $names);
        }
        $defaults = (string) $zip->getFromName('naara-respond-chat/defaults.php');
        $this->assertStringContainsString($this->chat->public_id, $defaults);
        $this->assertStringContainsString('https://naara.app', $defaults);
        $all = '';
        foreach ($names as $n) {
            $all .= (string) $zip->getFromName($n);
        }
        $this->assertStringNotContainsString('SUPER-SECRET-SIGNING-VALUE', $all);
        $this->assertStringNotContainsString('shop.example', $all);
        $zip->close();
    }

    public function test_another_workspace_or_a_plain_member_gets_a_404(): void
    {
        $other = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $g2 = WalletGroup::create(['owner_user_id' => $other->id, 'name' => 'Other', 'kind' => WalletGroup::KIND_BUSINESS]);
        app(WorkspaceRoleService::class)->assignRole($g2, $other, BusinessRole::OWNER);
        $this->actingAs($other)->withSession(['active_wallet_group_id' => $g2->id])->get(route('omni.webchat.plugin', ['connection' => $this->chat->id]))->assertNotFound();

        $agent = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        app(WorkspaceRoleService::class)->assignRole($this->ws, $agent, BusinessRole::AGENT);
        $this->actingAs($agent)->withSession($this->sess())->get(route('omni.webchat.plugin', ['connection' => $this->chat->id]))->assertNotFound();
        auth()->logout();
        $this->assertContains($this->get(route('omni.webchat.plugin', ['connection' => $this->chat->id]))->status(), [302, 404], 'a visitor never gets the file');
    }

    public function test_a_connection_that_is_not_web_chat_is_not_downloadable(): void
    {
        $tg = ChannelConnection::create(['wallet_group_id' => $this->ws->id, 'channel' => 'telegram', 'external_id' => '9', 'status' => 'active', 'label' => 'tg', 'credentials' => ['bot_token' => '1:a'], 'webhook_secret' => 'x']);
        $tg->forceFill(['public_id' => (string) Str::uuid()])->save();
        $this->actingAs($this->owner)->withSession($this->sess())->get(route('omni.webchat.plugin', ['connection' => $tg->id]))->assertNotFound();
    }

    public function test_the_plugin_source_is_valid_php_with_the_wordpress_safeguards(): void
    {
        $root = base_path(WebChatPluginPackager::SOURCE);
        foreach (['naara-respond-chat.php', 'uninstall.php'] as $f) {
            $out = [];
            exec('php -l '.escapeshellarg($root.'/'.$f).' 2>&1', $out, $code);
            $this->assertSame(0, $code, implode("\n", $out));
        }
        $src = (string) file_get_contents($root.'/naara-respond-chat.php');
        $this->assertStringContainsString("if (! defined('ABSPATH'))", $src);
        $this->assertStringContainsString("current_user_can('manage_options')", $src);
        $this->assertStringContainsString('register_setting', $src);
        $this->assertStringContainsString('esc_attr', $src);
        $this->assertStringContainsString('https', $src);
        $this->assertSame('1.0.0', WebChatPluginPackager::version());
    }

    public function test_the_channels_page_offers_the_download_on_the_web_chat_card(): void
    {
        \Livewire\Livewire::actingAs($this->owner)->test(\App\Livewire\BusinessChannels::class)->assertSee('data-wp-plugin', false)->assertSee('Download the plugin (.zip)');
    }

    public function test_the_banner_says_plainly_what_is_downloaded_and_every_file_is_described(): void
    {
        $info = WebChatPluginPackager::info();
        $this->assertSame('1.0.0', $info['version']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $info['fingerprint']);
        $this->assertSame($info['fingerprint'], WebChatPluginPackager::info()['fingerprint'], 'the fingerprint is stable until the code changes');
        $this->assertGreaterThan(0, $info['kb']);
        // every file that ships (bar the generated defaults) is explained in the banner, and nothing unexplained ships
        $root = base_path(WebChatPluginPackager::SOURCE);
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile()) {
                $this->assertArrayHasKey(str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1)), $info['files'], 'an unexplained file in the plugin');
            }
        }
        \Livewire\Livewire::actingAs($this->owner)->test(\App\Livewire\BusinessChannels::class)
            ->assertSee('data-wp-plugin-card', false)->assertSee('Naara Respond Chat')->assertSee('Version 1.0.0')->assertSee('What it never does')->assertSee('Contains no password, API key or secret')
            ->assertSee('data-wp-plugin-fingerprint', false)->assertSee($info['fingerprint'])->assertSee('Plugins, Add New, Upload Plugin')->assertSee('Your WordPress site, the Naara chat bubble', false);
    }

    public function test_the_illustration_is_valid_svg_with_a_title_and_no_script(): void
    {
        $svg = (string) file_get_contents(base_path(WebChatPluginPackager::SOURCE.'/assets/chat-flow.svg'));
        $this->assertNotFalse(simplexml_load_string($svg), 'well-formed XML');
        $this->assertStringContainsString('<title', $svg);
        $this->assertStringContainsString('role="img"', $svg);
        $this->assertStringNotContainsStringIgnoringCase('<script', $svg);
        $this->assertStringNotContainsStringIgnoringCase('href="http', $svg);
        $this->assertLessThan(20000, strlen($svg), 'light enough to ship');
    }
}
