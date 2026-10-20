{{-- nx:converted (skin tokens; admin tool) --}}
@php($input = 'rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-2 py-1.5 text-sm text-[rgb(var(--nx-text))]')
<div class="mx-auto max-w-4xl">
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">NaaraBrain ideas library</h1>
            <p class="text-sm text-[rgb(var(--nx-text-2))]">What members see to get ideas: the centre line (changes every visit), the quick questions, and the capability list.</p>
        </div>
        <a href="{{ route('admin.brain') }}" wire:navigate class="text-sm font-semibold text-[rgb(var(--nx-teal-ink))] underline">Back to NaaraBrain</a>
    </div>

    <div class="mb-4 flex flex-wrap gap-2" role="tablist">
        @foreach ([\App\Models\Brain\BrainPrompt::TAGLINE => 'Centre lines', \App\Models\Brain\BrainPrompt::SUGGESTION => 'Quick questions', \App\Models\Brain\BrainPrompt::CAPABILITY => 'Capabilities'] as $k => $label)
            <button type="button" role="tab" aria-selected="{{ $kind === $k ? 'true' : 'false' }}" wire:click="setKind('{{ $k }}')"
                    class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $kind === $k ? 'bg-[rgb(var(--nx-cta-b))] text-white' : 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text))]' }}">
                {{ $label }} <span class="opacity-80">({{ (int) ($counts[$k]->on_n ?? 0) }}/{{ (int) ($counts[$k]->n ?? 0) }})</span></button>
        @endforeach
    </div>

    @if ($kind !== \App\Models\Brain\BrainPrompt::TAGLINE)
        <div class="mb-3"><label class="text-xs text-[rgb(var(--nx-text-2))]">Area <select wire:model.live="pack" class="{{ $input }} ms-1"><option value="">All</option>@foreach ($packs as $pk => $pn)<option value="{{ $pk }}">{{ $pn }}</option>@endforeach</select></label></div>
    @endif

    <form wire:submit="add" class="mb-5 grid gap-2 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 sm:grid-cols-[1fr_auto]">
        <input type="text" wire:model="newText" maxlength="240" placeholder="{{ $kind === 'capability' ? 'Capability title' : ($kind === 'suggestion' ? 'A question members can ask' : 'A centre line') }}" class="{{ $input }}" aria-label="New line">
        <button type="submit" wire:loading.attr="disabled" wire:target="add" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">Add</button>
        @if ($kind === 'capability')<input type="text" wire:model="newDetail" maxlength="280" placeholder="One line on what it does" class="{{ $input }} sm:col-span-2" aria-label="Detail">@endif
        @if ($kind !== 'tagline')
            <div class="flex flex-wrap items-center gap-3 sm:col-span-2">
                <label class="text-xs text-[rgb(var(--nx-text-2))]">Area <select wire:model="newPack" class="{{ $input }} ms-1">@foreach ($packs as $pk => $pn)<option value="{{ $pk }}">{{ $pn }}</option>@endforeach</select></label>
                <label class="flex items-center gap-1.5 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="newLive" class="h-4 w-4"> Works today (untick to show "Soon")</label>
            </div>
        @endif
        @error('newText')<p class="text-xs text-[rgb(var(--nx-bad))] sm:col-span-2">{{ $message }}</p>@enderror
    </form>

    <ul class="divide-y divide-[rgb(var(--nx-line))] rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
        @forelse ($rows as $r)
            <li class="flex flex-wrap items-start gap-3 p-3" wire:key="p-{{ $r->id }}">
                <div class="min-w-0 flex-1">
                    @if ($editId === $r->id)
                        <form wire:submit="saveEdit" class="flex gap-2"><input type="text" wire:model="editText" maxlength="240" class="{{ $input }} flex-1" aria-label="Edit line"><button class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-1.5 text-xs font-semibold text-white">Save</button></form>
                    @else
                        <p class="text-sm {{ $r->active ? 'text-[rgb(var(--nx-text))]' : 'text-[rgb(var(--nx-text-2))] line-through' }}">{{ $r->text }}</p>
                        @if ($r->detail)<p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $r->detail }}</p>@endif
                        @if ($kind !== 'tagline')<p class="mt-0.5 text-[11px] text-[rgb(var(--nx-text-2))]">{{ $packs[$r->pack] ?? $r->pack }} - {{ $r->live ? 'works today' : 'Soon' }}</p>@endif
                    @endif
                </div>
                <div class="flex flex-none items-center gap-1.5 text-xs">
                    <button type="button" wire:click="toggle({{ $r->id }}, 'active')" class="rounded-full bg-[rgb(var(--nx-surface-3))] px-2.5 py-1 font-semibold text-[rgb(var(--nx-text))]">{{ $r->active ? 'On' : 'Off' }}</button>
                    @if ($kind !== 'tagline')<button type="button" wire:click="toggle({{ $r->id }}, 'live')" class="rounded-full bg-[rgb(var(--nx-surface-3))] px-2.5 py-1 font-semibold text-[rgb(var(--nx-text))]">{{ $r->live ? 'Live' : 'Soon' }}</button>@endif
                    <button type="button" wire:click="startEdit({{ $r->id }})" class="rounded-full bg-[rgb(var(--nx-surface-3))] px-2.5 py-1 font-semibold text-[rgb(var(--nx-text))]">Edit</button>
                    <button type="button" wire:click="delete({{ $r->id }})" wire:confirm="Delete this line?" class="rounded-full px-2.5 py-1 font-semibold text-[rgb(var(--nx-bad))]">Delete</button>
                </div>
            </li>
        @empty
            <li class="p-4 text-sm text-[rgb(var(--nx-text-2))]">Nothing here yet.</li>
        @endforelse
    </ul>
</div>
