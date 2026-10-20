<?php

namespace App\Http\Controllers\N2N;

use App\Http\Controllers\Controller;
use App\Models\BrandPartner;
use App\Models\N2N\BrandAlbum;
use App\Services\N2N\Brands\LinkValidator;
use Illuminate\Contracts\View\View;

/** Public album / smart-link page with Open Graph tags (N2N Batch 8, MASTER-ONLY). Hidden or unlisted brands answer the same 404 as a missing page. */
class AlbumController extends Controller
{
    public function show(int $brand, string $slug): View
    {
        $b = BrandPartner::listed()->find($brand);
        $album = $b ? BrandAlbum::query()->where('brand_partner_id', $b->id)->where('slug', $slug)->live()->first() : null;
        abort_if($b === null || $album === null, 404);

        $links = $album->links->filter(fn ($l) => isset(LinkValidator::platforms()[$l->platform]));

        return view('n2n.album', ['brand' => $b, 'album' => $album, 'links' => $links, 'platforms' => LinkValidator::platforms()]);
    }
}
