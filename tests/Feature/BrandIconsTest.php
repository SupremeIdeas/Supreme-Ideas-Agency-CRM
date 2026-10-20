<?php

namespace Tests\Feature;

use App\Livewire\Admin\BrandIcons;
use App\Models\Setting;
use App\Models\User;
use App\Support\IconOverrides;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * UX/localization/platform-reach blueprint Phase C: IconOverrides existed
 * with zero admin UI. This page is that missing screen — one row per payment
 * gateway, upload writes through the SAME IconOverrides storage
 * <x-payment-icon> already reads from.
 */
class BrandIconsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    public function test_a_non_admin_cannot_open_the_editor(): void
    {
        Livewire::actingAs(User::factory()->create())->test(BrandIcons::class)->assertForbidden();
    }

    public function test_admin_can_upload_a_gateway_logo_override(): void
    {
        Storage::fake('public');
        config(['filesystems.disks.wasabi.key' => null]);

        Livewire::actingAs($this->admin())->test(BrandIcons::class)
            ->set('uploadSlug', 'cryptomus')
            ->set('upload', UploadedFile::fake()->image('cryptomus.png', 200, 200))
            ->call('save')
            ->assertHasNoErrors();

        // Storage::fake() deliberately returns a relative "/storage/..." URL
        // (unlike production's absolute APP_URL-based one), and
        // IconOverrides::all() only recognizes absolute URLs when re-parsing
        // the stored textarea — so assert on the raw persisted setting here
        // rather than a for() round-trip, which the fake disk can't exercise
        // realistically. The absolute-URL round trip is covered directly in
        // IconSystemTest.
        $raw = (string) Setting::getValue('ui.icon_overrides');
        $this->assertStringContainsString('cryptomus', $raw);
        $this->assertStringContainsString('/brand-icons/', $raw);
    }

    public function test_admin_can_remove_an_override(): void
    {
        IconOverrides::set('pay.cryptomus', 'https://cdn.example.com/cryptomus.png');

        Livewire::actingAs($this->admin())->test(BrandIcons::class)
            ->call('remove', 'cryptomus');

        $this->assertNull(IconOverrides::for('pay.cryptomus'));
    }

    public function test_the_override_actually_renders_on_the_payment_icon_component(): void
    {
        IconOverrides::set('pay.cryptomus', 'https://cdn.example.com/cryptomus.png');

        $this->blade('<x-payment-icon slug="cryptomus" />')
            ->assertSee('src="https://cdn.example.com/cryptomus.png"', false);
    }

    public function test_the_row_list_covers_every_configured_gateway(): void
    {
        Livewire::actingAs($this->admin())->test(BrandIcons::class)
            ->assertSee('cryptomus')
            ->assertSee('coinpayments')
            ->assertSee('nowpayments')
            ->assertSee('payssion')
            ->assertSee('binance');
    }
}
