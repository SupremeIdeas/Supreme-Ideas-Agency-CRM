<?php

namespace Tests\Feature;

use App\Livewire\Admin\EsimControlCenter;
use App\Models\EsimCountryImage;
use App\Models\EsimPlan;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Oversight blueprint Phase F — missing-images checklist + replace-on-reupload. */
class EsimMissingImagesFilterTest extends TestCase
{
    use RefreshDatabase;

    private function plan(string $iso): void
    {
        EsimPlan::create([
            'provider' => 'esimgo', 'provider_plan_id' => 'p-'.uniqid(), 'name' => 'Plan '.$iso,
            'coverage_type' => EsimPlan::COVERAGE_LOCAL, 'countries' => [$iso], 'is_active' => true,
            'data_mb' => 1024, 'validity_days' => 7, 'cost_price_usd' => 3, 'computed_retail_usd' => 9,
        ]);
    }

    public function test_the_filter_lists_only_countries_missing_an_icon_or_banner(): void
    {
        $this->seed(RoleSeeder::class);
        foreach (['NG', 'GH', 'KE'] as $iso) {
            $this->plan($iso);
        }
        EsimCountryImage::create(['country_code' => 'NG', 'icon_path' => 'https://x/i.webp', 'detail_image_path' => 'https://x/b.webp']);
        EsimCountryImage::create(['country_code' => 'GH', 'icon_path' => 'https://x/i.webp', 'detail_image_path' => null]);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $rows = collect(Livewire::actingAs($admin)->test(EsimControlCenter::class)->instance()->missingCountryImages())->keyBy('code');

        $this->assertFalse($rows->has('NG'), 'a fully-imaged country is not listed');
        $this->assertTrue($rows['GH']['missing_banner']);
        $this->assertFalse($rows['GH']['missing_icon']);
        $this->assertTrue($rows['KE']['missing_icon'] && $rows['KE']['missing_banner']);
    }
}
