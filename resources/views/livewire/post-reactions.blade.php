{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
{{-- Post reactions (batch 2 §5). Neutral surface tokens so it reads as a quiet
     utility strip, not a coloured call-to-action; the active reaction lifts to
     the brand teal. Every button shows a loading state (platform rule). --}}
<div class="flex flex-wrap items-center gap-2" wire:loading.class="opacity-70">
    @foreach ($types as $key => [$label, $icon])
        @php($count = $counts[$key] ?? 0)
        @php($active = $mine === $key)
        <button type="button"
                wire:click="react('{{ $key }}')"
                wire:loading.attr="disabled"
                aria-pressed="{{ $active ? 'true' : 'false' }}"
                title="{{ $label }}"
                @class([
                    'group inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition',
                    'border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] text-[rgb(var(--nx-text-2))] hover:border-[rgb(var(--nx-teal)/0.4)] hover:text-[rgb(var(--nx-teal-ink))]' => ! $active,
                    'border-[rgb(var(--nx-teal)/0.5)] bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))]' => $active,
                ])>
            <x-icon :name="$icon" class="h-4 w-4 transition group-hover:scale-110" />
            <span>{{ $label }}</span>
            @if ($count > 0)
                <span class="tabular-nums opacity-80">{{ $count }}</span>
            @endif
        </button>
    @endforeach

    @if ($total > 0)
        <span class="ml-1 text-xs text-[rgb(var(--nx-text-2))]">{{ $total }} {{ \Illuminate\Support\Str::plural('reaction', $total) }}</span>
    @endif
</div>
