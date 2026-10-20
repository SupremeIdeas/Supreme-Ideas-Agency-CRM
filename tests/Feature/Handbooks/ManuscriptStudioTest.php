<?php

namespace Tests\Feature\Handbooks;

use App\Livewire\Admin\Handbooks\HandbookManuscript;
use App\Models\Handbook;
use App\Models\User;
use App\Services\Handbooks\HandbookStorage;
use App\Services\Handbooks\Manuscript\DocxToEpub;
use App\Services\Handbooks\Manuscript\EpubImageOptimizer;
use App\Services\Handbooks\Manuscript\EpubPackage;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

/** Manuscript studio (MASTER-ONLY): Word to EPUB, the safe preview, the batch picture fit, and saving onto a handbook. */
class ManuscriptStudioTest extends TestCase
{
    use RefreshDatabase;

    private function bigPng(int $w = 2400, int $h = 1200): string
    {
        $im = imagecreatetruecolor($w, $h);
        for ($i = 0; $i < 3000; $i++) {
            imageline($im, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), imagecolorallocate($im, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        }
        ob_start();
        imagejpeg($im, null, 95);

        return (string) ob_get_clean();
    }

    private function docx(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tdx').'.docx';
        $z = new ZipArchive;
        $z->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $z->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Default Extension="jpg" ContentType="image/jpeg"/></Types>');
        $z->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId5" Type="x" Target="media/image1.jpg"/><Relationship Id="rId6" Type="x" TargetMode="External" Target="https://naara.app/help"/></Relationships>');
        $z->addFromString('word/media/image1.jpg', $this->bigPng());
        $z->addFromString('docProps/core.xml', '<?xml version="1.0"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>Sample Handbook</dc:title></cp:coreProperties>');
        $w = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"';
        $z->addFromString('word/document.xml', '<?xml version="1.0"?><w:document '.$w.'><w:body>'
            .'<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Before you land</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t xml:space="preserve">Pack </w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>light</w:t></w:r><w:r><w:t>.</w:t></w:r><w:hyperlink r:id="rId6"><w:r><w:t> Help</w:t></w:r></w:hyperlink></w:p>'
            .'<w:p><w:r><w:drawing><wp:inline><wp:docPr id="1" name="p" descr="A big photo"/><a:graphic><a:graphicData><a:blip r:embed="rId5"/></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>'
            .'<w:p><w:pPr><w:pStyle w:val="ListParagraph"/><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>Passport</w:t></w:r></w:p>'
            .'<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>First week</w:t></w:r></w:p><w:p><w:r><w:t>Get a SIM.</w:t></w:r></w:p>'
            .'</w:body></w:document>');
        $z->close();

        return $path;
    }

    public function test_a_word_file_becomes_a_valid_epub_with_chapters_and_pictures(): void
    {
        $out = tempnam(sys_get_temp_dir(), 'tep').'.epub';
        $r = app(DocxToEpub::class)->convert($this->docx(), $out, 'Sample Handbook');
        $this->assertTrue($r['ok'], implode(' ', $r['errors']));
        $this->assertSame(2, $r['chapters']);
        $this->assertSame(1, $r['images']);
        $insp = app(\App\Services\Handbooks\EpubInspector::class)->inspect($out);
        $this->assertTrue($insp['ok'], implode(' ', $insp['errors']));
        $this->assertTrue($insp['has_imprint']);
        $pkg = new EpubPackage($out);
        $titles = array_column($pkg->chapters(), 'title');
        $this->assertContains('Before you land', $titles);
        $html = $pkg->chapterHtml(1, fn ($h) => '/a?p='.$h);
        $this->assertStringContainsString('<strong>light</strong>', $html);
        $this->assertStringContainsString('<li>Passport</li>', $html);
        $this->assertStringContainsString('/a?p=images/img001.jpg', $html);
    }

    public function test_the_batch_fit_scales_wide_pictures_and_leaves_the_book_valid(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'tes').'.epub';
        app(DocxToEpub::class)->convert($this->docx(), $src, 'Sample Handbook');
        $before = (new EpubPackage($src))->images()[0];
        $this->assertSame(2400, $before['width']);
        $dst = tempnam(sys_get_temp_dir(), 'ted').'.epub';
        $res = app(EpubImageOptimizer::class)->optimise($src, $dst, 1000, 80, 100);
        $after = (new EpubPackage($dst))->images()[0];
        $this->assertSame(1000, $after['width']);
        $this->assertSame(500, $after['height']);
        $this->assertLessThan($before['bytes'], $after['bytes']);
        $this->assertGreaterThan(0, $res['saved']);
        $this->assertTrue(app(\App\Services\Handbooks\EpubInspector::class)->inspect($dst)['ok']);
    }

    public function test_the_preview_frame_is_safe_and_admin_only(): void
    {
        $this->seed(RoleSeeder::class);
        Storage::fake(HandbookStorage::disk());
        $src = tempnam(sys_get_temp_dir(), 'tep').'.epub';
        app(DocxToEpub::class)->convert($this->docx(), $src, 'Sample Handbook');
        $token = bin2hex(random_bytes(16));
        app(\App\Services\Handbooks\Manuscript\ManuscriptStaging::class)->replace($token, $src);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');
        $member = User::factory()->create(['is_active' => true]);
        $this->actingAs($member)->get(route('admin.handbooks.manuscript.chapter', ['token' => $token, 'n' => 0]))->assertNotFound();   // the admin area is a plain 404 to everyone else
        $this->actingAs($admin)->get(route('admin.handbooks.manuscript.chapter', ['token' => $token, 'n' => 2]))->assertOk()->assertSee('Get a SIM.')->assertHeader('X-Robots-Tag', 'noindex');
        $this->actingAs($admin)->get(route('admin.handbooks.manuscript.asset', ['token' => $token]).'?p=images/img001.jpg')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($admin)->get(route('admin.handbooks.manuscript.asset', ['token' => $token]).'?p=../../etc/passwd')->assertNotFound();
    }

    public function test_the_studio_uploads_flags_fits_and_saves_onto_the_handbook(): void
    {
        $this->seed(RoleSeeder::class);
        Storage::fake(HandbookStorage::disk());
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');
        $book = Handbook::create(['title' => 'Sample Handbook', 'slug' => 'sample', 'catalogue_id' => 'TST-01', 'status' => Handbook::DRAFT, 'list_price_usd' => 5]);
        $file = UploadedFile::fake()->createWithContent('draft.docx', (string) file_get_contents($this->docx()));
        $c = Livewire::actingAs($admin)->test(HandbookManuscript::class, ['handbook' => $book->id])
            ->set('manuscript', $file)->assertSeeHtml('data-preview-frame')->assertSeeHtml('data-image-row')->assertSee('Too big');
        $this->assertNotNull($c->get('token'));
        $c->set('maxWidth', 1200)->set('flagKb', 5000)->call('fitImages')->assertSeeHtml('data-fit-result')->assertSee('Fits');
        $c->call('saveManuscript')->assertSet('token', null);
        $book->refresh();
        $this->assertNotNull($book->epub_path);
        $this->assertSame('draft', $book->status);
        $this->assertNotEmpty($book->toc);
    }
}
