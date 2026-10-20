{{-- Chat Composer Pro (<naara-composer>) — the ONE composer for every chat/message surface. Server-renders the config + translated labels;
     the client module (resources/js/composer) is code-split and loads only on pages that include this. `wire:ignore` because the
     element owns its own DOM and must survive Livewire morphs.

     Hosts answer the `cc:transmit` event (Livewire surfaces: `$wire.upload()` + their existing action — no second transport) or set
     `endpoint` to use the built-in HTTP path. Docs: docs/chat-composer-plan.md. --}}
@props([
    'mode' => 'chat',                              // chat | field
    'features' => ['emoji', 'attach', 'voice'],    // emoji attach voice gif schedule hints snippets captions
    'attachKinds' => ['media', 'camera', 'document'],
    'accept' => [],
    'placeholder' => null,
    'rows' => 1,
    'maxFiles' => 10,
    'maxFileMb' => 25,
    'maxChars' => 0,
    'undoMs' => 3000,
    'convoId' => 'default',
    'surface' => '',                                // where this composer sits (config gif_library.surfaces): decides which GIF/sticker shelves it offers
    'endpoint' => '',
    'bare' => false,
    'micPrimer' => true,
    'labels' => [],
    'extra' => [],
])
@php
    $config = array_merge([
        'mode' => $mode,
        'features' => array_values($features),
        'attachKinds' => array_values($attachKinds),
        'accept' => (object) $accept,
        'rows' => $rows,
        'maxFiles' => $maxFiles,
        'maxFileMb' => $maxFileMb,
        'maxChars' => $maxChars,
        'undoMs' => $undoMs,
        'convoId' => (string) $convoId,
        'scope' => (string) (auth()->id() ?? ''),
        'endpoint' => $endpoint,
        'bare' => $bare,
        'micPrimer' => $micPrimer,
        'lang' => app()->getLocale(),
        'placeholder' => $placeholder ?? '',
        'labels' => (object) array_merge((array) __('composer'), $labels),
        // The shared GIF and sticker library, and live search when the admin added a GIPHY / Tenor key (both only where the routes exist and the person is signed in).
        'gifLibraryEndpoint' => (in_array('gif', $features, true) && auth()->check() && \Illuminate\Support\Facades\Route::has('gifs.library')) ? route('gifs.library', array_filter(['surface' => \App\Services\Gifs\GifLibrary::validSurface($surface)]), false) : '',
        'gifSearchEndpoint' => (in_array('gif', $features, true) && auth()->check() && \Illuminate\Support\Facades\Route::has('gifs.search') && (filled(config('services.giphy.api_key')) || filled(config('services.tenor.api_key')))) ? route('gifs.search', [], false) : '',
    ], $extra);
@endphp
<naara-composer wire:ignore data-config="{{ json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}" {{ $attributes }}></naara-composer>
