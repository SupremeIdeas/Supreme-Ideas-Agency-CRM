{{-- Naara Pro: every capability against every plan, from ONE catalogue (MASTER-ONLY). The same table on Get Listed, the Business page, the dashboard and the setup wizard. --}}
@props(['plans', 'current' => null, 'compact' => false])
@php($m = \App\Support\Business\NaaraProCatalogue::matrix($plans))
@if ($plans->isNotEmpty())
<div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white dark:border-white/10 dark:bg-white/5" data-pro-compare role="region" aria-label="Naara Pro plan comparison" tabindex="0">
    <table class="w-full border-collapse text-left text-sm" style="min-width: {{ 200 + $plans->count() * 110 }}px">
        <thead>
            <tr>
                <th class="sticky left-0 z-10 bg-white p-3 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:bg-[#10192b] dark:text-slate-400" scope="col">Naara Pro includes</th>
                @foreach ($plans as $p)
                    <th scope="col" class="p-3 text-center align-bottom {{ $current && (int) $current === (int) $p->id ? 'bg-primary/10 dark:bg-accent/10' : '' }}">
                        <span class="block text-sm font-bold text-slate-900 dark:text-white">{{ $p->name }}</span>
                        <span class="block text-xs font-medium text-slate-500 dark:text-slate-400">${{ number_format((float) $p->price_usd_per_month, 0) }}/mo</span>
                        @if ($current && (int) $current === (int) $p->id)<span class="mt-1 inline-block rounded-full bg-primary px-2 py-0.5 text-[10px] font-bold text-white dark:bg-accent dark:text-[#0D1B2A]">Your plan</span>@endif
                    </th>
                @endforeach
            </tr>
        </thead>
        @foreach ($m['groups'] as $g)
            <tbody>
                <tr><th colspan="{{ $plans->count() + 1 }}" scope="colgroup" class="bg-slate-50 px-3 py-2 text-xs font-bold uppercase tracking-wider text-primary dark:bg-white/5 dark:text-accent">{{ $g['label'] }}</th></tr>
                @foreach ($g['rows'] as $row)
                    <tr class="border-t border-slate-100 dark:border-white/5" data-pro-cap="{{ $row['key'] }}">
                        <th scope="row" class="sticky left-0 z-10 bg-white p-3 text-left font-medium text-slate-800 dark:bg-[#10192b] dark:text-slate-100">
                            {{ $row['label'] }}
                            @unless ($compact)<span class="mt-0.5 block text-xs font-normal text-slate-500 dark:text-slate-400">{{ $row['blurb'] }}</span>@endunless
                        </th>
                        @foreach ($plans as $p)
                            @php($c = $row['cells'][$p->id])
                            <td class="p-3 text-center {{ $current && (int) $current === (int) $p->id ? 'bg-primary/5 dark:bg-accent/5' : '' }}">
                                @if ($c['on'])
                                    @if ($c['text'])<span class="text-[13px] font-semibold text-slate-800 dark:text-slate-100">{{ $c['text'] }}</span>
                                    @else<x-icon name="check" class="mx-auto h-4 w-4 text-primary dark:text-accent" /><span class="sr-only">Included</span>@endif
                                @else
                                    <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">&mdash;</span><span class="sr-only">Not included</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        @endforeach
    </table>
</div>
@endif
