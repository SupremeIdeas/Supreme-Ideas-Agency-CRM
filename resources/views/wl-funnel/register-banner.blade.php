{{-- White Label funnel context on the Register page (MASTER-ONLY). Only shown when this browser came from a landing-page plan button. --}}
@php($wlCode = session('wl_funnel_code'))
@if ($wlCode && class_exists(\App\Services\WhiteLabel\Funnel\WlFunnel::class))
    @php($wlInst = \App\Models\WlFunnelInstance::where('public_code', $wlCode)->first())
    @if ($wlInst)
        <div class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 text-sm shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]" data-wl-funnel-banner>
            <p class="font-semibold text-slate-900 dark:text-slate-100">You are starting your White Label {{ optional($wlInst->plan())->name ?? 'purchase' }}</p>
            <p class="mt-1 text-slate-600 dark:text-slate-300">Create your account and we pick up right where you left off. Your reference is <b>{{ $wlInst->public_code }}</b>.</p>
        </div>
    @endif
@endif
