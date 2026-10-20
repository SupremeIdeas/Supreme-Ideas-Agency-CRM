{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
@php $inp = 'w-full rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))] focus:border-[rgb(var(--nx-teal))] focus:ring-[rgb(var(--nx-teal))]'; @endphp
<div class="mx-auto max-w-3xl px-4 py-6 sm:px-6">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">User guides</h1>
        <p class="mt-0.5 text-sm text-[rgb(var(--nx-text-2))]">Edit the guide + agreement each audience sees in their area. HTML is sanitized on save.</p>
    </div>

    {{-- Audience tabs --}}
    <div class="mb-6 flex flex-wrap gap-2">
        @foreach ($audiences as $a)
            <button type="button" wire:click="selectAudience('{{ $a }}')"
                @class(['rounded-full px-3.5 py-1.5 text-sm font-medium transition', 'bg-[rgb(var(--nx-cta-b))] text-white' => $audience === $a, 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]' => $audience !== $a])>
                {{ \App\Support\UserGuides::label($a) }}
            </button>
        @endforeach
    </div>

    <div class="space-y-5">
        <div class="rounded-2xl border border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface))] p-5">
            <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Title</label>
            <input type="text" wire:model="title" class="{{ $inp }}">
            @error('title') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            <label class="mb-1 mt-3 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Intro</label>
            <textarea wire:model="intro" rows="2" class="{{ $inp }}"></textarea>
        </div>

        {{-- Sections --}}
        <div class="rounded-2xl border border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface))] p-5">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Sections</h2>
                <button type="button" wire:click="addSection" class="rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-3 py-1 text-xs font-semibold text-[rgb(var(--nx-teal-ink))]">+ Section</button>
            </div>
            <div class="space-y-3">
                @foreach ($sections as $i => $section)
                    <div class="rounded-xl border border-[rgb(var(--nx-line)/0.7)] p-3" wire:key="gs-{{ $i }}">
                        <div class="mb-2 flex items-center justify-between">
                            <input type="text" wire:model="sections.{{ $i }}.heading" placeholder="Heading" class="{{ $inp }} mr-2">
                            <button type="button" wire:click="removeSection({{ $i }})" class="shrink-0 text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-bad))]"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                        </div>
                        <textarea wire:model="sections.{{ $i }}.body" rows="3" placeholder="Body (HTML: p, ul/li, strong, a…)" class="{{ $inp }} font-mono text-xs"></textarea>
                        <div class="mt-2 flex items-center gap-2">
                            @if (! empty($section['image']))
                                <img src="{{ $section['image'] }}" class="h-10 w-16 rounded object-cover">
                                <button type="button" wire:click="clearSectionImage({{ $i }})" class="text-xs text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-bad))]">Remove</button>
                            @endif
                            <input type="file" wire:model="sectionImage" accept="image/*" class="block w-full text-xs text-[rgb(var(--nx-text-2))] file:mr-2 file:rounded-full file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-2 file:py-1 file:text-xs file:text-[rgb(var(--nx-teal-ink))]">
                            <button type="button" wire:click="uploadSectionImage({{ $i }})" class="shrink-0 rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-2.5 py-1 text-xs font-semibold text-[rgb(var(--nx-teal-ink))]">Set image</button>
                        </div>
                    </div>
                @endforeach
                @if (empty($sections))<p class="text-sm text-[rgb(var(--nx-text-2))]">No sections yet.</p>@endif
            </div>
        </div>

        {{-- Agreement --}}
        <div class="rounded-2xl border border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface))] p-5">
            <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Agreement &amp; policy (HTML — pricing promise, rules, one-account warning)</label>
            <textarea wire:model="agreement" rows="12" class="{{ $inp }} font-mono text-xs"></textarea>
        </div>

        <button type="button" wire:click="save" class="w-full rounded-full bg-[rgb(var(--nx-cta-b))] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))]">Save {{ \App\Support\UserGuides::label($audience) }}</button>
    </div>
</div>
