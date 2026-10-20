{{-- Admin > Content protection (MASTER ONLY). Skin tokens only. --}}
<div class="mx-auto max-w-3xl space-y-6" data-content-lock-admin>
    <div>
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Content protection</h1>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">Make it hard to save or lift Naara's images and videos. Turn the whole lock off or on here, and choose which parts apply. It takes effect on the next page load.</p>
    </div>

    <form wire:submit="save" class="space-y-4 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <label class="flex items-start gap-3"><input type="checkbox" wire:model.live="enabled" class="mt-1 h-5 w-5 rounded"><span><span class="block text-base font-semibold text-[rgb(var(--nx-text))]">Content lock</span><span class="text-xs text-[rgb(var(--nx-text-2))]">The master switch. Off means nothing below runs.</span></span></label>

        <div class="space-y-3 border-t border-[rgb(var(--nx-line))] pt-4 @if (! $enabled) opacity-50 @endif">
            @foreach ([
                ['images', 'Protect images', 'No right-click save, no dragging an image out, no long-press save on phones.'],
                ['videos', 'Protect videos', 'No download button, no right-click menu and no picture-in-picture on any video. Self-hosted uploads are also kept private and played through short-lived signed links, so there is no file address to copy.'],
                ['keys', 'Save page and Print', 'Blocks Ctrl/Cmd+S and Ctrl/Cmd+P on public pages, and hides images and videos when someone prints.'],
                ['shield', 'Screenshot shield (strict)', 'Blurs the page while the tab or window is hidden or Print Screen is pressed. This can annoy honest visitors, so it is off unless you want it.'],
            ] as [$k, $t, $d])
                <label class="flex items-start gap-3"><input type="checkbox" wire:model="{{ $k }}" @disabled(! $enabled) class="mt-1 h-4 w-4 rounded"><span><span class="block text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $t }}</span><span class="text-xs text-[rgb(var(--nx-text-2))]">{{ $d }}</span></span></label>
            @endforeach
            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Where it applies</label>
                <select wire:model="scope" @disabled(! $enabled) class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    <option value="marketing">Public marketing pages only (recommended)</option>
                    <option value="all">Marketing pages and the signed-in app</option>
                </select>
                <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Chat photos and anything a person uploads themselves are never locked.</p>
            </div>
        </div>

        <div class="flex justify-end"><button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">Save</button></div>
    </form>

    <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] p-5 text-sm text-[rgb(var(--nx-text-2))]">
        <p class="font-semibold text-[rgb(var(--nx-text))]">What this can and cannot do</p>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-xs">
            <li>It stops the easy ways: save as, drag out, copy the video link, print and save the page.</li>
            <li>No website can stop someone taking a photo of their screen or recording it. In the Android and desktop apps, the screen is hidden from capture while a protected video plays.</li>
            <li>Images are still downloaded by the browser to be shown, so a determined person with developer tools can fetch them. That is true of every website.</li>
        </ul>
    </div>
</div>
