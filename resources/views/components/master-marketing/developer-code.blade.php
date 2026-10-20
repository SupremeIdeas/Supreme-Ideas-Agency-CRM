{{-- A brand-coloured code panel for the developer docs (MASTER-ONLY). `sample` is either ['title','tabs'=>[lang=>code]] (a request in several languages, with a
     remembered language choice shared by every panel on the page) or ['title','lang','code'] (one block, e.g. a JSON response). Highlighting is done server-side
     by DeveloperCodeHighlighter, which escapes every character once. Midnight Navy panel, Deep Teal edge, Warm Gold accents; the panel is the same in light and dark mode. --}}
@props(['sample', 'host' => 'your-domain'])
@php
    use App\Support\MasterMarketing\DeveloperCodeHighlighter as Hl;
    $labels = ['curl' => 'cURL', 'php' => 'PHP', 'node' => 'Node.js', 'python' => 'Python', 'json' => 'JSON', 'html' => 'HTML'];
    $tabs = $sample['tabs'] ?? [($sample['lang'] ?? 'json') => $sample['code'] ?? ''];
    $tabs = array_map(fn ($c) => str_replace('YOUR-DOMAIN', $host, $c), $tabs);
    $langOf = fn ($k) => $k === 'node' ? 'js' : $k;
    $multi = count($tabs) > 1;
    $first = array_key_first($tabs);
    $keys = array_keys($tabs);
@endphp
<figure {{ $attributes->merge(['class' => 'dv-code']) }} data-dv-code
    x-data="{ tab: (() => { try { const t = localStorage.getItem('dvLang'); return {{ Js::from($keys) }}.includes(t) ? t : {{ Js::from($first) }}; } catch (e) { return {{ Js::from($first) }}; } })(), keys: {{ Js::from($keys) }}, copied: false,
        pick(t) { this.tab = t; try { localStorage.setItem('dvLang', t); } catch (e) {} window.dispatchEvent(new CustomEvent('dv-lang', { detail: t })); },
        copy() { const p = [...$el.querySelectorAll('pre')].find((n) => n.offsetParent !== null); if (!p) return; navigator.clipboard.writeText(p.innerText).then(() => { this.copied = true; setTimeout(() => this.copied = false, 1600); }).catch(() => {}); } }"
    x-on:dv-lang.window="if (keys.includes($event.detail)) tab = $event.detail">
    <div class="dv-code__bar">
        @if (! empty($sample['title']))<figcaption class="dv-code__title">{{ $sample['title'] }}</figcaption>@endif
        @if ($multi)
            <div class="dv-code__tabs" role="tablist" aria-label="Language">
                @foreach ($tabs as $k => $_)
                    <button type="button" role="tab" :aria-selected="tab === '{{ $k }}'" :class="tab === '{{ $k }}' ? 'is-on' : ''" x-on:click="pick('{{ $k }}')">{{ $labels[$k] ?? ucfirst($k) }}</button>
                @endforeach
            </div>
        @else
            <span class="dv-code__lang">{{ $labels[$first] ?? strtoupper($first) }}</span>
        @endif
        <button type="button" class="dv-code__copy" x-on:click="copy()" :aria-label="copied ? 'Copied' : 'Copy code'">
            <x-icon name="copy" class="h-3.5 w-3.5" /><span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
        </button>
    </div>
    @foreach ($tabs as $k => $code)
        <pre class="dv-code__pre" @if ($multi) x-show="tab === '{{ $k }}'" @if (! $loop->first) x-cloak @endif @endif tabindex="0"><code>{!! Hl::highlight($code, $langOf($k)) !!}</code></pre>
    @endforeach
</figure>
