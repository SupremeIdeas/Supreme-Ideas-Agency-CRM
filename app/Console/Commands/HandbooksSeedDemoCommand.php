<?php

namespace App\Console\Commands;

use App\Models\Handbook;
use App\Models\HandbookCategory;
use App\Models\HandbookMood;
use App\Services\Handbooks\HandbookCatalogueService;
use App\Services\Handbooks\HandbookStorage;
use App\Support\Handbooks\DemoEpubBuilder;
use Illuminate\Console\Command;

/** handbooks:seed-demo (MASTER-ONLY): two clearly-labelled sample handbooks with real covers and an EPUB with pictures, so the store, reader and unlock flows can be seen end to end. Safe to re-run; delete the DEMO titles from Admin > Handbooks before launch. */
class HandbooksSeedDemoCommand extends Command
{
    protected $signature = 'handbooks:seed-demo {--draft : Leave the titles unpublished}';

    protected $description = 'Create two sample handbooks (Ghana and UK) with covers and an illustrated EPUB';

    public function handle(HandbookCatalogueService $svc, HandbookStorage $storage): int
    {
        $dir = database_path('seeders/handbook-demo');
        $cat = HandbookCategory::query()->firstOrCreate(['slug' => 'moving-abroad'], ['name' => 'Moving abroad']);
        $stu = HandbookCategory::query()->firstOrCreate(['slug' => 'student-life'], ['name' => 'Student life']);
        $mood = HandbookMood::query()->firstOrCreate(['slug' => 'quick-start'], ['name' => 'Quick-start']);
        $para = fn (string $t) => [$t, 'Sample text for previewing the reader: headings, paragraphs and pictures all reflow to fit your screen, in any text size and theme.', 'Use the chapter list to jump to a section, drop a bookmark, and your place is saved for next time, even offline.'];
        $books = [
            ['id' => 'DEMO-GH-01', 'cc' => 'GH', 'slug' => 'demo-moving-to-ghana', 'title' => 'Moving to Ghana', 'sub' => 'Visas, housing, money and everyday life (demo handbook)', 'price' => 9.99, 'cover' => 'ghana-cover.jpg', 'cats' => [$cat],
                'desc' => "A sample handbook that shows what a Naara Travel Handbook feels like to read: practical chapters, clear pictures and a chapter list you can jump around in.\n\nThis is demo content for previewing the store. It is not real advice.",
                'chapters' => [
                    ['title' => 'Before you go', 'paras' => $para('Start with a short list of what has to happen before you fly: documents, money, a place for the first nights and a way to get online.'), 'figure' => ['file' => 'fig-steps.jpg', 'caption' => 'A first-30-days checklist.', 'alt' => 'A five-step checklist for your first month abroad']],
                    ['title' => 'Visas and permits', 'paras' => $para('Which visa fits your plan, how long it takes and what to carry on arrival. Check the official immigration site for the current rules.')],
                    ['title' => 'Housing', 'paras' => $para('Short stays first, then a longer lease. What to ask, what to inspect and how deposits usually work.')],
                    ['title' => 'Money and banking', 'paras' => $para('Cards, cash and mobile money, and how to avoid paying more in fees than you need to.'), 'figure' => ['file' => 'fig-budget.jpg', 'caption' => 'A typical monthly budget split.', 'alt' => 'A bar showing how a monthly budget is divided between rent, food, transport and phone']],
                    ['title' => 'Getting around', 'paras' => $para('Walking, shared transport and ride apps, with tips for staying safe and not overpaying.'), 'figure' => ['file' => 'fig-map.jpg', 'caption' => 'From the airport to home.', 'alt' => 'A simple route map from the airport to home']],
                    ['title' => 'Staying connected', 'paras' => $para('Get a local eSIM before you land so maps, rides and messages work from the airport.')],
                ]],
            ['id' => 'DEMO-GB-01', 'cc' => 'GB', 'slug' => 'demo-studying-in-the-uk', 'title' => 'Studying in the UK', 'sub' => 'The student visa, your first term and living on a budget (demo handbook)', 'price' => 12.99, 'cover' => 'uk-cover.jpg', 'cats' => [$stu, $cat],
                'desc' => "A second sample handbook with a different cover and chapters, for previewing the store shelves and the reader.\n\nThis is demo content. It is not real advice.",
                'chapters' => [
                    ['title' => 'Is the UK right for you?', 'paras' => $para('Courses, costs and what a typical year looks like for an international student.'), 'figure' => ['file' => 'fig-budget.jpg', 'caption' => 'Where a typical month goes.', 'alt' => 'A bar showing a monthly student budget']],
                    ['title' => 'The student visa', 'paras' => $para('The steps from offer letter to visa decision, and the papers you will be asked for.')],
                    ['title' => 'Accommodation', 'paras' => $para('Halls, shared houses and private rentals, and how to avoid common rental scams.')],
                    ['title' => 'Budgeting and part-time work', 'paras' => $para('Planning your money, what student work rules usually allow and how to open a bank account.'), 'figure' => ['file' => 'fig-steps.jpg', 'caption' => 'Settling in checklist.', 'alt' => 'A checklist for settling in']],
                    ['title' => 'Health and safety', 'paras' => $para('Registering with a doctor, staying well in your first winter and who to call in an emergency.')],
                    ['title' => 'Your first term', 'paras' => $para('Making friends, finding your way around campus and keeping in touch with home.'), 'figure' => ['file' => 'fig-map.jpg', 'caption' => 'Getting around the city.', 'alt' => 'A simple map of city stops']],
                ]],
        ];
        foreach ($books as $i => $d) {
            $b = Handbook::query()->where('catalogue_id', $d['id'])->first() ?? new Handbook(['catalogue_id' => $d['id'], 'status' => Handbook::DRAFT]);
            $b->fill(['slug' => $d['slug'], 'title' => $d['title'], 'subtitle' => $d['sub'], 'description' => $d['desc'], 'country_code' => $d['cc'], 'series_slot' => 'S1', 'list_price_usd' => $d['price'], 'last_verified_on' => now()->toDateString(), 'is_featured' => true, 'sort_order' => $i])->save();
            if ($b->epub_path && $b->cover_path) {   // already seeded: leave the files (and any live edition) alone
                $this->line($d['id'].': already present');

                continue;
            }
            $epub = DemoEpubBuilder::build($d['title'], $d['id'], $d['chapters'], $dir);
            try {
                $r = $svc->attachEpub($b, $epub);
            } finally {
                @unlink($epub);
            }
            if (! $r['ok']) {
                $this->error($d['id'].': '.implode(' ', $r['errors']));

                continue;
            }
            $b = $b->fresh();
            $b->forceFill(['cover_path' => $storage->put($b, $dir.'/'.$d['cover'], 'cover')])->save();   // stored straight: the demo art is 1000x1500, below the upload minimum for real covers
            $b->categories()->sync(array_map(fn ($c) => $c->id, $d['cats']));
            $b->moods()->sync([$mood->id]);
            if (! $this->option('draft')) {
                $p = $svc->publish($b->fresh());
                $this->line($d['id'].': '.($p['ok'] ? 'published' : 'draft ('.implode(' ', $p['problems']).')'));
            } else {
                $this->line($d['id'].': draft');
            }
        }

        return self::SUCCESS;
    }
}
