{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
@php
    $in = 'w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]';
    $lb = 'mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]';
    $card = 'rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 sm:p-5';
@endphp
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Naara Chat status</h1>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">24-hour text, photo, video and voice status for Naara Pro members. Every post and its stored file is deleted after 24 hours. Brand-profile Highlights in the Updates tab keep working whatever you choose here.</p>
    </div>

    <form wire:submit="save" class="{{ $card }} space-y-4">
        <div>
            <label class="{{ $lb }}" for="st-mode">Status</label>
            <select id="st-mode" wire:model="mode" class="{{ $in }}">
                <option value="auto">Automatic: on while cloud storage (R2 or Wasabi) is configured</option>
                <option value="on">On, even without cloud storage</option>
                <option value="off">Off</option>
            </select>
            @if ($mode === 'on' && ! $cloud)
                <p class="mt-2 rounded-lg bg-[rgb(var(--nx-warn)/0.15)] p-2 text-xs text-[rgb(var(--nx-text))]">No cloud storage is configured, so photos and voice notes will be kept on this server's disk for 24 hours with tight size limits (2 MB photo, 1 MB voice) and video is refused. Text status uses no storage at all.</p>
            @endif
        </div>
        <div>
            <label class="{{ $lb }}" for="st-limit">Default status updates per member per 24 hours (1 to 100)</label>
            <input id="st-limit" type="number" wire:model="dailyLimit" min="1" max="100" class="{{ $in }}">
            @error('dailyLimit')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
            <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">The highest-priced active brand plan is unlimited automatically. Any plan can override this with a "status_per_day" capability (-1 = unlimited).</p>
        </div>
        <label class="flex items-start gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="mentions" class="mt-1"><span><b>Allow tagging people in a status</b><span class="block text-xs text-[rgb(var(--nx-text-2))]">Members can tag up to 5 connections; each tagged member gets one notification. Only people who can see the status can be tagged.</span></span></label>
        <label class="flex items-start gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="camera" class="mt-1"><span><b>Offer the in-app camera</b><span class="block text-xs text-[rgb(var(--nx-text-2))]">Take a photo or record a short video inside Add status. Members whose browser has no camera still get the gallery picker.</span></span></label>
        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="ns-cta ns-cta--pill"><span wire:loading.remove wire:target="save">Save</span><span wire:loading wire:target="save">Saving...</span></button>
    </form>

    <section class="{{ $card }}">
        <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Right now</h2>
        <dl class="mt-2 grid gap-2 text-sm text-[rgb(var(--nx-text))] sm:grid-cols-2">
            <div><dt class="text-xs text-[rgb(var(--nx-text-2))]">Status is</dt><dd>{{ $effective ? 'On' : 'Off' }}</dd></div>
            <div><dt class="text-xs text-[rgb(var(--nx-text-2))]">Cloud storage</dt><dd>{{ $cloud ? 'Configured' : 'Not configured' }}</dd></div>
            <div><dt class="text-xs text-[rgb(var(--nx-text-2))]">Allowed kinds</dt><dd>{{ $kinds === [] ? 'None' : implode(', ', $kinds) }}</dd></div>
            <div><dt class="text-xs text-[rgb(var(--nx-text-2))]">Live posts</dt><dd>{{ number_format($live) }}</dd></div>
            <div><dt class="text-xs text-[rgb(var(--nx-text-2))]">Files awaiting a retry</dt><dd>{{ number_format($stuck) }}</dd></div>
        </dl>
    </section>
</div>
