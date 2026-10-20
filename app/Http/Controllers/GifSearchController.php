<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Live GIF search for the chat composer through the admin's own GIPHY or Tenor key (Admin > Provider keys). The browser never sees the key and never talks to the
 * provider: it asks this route, which returns small, family-safe results. Without a key it answers an empty list, so the composer simply shows the built-in library.
 * Each provider's terms ask that its brand is shown next to its results, so the reply carries an `attribution`.
 */
class GifSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim(mb_substr((string) $request->query('q', ''), 0, 60));
        if ($q === '') {
            return response()->json(['items' => [], 'attribution' => null]);
        }
        $giphy = (string) config('services.giphy.api_key');
        $tenor = (string) config('services.tenor.api_key');
        if ($giphy === '' && $tenor === '') {
            return response()->json(['items' => [], 'attribution' => null]);
        }

        $result = Cache::remember('gif.search.'.md5(($giphy !== '' ? 'g' : 't').strtolower($q)), 600, fn () => $giphy !== '' ? $this->giphy($giphy, $q) : $this->tenor($tenor, $q));

        return response()->json($result);
    }

    /** @return array{items: list<array{name: string, url: string}>, attribution: string} */
    private function giphy(string $key, string $q): array
    {
        try {
            $r = Http::timeout(8)->get('https://api.giphy.com/v1/gifs/search', ['api_key' => $key, 'q' => $q, 'limit' => 18, 'rating' => 'g', 'lang' => app()->getLocale()]);
        } catch (\Throwable) {
            return ['items' => [], 'attribution' => 'GIPHY'];
        }
        $items = [];
        foreach ((array) $r->json('data', []) as $g) {
            $url = $g['images']['fixed_height_small']['url'] ?? $g['images']['fixed_height']['url'] ?? null;
            if (is_string($url) && str_starts_with($url, 'https://')) {
                $items[] = ['name' => mb_substr((string) ($g['title'] ?? $q), 0, 60) ?: $q, 'url' => $url];
            }
        }

        return ['items' => $items, 'attribution' => 'GIPHY'];
    }

    /** @return array{items: list<array{name: string, url: string}>, attribution: string} */
    private function tenor(string $key, string $q): array
    {
        try {
            $r = Http::timeout(8)->get('https://tenor.googleapis.com/v2/search', ['key' => $key, 'q' => $q, 'limit' => 18, 'contentfilter' => 'high', 'media_filter' => 'tinygif', 'locale' => app()->getLocale()]);
        } catch (\Throwable) {
            return ['items' => [], 'attribution' => 'Tenor'];
        }
        $items = [];
        foreach ((array) $r->json('results', []) as $g) {
            $url = $g['media_formats']['tinygif']['url'] ?? null;
            if (is_string($url) && str_starts_with($url, 'https://')) {
                $items[] = ['name' => mb_substr((string) ($g['content_description'] ?? $q), 0, 60) ?: $q, 'url' => $url];
            }
        }

        return ['items' => $items, 'attribution' => 'Tenor'];
    }
}
