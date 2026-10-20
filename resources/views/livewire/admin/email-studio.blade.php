{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-6xl px-4 py-6" wire:key="email-studio">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Email Studio</h1>
            <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">Edit your transactional emails and see exactly what a recipient gets. Blank fields keep the built-in default.</p>
        </div>
        <a href="{{ route('admin.email-broadcast') }}" wire:navigate class="inline-flex items-center gap-2 rounded-xl border border-[rgb(var(--nx-line))] px-4 py-2 text-sm font-semibold text-[rgb(var(--nx-text))] hover:bg-[rgb(var(--nx-surface-2))]">
            <x-icon name="send" class="h-4 w-4" /> Send a broadcast
        </a>
    </div>

    <div class="grid gap-6 lg:grid-cols-[16rem_1fr]">
        {{-- Template list --}}
        <div class="space-y-1">
            <button type="button" wire:click="loadTemplate('global')"
                    class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-sm font-medium {{ $key === 'global' ? 'bg-[rgb(var(--nx-cta-b))] text-white' : 'text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]' }}">
                <x-icon name="image" class="h-4 w-4" /> Global styling
            </button>
            @foreach ($templates as $tk => $tpl)
                <button type="button" wire:click="loadTemplate('{{ $tk }}')"
                        class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-sm font-medium {{ $key === $tk ? 'bg-[rgb(var(--nx-cta-b))] text-white' : 'text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]' }}">
                    <x-icon name="mail" class="h-4 w-4" /> {{ $tpl['label'] }}
                </button>
            @endforeach
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            {{-- Editor --}}
            <div class="space-y-4 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
                @if ($key === 'global')
                    <p class="text-sm text-[rgb(var(--nx-text-2))]">Accent colour applies to every email's header and buttons, unless a specific template sets its own.</p>
                @else
                    <label class="block"><span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Subject line</span>
                        <input type="text" wire:model.live.debounce.400ms="form.subject" placeholder="{{ $templates[$key]['subject'] ?? '' }}" class="w-full rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                        @error('form.subject') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                    </label>
                    <label class="block"><span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Heading (the email's page title, shown in some inboxes)</span>
                        <input type="text" wire:model.live.debounce.400ms="form.heading" placeholder="{{ $templates[$key]['heading'] ?? '' }}" class="w-full rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                        @error('form.heading') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                    </label>
                    <label class="block"><span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Intro line (added above the body)</span>
                        <textarea rows="3" wire:model.live.debounce.400ms="form.intro" class="w-full rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm"></textarea>
                        @error('form.intro') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                    </label>
                    <label class="block"><span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Button text</span>
                        <input type="text" wire:model.live.debounce.400ms="form.button_text" class="w-full rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                    </label>
                @endif
                <label class="flex items-center gap-3 text-sm font-medium text-[rgb(var(--nx-text))]">
                    Accent colour
                    <input type="color" wire:model.live="form.accent_color" class="h-9 w-12 cursor-pointer rounded border border-[rgb(var(--nx-line))] bg-transparent">
                    <button type="button" wire:click="$set('form.accent_color', '')" class="text-xs font-normal text-[rgb(var(--nx-text-2))] underline">Reset</button>
                    @error('form.accent_color') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                </label>

                <div class="flex items-center gap-3 pt-2">
                    <button type="button" wire:click="save" wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-xl bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">Save</span><span wire:loading wire:target="save">Saving…</span>
                    </button>
                    @if ($saved === $key)<span class="text-sm font-medium text-[rgb(var(--nx-ok))]">Saved.</span>@endif
                </div>
            </div>

            {{-- Live preview --}}
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Live preview (sample data)</p>
                <div class="overflow-hidden rounded-2xl border border-[rgb(var(--nx-line))]">
                    <iframe title="Email preview" class="h-[32rem] w-full bg-[rgb(var(--nx-surface))]" srcdoc="{{ $previewHtml }}"></iframe>
                </div>
            </div>
        </div>
    </div>
</div>
