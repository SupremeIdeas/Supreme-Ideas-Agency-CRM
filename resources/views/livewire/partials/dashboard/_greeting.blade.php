{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
{{-- Greeting block (Theme Batch 2 §2): the assistant's welcome as a chat message, on the skin system. --}}
@php($gAvatar = \App\Support\SupportSettings::avatar())
@php($gName = \App\Support\SupportSettings::name())
@php($greetingOn = \App\Support\SupportSettings::greetingEnabled())
@php($greetingMode = \App\Support\SupportSettings::greetingMode())

@if ($greetingOn)
    @php($greetKey = 'nx_greet_'.now()->format('Ymd'))
    @if ($greetingMode === 'popup')
        <div x-data="{ show: false }"
             x-init="$nextTick(() => { show = localStorage.getItem('{{ $greetKey }}') !== '1'; })"
             x-show="show" x-cloak x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-4 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
             class="nx-fab fixed bottom-24 left-4 right-4 z-40 mx-auto max-w-sm sm:left-6 sm:right-auto lg:bottom-6">
            <div class="ns-app ns-greet ns-greet--popup">
                @include('partials.greeting-bubble', ['onDismiss' => "@click=\"show = false; localStorage.setItem('{$greetKey}', '1')\""])
            </div>
        </div>
    @else
        <div x-data="{ show: true }"
             x-init="$nextTick(() => { show = localStorage.getItem('{{ $greetKey }}') !== '1'; })"
             x-show="show" x-cloak class="ns-greet" style="margin-top:20px">
            @include('partials.greeting-bubble', ['onDismiss' => "@click=\"show = false; localStorage.setItem('{$greetKey}', '1')\""])
        </div>
    @endif
@endif
