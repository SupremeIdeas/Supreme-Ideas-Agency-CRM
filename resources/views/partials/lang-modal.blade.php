{{-- The ONE language picker modal (opened with `open-modal` name "language"). Included by the header chip (x-lang-switcher) and by the global menu, so the
     picker exists wherever either is shown, rendered once per page. --}}
@php
    $cur = app()->getLocale();
    $opts = \App\Support\Locale::available();
@endphp
@once
    <x-ui.modal name="language" :title="__('common.choose_language')" max-width="md">
        <form method="POST" action="{{ route('locale.switch') }}" class="nx-lang">
            @csrf
            <p class="text-sm text-[rgb(var(--nx-text-2))] ">{{ __('common.language_hint') }}</p>
            <div class="mt-4 grid gap-2 sm:grid-cols-2" role="radiogroup" aria-label="{{ __('common.language') }}">
                @foreach ($opts as $code => $meta)
                    @php $on = $code === $cur; @endphp
                    <button type="submit" name="locale" value="{{ $code }}" role="radio" aria-checked="{{ $on ? 'true' : 'false' }}"
                            lang="{{ $code }}"
                            class="nx-lang__opt {{ $on ? 'is-on' : '' }} flex w-full items-center justify-between gap-3 rounded-xl border px-3.5 py-3 text-start transition {{ $on ? 'border-[rgb(var(--nx-teal))] bg-[rgb(var(--nx-cta-b)/0.1)] ' : 'border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] hover:border-[rgb(var(--nx-teal)/0.6)]  ' }}">
                        <span class="min-w-0">
                            <span class="block truncate text-[15px] font-semibold text-[rgb(var(--nx-text))] " dir="{{ $meta['rtl'] ? 'rtl' : 'ltr' }}">{{ $meta['native'] }}</span>
                            <span class="block truncate text-xs text-[rgb(var(--nx-text-2))] " dir="ltr">{{ $meta['name'] }}@if ($meta['beta'] ?? false) · <span class="font-semibold text-[rgb(var(--nx-gold-ink))] ">{{ __('common.beta') }}</span>@endif</span>
                        </span>
                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border {{ $on ? 'border-[rgb(var(--nx-teal))] bg-[rgb(var(--nx-cta-b))] text-white' : 'border-[rgb(var(--nx-line-strong))] ' }}" aria-hidden="true">
                            @if ($on)<x-icon name="check" class="h-3 w-3" />@endif
                        </span>
                    </button>
                @endforeach
            </div>
        </form>
    </x-ui.modal>
@endonce
