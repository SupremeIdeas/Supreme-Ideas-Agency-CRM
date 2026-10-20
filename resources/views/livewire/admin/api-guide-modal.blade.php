{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div>
    {{-- Uses the shared modal engine (blueprint Section 31) — focus-trap, ESC,
         backdrop + ARIA come from <x-ui.modal>; this only supplies content. --}}
    <x-ui.modal wire="open" :title="$title" max-width="2xl">
        @if ($content)
            <dl class="space-y-4 text-sm">
                <div>
                    <dt class="font-semibold text-[rgb(var(--nx-text-2))]">Config key</dt>
                    <dd class="mt-1 rounded-lg bg-[rgb(var(--nx-surface-3))] px-3 py-2 font-mono text-[rgb(var(--nx-text))]">{{ $content['config_key'] }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-[rgb(var(--nx-text-2))]">Where to get it</dt>
                    <dd class="mt-1 text-[rgb(var(--nx-text))]">{{ $content['where'] }}</dd>
                </div>
                <div class="flex gap-8">
                    <div>
                        <dt class="font-semibold text-[rgb(var(--nx-text-2))]">Format</dt>
                        <dd class="mt-1 text-[rgb(var(--nx-text))]">{{ $content['format'] }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-[rgb(var(--nx-text-2))]">Required</dt>
                        <dd class="mt-1">
                            <span @class([
                                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold',
                                'bg-[rgb(var(--nx-warn)/0.16)] text-[rgb(var(--nx-warn))]' => $content['required'],
                                'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))]' => ! $content['required'],
                            ])>
                                {{ $content['required'] ? 'Required' : 'Optional' }}
                            </span>
                        </dd>
                    </div>
                </div>
            </dl>
        @endif
    </x-ui.modal>
</div>
