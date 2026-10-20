{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
@php $inp = 'w-full rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))] focus:border-[rgb(var(--nx-teal))] focus:ring-[rgb(var(--nx-teal))]'; @endphp
<div class="mx-auto max-w-3xl px-4 py-6 sm:px-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">Status incidents</h1>
            <p class="mt-0.5 text-sm text-[rgb(var(--nx-text-2))]">Post incidents to the public <a href="{{ route('status') }}" target="_blank" class="text-[rgb(var(--nx-teal-ink))] hover:underline">status page</a>.</p>
        </div>
        <span class="rounded-full bg-[rgb(var(--nx-surface-3))] px-3 py-1 text-xs font-semibold text-[rgb(var(--nx-text-2))]">{{ $subscriberCount }} subscriber{{ $subscriberCount === 1 ? '' : 's' }}</span>
    </div>

    {{-- New incident --}}
    <div class="mb-8 rounded-2xl border border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-3 text-sm font-semibold text-[rgb(var(--nx-text))]">Post a new incident</h2>
        <input type="text" wire:model="title" placeholder="Incident title" class="{{ $inp }}">
        @error('title') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <select wire:model="component" class="{{ $inp }}">
                <option value="">Platform-wide</option>
                @foreach ($components as $c)<option value="{{ $c['key'] }}">{{ $c['label'] }}</option>@endforeach
            </select>
            <select wire:model="impact" class="{{ $inp }}">
                <option value="minor">Minor (degraded)</option>
                <option value="major">Major (partial outage)</option>
                <option value="critical">Critical (major outage)</option>
                <option value="maintenance">Maintenance</option>
            </select>
        </div>
        <textarea wire:model="body" rows="3" placeholder="What's happening?" class="{{ $inp }} mt-3"></textarea>
        @error('body') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
        <button type="button" wire:click="create" class="mt-3 rounded-full bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))]">Post incident</button>
    </div>

    {{-- Existing incidents --}}
    @foreach ($incidents as $incident)
        <div class="mb-4 rounded-2xl border border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface))] p-5" wire:key="inc-{{ $incident->id }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-semibold text-[rgb(var(--nx-text))]">{{ $incident->title }}</h3>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $incident->isResolved() ? 'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-warn)/0.16)] text-[rgb(var(--nx-warn))]' }}">{{ ucfirst($incident->status) }}</span>
            </div>
            <div class="mt-3 space-y-1.5 border-l-2 border-[rgb(var(--nx-line))] pl-3 text-sm">
                @foreach ($incident->updates as $u)
                    <div><span class="text-xs font-semibold uppercase text-[rgb(var(--nx-teal-ink))]">{{ ucfirst($u->status) }}</span> <span class="text-[rgb(var(--nx-text-2))]">— {{ $u->body }}</span></div>
                @endforeach
            </div>
            @unless ($incident->isResolved())
                <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                    <select wire:model="update.{{ $incident->id }}.status" class="{{ $inp }} sm:max-w-[160px]">
                        <option value="identified">Identified</option>
                        <option value="monitoring">Monitoring</option>
                        <option value="resolved">Resolved</option>
                    </select>
                    <input type="text" wire:model="update.{{ $incident->id }}.body" placeholder="Update message" class="{{ $inp }}">
                    <button type="button" wire:click="postUpdate({{ $incident->id }})" class="shrink-0 rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-4 py-2 text-sm font-semibold text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-cta-b)/0.2)]">Post</button>
                </div>
                @error('update.'.$incident->id.'.body') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            @endunless
        </div>
    @endforeach
</div>
