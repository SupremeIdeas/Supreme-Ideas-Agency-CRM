<?php

namespace Tests\Feature;

use App\Models\Handbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** The draft catalogue ships as data (one json per title id), lands as drafts, groups by country series and category, and never overwrites the owner's edits. */
class HandbookCatalogueDraftsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_catalogue_files_import_as_drafts_grouped_by_series_and_category(): void
    {
        Artisan::call('handbooks:import', ['folder' => database_path('seeders/handbooks')]);
        $this->assertGreaterThanOrEqual(58, Handbook::count());
        $kenya = Handbook::where('catalogue_id', 'KEN-02')->firstOrFail();
        $this->assertSame('draft', $kenya->status);
        $this->assertSame('KE', $kenya->country_code);
        $this->assertSame('S7', $kenya->series_slot);
        $this->assertSame('The Kenya Series', $kenya->series?->name);
        $this->assertContains('Explore and travel', $kenya->categories->pluck('name')->all());
        $this->assertSame(3, Handbook::where('country_code', 'KE')->count());
    }

    public function test_a_rerun_with_missing_only_never_overwrites_an_edited_draft(): void
    {
        Artisan::call('handbooks:import', ['folder' => database_path('seeders/handbooks')]);
        $b = Handbook::where('catalogue_id', 'GHA-02')->firstOrFail();
        $b->update(['title' => 'My own title', 'list_price_usd' => 12.5]);
        $b->categories()->detach();
        Artisan::call('handbooks:import', ['folder' => database_path('seeders/handbooks'), '--missing-only' => true]);
        $b->refresh();
        $this->assertSame('My own title', $b->title);
        $this->assertSame('12.50', number_format((float) $b->list_price_usd, 2, '.', ''));
        $this->assertSame(0, $b->categories()->count());
    }
}
