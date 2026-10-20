<?php

namespace Tests\Feature;

use App\Services\Updater\PackageBuilder;
use App\Services\Updater\PackagePublisher;
use App\Support\MasterOnlyModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * N2N (Naara-to-Naara) is PERMANENTLY master-only (owner decision 2026-10-03), enforced like the license boundary:
 * a distributable package can never carry it, whichever way it was built.
 */
class MasterOnlyModulesGuardTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    private array $keys;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('app/testing/mo-'.uniqid());
        File::ensureDirectoryExists($this->dir);
        $this->keys = PackageBuilder::generateKeypair();
        config()->set('updater.public_key', $this->keys['public']);
        config()->set('updater.product_identifier', 'naarasim-core');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_the_path_matcher_catches_every_n2n_shape_and_nothing_innocent(): void
    {
        foreach ([
            'app/Livewire/N2N/Chat.php', 'app/Services/N2n/Router.php', 'resources/views/livewire/n2n/thread.blade.php',
            'database/migrations/2026_10_04_000001_create_n2n_threads_table.php', 'lang/fr/n2n.php', 'config/n2n.php',
            'tests/Feature/N2NFundingTest.php', 'app/Http/Controllers/N2NController.php', 'routes/n2n.php',
        ] as $path) {
            $this->assertTrue(MasterOnlyModules::matches($path), $path.' must be treated as master-only');
        }
        foreach (['app/Livewire/Wallet.php', 'resources/views/livewire/messages.blade.php', 'app/Support/Run2nd.php', 'public/images/n20.webp'] as $path) {
            $this->assertFalse(MasterOnlyModules::matches($path), $path.' is innocent');
        }
    }

    public function test_the_path_matcher_catches_every_naarabrain_shape(): void
    {
        foreach ([
            'app/Livewire/Admin/Brain/AiCosts.php', 'app/Services/Brain/BrainAgent.php', 'resources/views/livewire/brain/chat.blade.php',
            'database/migrations/2026_11_22_000001_create_brain_threads_table.php', 'config/brain.php', 'tests/Feature/Brain/IsolationTest.php',
            'public/images/brand/brain/naarabrain-chat.webp', 'app/Http/Controllers/BrainController.php', 'lang/fr/brain.php',
        ] as $path) {
            $this->assertTrue(MasterOnlyModules::matches($path), $path.' must be treated as master-only');
        }
        foreach (['app/Livewire/Wallet.php', 'public/images/terrain.webp'] as $path) {
            $this->assertFalse(MasterOnlyModules::matches($path), $path.' is innocent');
        }
    }

    public function test_the_path_matcher_catches_every_naara_widgets_shape(): void
    {
        foreach ([
            'app/Models/MerchantWidgetKey.php', 'app/Http/Middleware/EnsureMerchantWidgetKeyUsable.php',
            'app/Http/Controllers/Api/V1/MerchantWidget/CatalogueController.php', 'resources/views/merchant-widget/checkout.blade.php',
            'database/migrations/2026_10_14_100000_create_merchant_widget_tables.php', 'tests/Feature/MerchantWidgetCheckoutTest.php',
            'public/widgets/merchant-widget.js',
        ] as $path) {
            $this->assertTrue(MasterOnlyModules::matches($path), $path.' must be treated as master-only');
        }
        $this->assertFalse(MasterOnlyModules::matches('app/Livewire/MerchantDashboard.php'));
    }

    public function test_a_distributable_package_with_widget_files_cannot_be_built(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('master-only module');
        app(PackageBuilder::class)->build([
            'output_dir' => $this->dir, 'private_key' => $this->keys['private'], 'changelog' => 'x',
            'files' => [['path' => 'app/Models/MerchantWidgetKey.php', 'action' => 'add', 'content' => "<?php\n"]],
        ]);
    }

    public function test_a_distributable_package_with_n2n_files_cannot_be_built(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('master-only module');
        app(PackageBuilder::class)->build([
            'output_dir' => $this->dir, 'private_key' => $this->keys['private'], 'changelog' => 'x',
            'files' => [['path' => 'app/Livewire/N2N/Chat.php', 'action' => 'add', 'content' => "<?php\n"]],
        ]);
    }

    public function test_a_master_only_package_may_carry_n2n_but_can_never_be_published(): void
    {
        $path = app(PackageBuilder::class)->build([
            'output_dir' => $this->dir, 'private_key' => $this->keys['private'], 'changelog' => 'x', 'distribution_scope' => 'master_only',
            'files' => [['path' => 'app/Livewire/N2N/Chat.php', 'action' => 'add', 'content' => "<?php\n"]],
        ]);
        $row = app(PackagePublisher::class)->register($path, publish: true);

        $this->assertFalse((bool) $row->is_published);
        $this->assertTrue($row->isMasterOnly());
    }
}
