{{-- Naara chat inner-page navigation (MASTER-ONLY): one scrolling chip row so the settings-style pages reach each other in one tap. Skin tokens only. --}}
@php
    $tabs = array_values(array_filter([
        ['route' => 'n2n.chat', 'label' => 'Chats', 'icon' => 'message-circle'],
        ['route' => 'n2n.identity', 'label' => 'Naara ID', 'icon' => 'id-card'],
        ['route' => 'n2n.profile.settings', 'label' => 'My profile', 'icon' => 'user'],
        ['route' => 'n2n.planner', 'label' => 'Planner', 'icon' => 'calendar'],
        ['route' => 'n2n.credits', 'label' => 'Credits', 'icon' => 'wallet'],
        ['route' => 'n2n.gestures', 'label' => 'Gestures', 'icon' => 'cog'],
    ], fn ($t) => \Illuminate\Support\Facades\Route::has($t['route'])));
@endphp
<nav class="-mx-1 mb-3 flex gap-2 overflow-x-auto px-1 pb-1" aria-label="Naara chat sections" data-n2n-section-tabs>
    @foreach ($tabs as $t)
        @php($on = request()->routeIs($t['route']))
        <a href="{{ route($t['route']) }}" wire:navigate @if ($on) aria-current="page" @endif
           class="flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold {{ $on ? 'border-[rgb(var(--nx-teal))] bg-[rgb(var(--nx-teal)/0.14)] text-[rgb(var(--nx-teal-ink))]' : 'border-[rgb(var(--nx-line))] text-[rgb(var(--nx-text-2))]' }}">
            <x-nx.icon :name="$t['icon']" />{{ $t['label'] }}
        </a>
    @endforeach
</nav>
