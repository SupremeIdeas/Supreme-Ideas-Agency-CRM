{{-- One floating-nav segment. Icon-only until active/hover, when the label
     "swells" in — icon+label segment, spring-eased (Homepage floating-nav §2). --}}
<a href="{{ $href ?: '#' }}"
   @class([
       'nx-floatnav__seg group flex h-11 items-center gap-1.5 rounded-full px-3 text-sm font-medium transition-all duration-200',
       'bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300' => $isActive,
       'text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-slate-100' => ! $isActive,
   ])
   aria-label="{{ $slot->label }}" @if ($isActive) aria-current="page" @endif>
    @php $megaKey = class_exists(\App\Support\MasterMarketing\MenuIcons::class) ? \App\Support\MasterMarketing\MenuIcons::navSlotKey((string) $slot->target, (string) $slot->icon, (bool) ($uniqueTarget ?? false)) : null; @endphp
    @if ($megaKey)<x-master-marketing.menu-icon :slot-key="$megaKey" :fallback="$slot->icon" :size="24" class="shrink-0 transition-transform group-active:scale-110" />@else<x-icon name="{{ $slot->icon }}" class="h-5 w-5 shrink-0 transition-transform group-active:scale-110" />@endif
    <span @class([
        'overflow-hidden whitespace-nowrap transition-all duration-200',
        'max-w-[7rem]' => $isActive,
        'max-w-0 opacity-0 sm:max-w-[7rem] sm:opacity-100' => ! $isActive,
    ])>{{ $slot->label }}</span>
</a>
