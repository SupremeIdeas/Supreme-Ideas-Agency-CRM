@props(['kind' => 'status', 'value' => null])
@php
    $v = (string) $value;
    // Semantic colour by state — scannable at a glance (BUILD-17 §3). Green =
    // healthy, amber = watch, red = problem, slate = inert/unknown.
    $map = match ($kind) {
        'status' => ['ok' => 'green', 'low' => 'amber', 'down' => 'red', 'configured' => 'slate', 'coming_soon' => 'slate', 'paused' => 'slate'],
        'circuit' => ['closed' => 'green', 'half_open' => 'amber', 'open' => 'red'],
        'risk' => ['low' => 'green', 'medium' => 'amber', 'high' => 'red'],
        default => [],
    };
    $tone = $map[$v] ?? 'slate';
    $classes = [
        'green' => 'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]  ',
        'amber' => 'bg-[rgb(var(--nx-warn)/0.16)] text-[rgb(var(--nx-warn))]  ',
        'red' => 'bg-[rgb(var(--nx-bad)/0.14)] text-[rgb(var(--nx-bad))]  ',
        'slate' => 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))]  ',
    ][$tone];
    $label = $v === '' ? '—' : ucfirst(str_replace('_', ' ', $v));
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold $classes"]) }}>
    <span class="h-1.5 w-1.5 rounded-full {{ ['green' => 'bg-[rgb(var(--nx-ok))]', 'amber' => 'bg-[rgb(var(--nx-warn))]', 'red' => 'bg-[rgb(var(--nx-bad))]', 'slate' => 'bg-[rgb(var(--nx-line-strong))]'][$tone] }}"></span>
    {{ $label }}
</span>
