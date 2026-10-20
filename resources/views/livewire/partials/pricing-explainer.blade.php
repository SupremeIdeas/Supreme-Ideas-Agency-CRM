{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
{{-- AI/knowledge pricing education (Module 29). Rendered inline under a plan
     when the visitor taps "What does this mean for me?". --}}
@if (($explanations[$ref] ?? null) && $openExplainer === $ref)
    @php($ex = $explanations[$ref])
    <div class="mt-3 rounded-2xl border border-[rgb(var(--nx-teal)/0.2)] bg-[rgb(var(--nx-cta-b)/0.05)] p-4 text-left">
        <p class="text-sm leading-relaxed text-[rgb(var(--nx-text))]">{{ $ex['summary'] }}</p>
        @if (! empty($ex['tips']))
            <ul class="mt-3 space-y-1.5">
                @foreach ($ex['tips'] as $tip)
                    <li class="flex items-start gap-2 text-xs text-[rgb(var(--nx-text-2))]">
                        <x-icon name="check" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-[rgb(var(--nx-teal-ink))]" /> <span>{{ $tip }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
        <p class="mt-3 text-[10px] uppercase tracking-wide text-[rgb(var(--nx-text-2))]">
            {{ $ex['ai'] ? 'NaaraSim guide · AI-assisted' : 'NaaraSim guide' }}
        </p>
    </div>
@endif
