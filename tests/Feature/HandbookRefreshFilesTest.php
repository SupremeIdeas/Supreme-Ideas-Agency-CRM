<?php

namespace Tests\Feature;

use App\Models\Handbook;
use App\Models\HandbookEdition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MakesEpubs;
use Tests\TestCase;

/** `handbooks:import --refresh-files`: a pack that ships a different EPUB updates a draft in place and gives a published title a new, unpublished edition; a re-run never stacks duplicates. */
class HandbookRefreshFilesTest extends TestCase
{
    use MakesEpubs;
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.disks.wasabi.key' => null]);
        Storage::fake(\App\Services\Handbooks\HandbookStorage::disk());
        $this->dir = sys_get_temp_dir().'/hbpack'.uniqid();
        mkdir($this->dir);
        file_put_contents("{$this->dir}/TST-01.json", json_encode(['title' => 'Test book', 'series_slot' => 'S1', 'country_code' => 'GB', 'list_price_usd' => 5]));
        copy($this->makeEpub(), "{$this->dir}/TST-01.epub");
    }

    private function run_(array $extra = []): void
    {
        Artisan::call('handbooks:import', ['folder' => $this->dir, '--missing-only' => true] + $extra);
    }

    private function differentEpub(): void
    {
        $other = $this->makeEpub(['ncx' => true]);
        copy($other, "{$this->dir}/TST-01.epub");
    }

    public function test_a_draft_takes_the_new_file_directly_and_a_rerun_does_nothing_more(): void
    {
        $this->run_();
        $first = Handbook::where('catalogue_id', 'TST-01')->firstOrFail()->epub_path;
        $this->assertNotEmpty($first);

        $this->run_(['--refresh-files' => true]);                       // identical file: untouched
        $this->assertSame($first, Handbook::where('catalogue_id', 'TST-01')->firstOrFail()->epub_path);

        $this->differentEpub();
        $this->run_(['--refresh-files' => true]);
        $second = Handbook::where('catalogue_id', 'TST-01')->firstOrFail()->epub_path;
        $this->assertNotSame($first, $second);

        $this->run_(['--refresh-files' => true]);                       // same new file again: no churn
        $this->assertSame($second, Handbook::where('catalogue_id', 'TST-01')->firstOrFail()->epub_path);
    }

    public function test_a_published_title_gets_one_unpublished_edition_not_a_silent_swap(): void
    {
        $this->run_();
        $b = Handbook::where('catalogue_id', 'TST-01')->firstOrFail();
        $b->forceFill(['status' => Handbook::PUBLISHED, 'published_at' => now()])->save();
        $live = $b->epub_path;

        $this->differentEpub();
        $this->run_(['--refresh-files' => true]);
        $this->run_(['--refresh-files' => true]);

        $this->assertSame($live, $b->fresh()->epub_path, 'readers keep the current edition');
        $this->assertSame(1, HandbookEdition::where('handbook_id', $b->id)->where('edition', '>', 1)->whereNull('published_at')->count());
    }

    public function test_without_the_flag_an_existing_title_is_left_alone(): void
    {
        $this->run_();
        $first = Handbook::where('catalogue_id', 'TST-01')->firstOrFail()->epub_path;
        $this->differentEpub();
        $this->run_();
        $this->assertSame($first, Handbook::where('catalogue_id', 'TST-01')->firstOrFail()->epub_path);
    }
}
