{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-5xl px-4 py-6" wire:key="notification-tones">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Sounds &amp; ringtones</h1>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">
            The tone library members choose from in Account &rarr; Sounds: ringtones, alert tones, message tones and beeps. Upload as many as you like per category and mark one as the default for each use;
            anyone who hasn't picked their own hears the default. Custom sound plays while the app is open on screen. A closed tab uses the device's own
            notification sound, which no website controls, so nothing here promises otherwise.
        </p>
    </div>

    @if ($saved)<p class="mb-4 rounded-xl bg-[rgb(var(--nx-ok)/0.12)] px-4 py-2 text-sm font-medium text-[rgb(var(--nx-ok))]" role="status">{{ $saved }}</p>@endif

    {{-- Add a tone --}}
    <form wire:submit="upload" class="mb-8 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-3 text-sm font-semibold text-[rgb(var(--nx-text))]">Add a tone</h2>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <label class="block text-xs font-medium text-[rgb(var(--nx-text-2))]">Category
                <select wire:model.live="category" class="mt-1 w-full rounded-lg border border-[rgb(var(--nx-line-strong))] text-sm">
                    @foreach ($categories as $key => $def)<option value="{{ $key }}">{{ $def['label'] }}</option>@endforeach
                </select>
            </label>
            <label class="block text-xs font-medium text-[rgb(var(--nx-text-2))]">Used for
                <select wire:model="slot" class="mt-1 w-full rounded-lg border border-[rgb(var(--nx-line-strong))] text-sm">
                    @foreach ($categories[$category]['slots'] as $key => $name)<option value="{{ $key }}">{{ $name }}</option>@endforeach
                </select>
            </label>
            <label class="block text-xs font-medium text-[rgb(var(--nx-text-2))]">Name
                <input type="text" wire:model="label" maxlength="80" placeholder="e.g. Golden Bell" class="mt-1 w-full rounded-lg border border-[rgb(var(--nx-line-strong))] text-sm">
            </label>
            <label class="block text-xs font-medium text-[rgb(var(--nx-text-2))]">Credit (optional)
                <input type="text" wire:model="credit" maxlength="160" placeholder="e.g. Artist name · Album" class="mt-1 w-full rounded-lg border border-[rgb(var(--nx-line-strong))] text-sm">
            </label>
            <label class="block text-xs font-medium text-[rgb(var(--nx-text-2))]">Audio file
                <input type="file" wire:model="file" accept=".wav,.mp3,.ogg,.m4a,.webm,audio/*" class="mt-1 block w-full text-sm text-[rgb(var(--nx-text-2))] file:mr-3 file:rounded-lg file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-[rgb(var(--nx-teal-ink))]">
            </label>
        </div>
        <p class="mt-2 text-[11px] text-[rgb(var(--nx-text-2))]">{{ $categories[$category]['hint'] }} WAV, MP3, OGG, M4A or WebM, up to {{ $maxKb }} KB (the file's content is checked, not its name). Keep alert and beep tones short (about two seconds or less); a ringtone can be a clip of up to about a minute.</p>
        @error('file')<p class="mt-2 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
        @error('label')<p class="mt-2 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
        @if ($error)<p class="mt-2 text-xs text-[rgb(var(--nx-bad))]" role="alert">{{ $error }}</p>@endif
        <button type="submit" wire:loading.attr="disabled" wire:target="upload,file" class="nx-btn nx-btn--primary mt-4 !py-2 !text-sm">
            <span wire:loading.remove wire:target="upload">Add tone</span><span wire:loading wire:target="upload">Adding…</span>
        </button>
    </form>

    {{-- The library --}}
    @foreach ($categories as $cat => $def)
        <section class="mb-6">
            <h2 class="text-base font-bold text-[rgb(var(--nx-text))]">{{ $def['label'] }}</h2>
            <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">{{ $def['hint'] }}</p>
            @foreach ($def['slots'] as $slotKey => $slotName)
                @php($list = $tones[$cat.'.'.$slotKey] ?? collect())
                <div class="mb-3 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
                    <p class="border-b border-[rgb(var(--nx-line))] px-4 py-2 text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">{{ $slotName }} <span class="font-normal normal-case">({{ $list->count() }})</span></p>
                    @forelse ($list as $t)
                        <div class="flex flex-wrap items-center gap-3 border-b border-[rgb(var(--nx-line))] px-4 py-2.5 last:border-0" wire:key="tone-{{ $t->id }}">
                            <button type="button" onclick="window.NaaraSound && window.NaaraSound.preview('{{ $t->url() }}')" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-cta-b)/0.2)]" aria-label="Play {{ $t->label }}">
                                <x-icon name="play" class="h-4 w-4" />
                            </button>
                            <span class="min-w-0 flex-1 truncate text-sm font-medium text-[rgb(var(--nx-text))]">{{ $t->label }}
                                @if ($t->is_builtin)<span class="ml-1 text-[10px] font-normal text-[rgb(var(--nx-text-2))]">built-in</span>@endif
                                @if ($t->credit)<small class="block truncate text-[11px] font-normal text-[rgb(var(--nx-text-2))]">{{ $t->credit }}</small>@endif
                            </span>
                            <span class="text-[11px] text-[rgb(var(--nx-text-2))]">{{ number_format($t->size_bytes / 1024, 0) }} KB @if ($t->duration_ms) · {{ number_format($t->duration_ms / 1000, 1) }}s @endif</span>
                            @if ($t->is_default)
                                <span class="rounded-full bg-[rgb(var(--nx-ok)/0.16)] px-2.5 py-0.5 text-[11px] font-semibold text-[rgb(var(--nx-ok))]">Default</span>
                            @else
                                <button type="button" wire:click="makeDefault({{ $t->id }})" class="rounded-full border border-[rgb(var(--nx-line))] px-2.5 py-0.5 text-[11px] font-semibold text-[rgb(var(--nx-text-2))] hover:border-[rgb(var(--nx-teal)/0.4)] hover:text-[rgb(var(--nx-teal-ink))]">Make default</button>
                            @endif
                            <button type="button" wire:click="remove({{ $t->id }})" wire:confirm="Delete &quot;{{ $t->label }}&quot;? Members who picked it fall back to the default." class="text-[11px] font-semibold text-[rgb(var(--nx-bad))] hover:underline">Delete</button>
                        </div>
                    @empty
                        <p class="px-4 py-4 text-sm text-[rgb(var(--nx-text-2))]">No tones yet. Members hear nothing for this event until you add one.</p>
                    @endforelse
                </div>
            @endforeach
        </section>
    @endforeach
</div>
