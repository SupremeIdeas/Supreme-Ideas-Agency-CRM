{{-- Shared avatar: a real photo when one exists, else an initials circle —
     used wherever a person (Nia, a staff member, an online agent) needs a
     face. $online is a tri-state: null hides the presence dot entirely. --}}
@props(['src' => null, 'name' => '', 'size' => 'h-9 w-9', 'online' => null, 'onDark' => false])
<span class="relative inline-flex shrink-0 {{ $size }}">
    @if ($src)
        <img src="{{ $src }}" alt="{{ $name }}" class="{{ $size }} rounded-full object-cover">
    @else
        <span class="flex {{ $size }} items-center justify-center rounded-full text-sm font-bold {{ $onDark ? 'bg-white/20 text-white' : 'bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300' }}">
            {{ $name !== '' ? mb_strtoupper(mb_substr($name, 0, 1)) : '?' }}
        </span>
    @endif
    @if (! is_null($online))
        <span class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-white dark:border-[#0D1B2A] {{ $online ? 'bg-emerald-500' : 'bg-slate-400' }}"
              aria-label="{{ $online ? 'Online' : 'Offline' }}"></span>
    @endif
</span>
