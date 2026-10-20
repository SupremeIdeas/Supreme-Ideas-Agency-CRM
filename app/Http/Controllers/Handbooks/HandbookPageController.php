<?php

namespace App\Http\Controllers\Handbooks;

use App\Http\Controllers\Controller;
use App\Models\Handbook;
use App\Models\HandbookSeries;
use App\Services\Handbooks\HandbookAccess;

/** Public handbook pages (MASTER-ONLY): the book page and a country series page. Indexable, with schema.org Book markup; buying needs sign-in. */
class HandbookPageController extends Controller
{
    public function show(string $slug)
    {
        if (! \App\Support\Handbooks\HandbookSettings::get('store_enabled')) {
            return redirect()->route('handbooks.store');
        }
        $b = Handbook::query()->published()->where('slug', $slug)->with(['series', 'categories', 'moods', 'links', 'audiobook'])->firstOrFail();
        $siblings = $b->series_id ? Handbook::query()->published()->where('series_id', $b->series_id)->whereKeyNot($b->id)->orderBy('series_slot')->get() : collect();

        return view('handbooks.show', ['b' => $b, 'siblings' => $siblings, 'offers' => app(HandbookAccess::class)->offers(auth()->user(), $b), 'disclaimer' => (string) config('handbooks.disclaimer')]);
    }

    public function series(int $seriesId)
    {
        $s = HandbookSeries::query()->findOrFail($seriesId);
        $books = Handbook::query()->published()->where('series_id', $s->id)->orderBy('series_slot')->get();
        abort_if($books->isEmpty(), 404);

        return view('handbooks.series', ['series' => $s, 'books' => $books]);
    }
}
