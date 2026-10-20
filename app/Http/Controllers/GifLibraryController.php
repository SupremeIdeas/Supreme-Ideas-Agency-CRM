<?php

namespace App\Http\Controllers;

use App\Services\Gifs\GifLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The composer's GIF and sticker shelves, as JSON (signed-in people only). `?category=` filters a shelf, `?q=` searches names. */
class GifLibraryController extends Controller
{
    public function __invoke(Request $request, GifLibrary $library): JsonResponse
    {
        $category = $request->query('category');
        $q = $request->query('q');
        $surface = GifLibrary::validSurface(is_string($request->query('surface')) ? $request->query('surface') : null);

        return response()->json([
            'categories' => $library->categories(true, $surface),
            'items' => $library->listing(is_string($category) ? $category : null, is_string($q) ? mb_substr($q, 0, 60) : null, 600, $surface),
        ])->header('Cache-Control', 'private, no-cache');
    }
}
