{{-- One slot in the Numbers section bottom nav (Numbers overhaul §2). Mirrors
     the global bottom-nav-item, plus an unread badge for Messages. --}}
@php($on = $isActive($item['route']))
<a href="{{ route($item['route']) }}" wire:navigate @if ($on) aria-current="page" @endif
   class="nx-tab relative flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium transition {{ $on ? 'is-on ns-cta' : 'text-slate-400 dark:text-slate-500' }}">
    <span class="relative">
        @if (class_exists(\App\Support\MasterMarketing\MenuIcons::class))<x-master-marketing.menu-icon :route="$item['route']" surface="app" :fallback="$item['icon']" :size="24" />@else<x-icon :name="$item['icon']" class="h-5 w-5" />@endif
        @if (($item['badge'] ?? 0) > 0)
            <span class="absolute -right-2 -top-1.5 min-w-[1rem] rounded-full bg-primary px-1 text-center text-[9px] font-bold leading-4 text-white">{{ $item['badge'] > 9 ? '9+' : $item['badge'] }}</span>
        @endif
    </span>
    {{ $item['label'] }}
</a>
