{{-- nx:converted (skin tokens + x-nx components only) --}}
{{-- Shown instead of a NaaraBusiness Suite page when the workspace owner's Naara Pro plan does not include it (App\Http\Middleware\EnsureBusinessPlan). Never a dead end: it says what is missing, that nothing was lost, and where to fix it. --}}
@php
    $lapsed = $state === 'lapsed';
    $title = $lapsed ? 'Your NaaraBusiness Suite plan has ended' : $feature['label'].' is part of NaaraBusiness Suite';
@endphp
<x-layouts.app :title="$title" page-type="marketing" :skin="true">
    <x-nx.page class="ns-pg">
        <div class="ns-card" style="max-width:34rem;margin:40px auto;text-align:center">
            <span class="ns-tile" style="width:64px;height:64px;font-size:32px;margin:0 auto 14px"><x-nx.icon :name="$feature['icon']" /></span>
            <h1 class="ns-h1">{{ $title }}</h1>
            @if (! $isOwner)
                <p class="ns-sub" style="margin-top:10px">{{ $ownerName ?: 'Your workspace owner' }}'s plan does not include {{ $feature['label'] }} right now. Ask them to check their plan. Everything in the workspace is safe.</p>
            @elseif ($lapsed)
                <p class="ns-sub" style="margin-top:10px">Your workspace, numbers, flows and conversations are all still here, just paused. Renew a plan that includes {{ $feature['label'] }} and everything comes back exactly as you left it.</p>
            @else
                <p class="ns-sub" style="margin-top:10px">{{ $feature['blurb'] }} Pick a Naara Pro plan that includes it and you can start right away.</p>
            @endif
            @if ($isOwner)
                <a href="{{ route('brand.get-listed') }}" wire:navigate class="ns-cta ns-cta--pill" style="margin:18px auto 0;display:inline-flex">{{ $lapsed ? 'Renew a plan' : 'See plans' }}</a>
            @endif
            <p style="margin-top:14px"><a href="{{ route('dashboard') }}" wire:navigate class="ns-linkink" style="text-decoration:underline">Back to your dashboard</a></p>
        </div>
    </x-nx.page>
</x-layouts.app>
