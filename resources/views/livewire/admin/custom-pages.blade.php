{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-5xl">
    <div class="mb-5 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Pages</h1>
            <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">Author custom-HTML pages served at <code class="rounded bg-[rgb(var(--nx-surface-3))] px-1">/p/&lt;slug&gt;</code>.</p>
        </div>
        <button type="button" wire:click="newPage"
                class="inline-flex items-center gap-1.5 rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">
            <x-icon name="file-text" class="h-4 w-4" /> New page
        </button>
    </div>

    @if ($saved)
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]">
            <x-icon name="check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_1.4fr]">
        {{-- List --}}
        <div class="space-y-2">
            @forelse ($pages as $page)
                <div class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3 shadow-sm" wire:key="pg-{{ $page->id }}">
                    <div class="flex items-center justify-between gap-2">
                        <button type="button" wire:click="edit({{ $page->id }})" class="text-left">
                            <span class="block text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $page->title }}</span>
                            <span class="block text-xs text-[rgb(var(--nx-text-2))]">/p/{{ $page->slug }}</span>
                        </button>
                        <div class="flex items-center gap-1.5">
                            @if ($page->is_published)
                                <a href="{{ url('/p/'.$page->slug) }}" target="_blank" rel="noopener" class="rounded p-1.5 text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-teal-ink))]" title="View">
                                    <x-icon name="link" class="h-4 w-4" />
                                </a>
                                <span class="rounded-full bg-[rgb(var(--nx-ok)/0.16)] px-2 py-0.5 text-[10px] font-semibold text-[rgb(var(--nx-ok))]">Live</span>
                            @else
                                <span class="rounded-full bg-[rgb(var(--nx-surface-3))] px-2 py-0.5 text-[10px] font-semibold text-[rgb(var(--nx-text-2))]">Draft</span>
                            @endif
                            <button type="button" wire:click="delete({{ $page->id }})" wire:confirm="Delete this page permanently?"
                                    class="rounded p-1.5 text-[rgb(var(--nx-bad))] hover:text-[rgb(var(--nx-bad))]" title="Delete">
                                <x-icon name="trash" class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-[rgb(var(--nx-line-strong))] p-6 text-center text-sm text-[rgb(var(--nx-text-2))]">No pages yet.</div>
            @endforelse
        </div>

        {{-- Editor --}}
        <div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $editingId ? 'Edit page' : 'New page' }}</h2>
            <div class="space-y-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Title</label>
                    <input type="text" wire:model="title" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                    @error('title') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Slug <span class="text-[rgb(var(--nx-text-2))]">(URL: /p/…)</span></label>
                    <input type="text" wire:model="slug" placeholder="auto from title" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                    @error('slug') <span class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Custom HTML</label>
                    <textarea wire:model="html" rows="12" spellcheck="false"
                              class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 font-mono text-xs text-[rgb(var(--nx-text))]"
                              placeholder="&lt;section class=&quot;text-center&quot;&gt;&#10; &lt;h1&gt;Your page&lt;/h1&gt;&#10;&lt;/section&gt;"></textarea>
                    <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Tailwind classes work. Inline &lt;script&gt; is blocked by the site security policy (visitors are protected).</p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Meta description (SEO)</label>
                    <input type="text" wire:model="meta" maxlength="200" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                </div>
                <div class="flex flex-wrap gap-4 pt-1">
                    <label class="inline-flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="isPublished" class="rounded text-[rgb(var(--nx-teal-ink))]"> Published</label>
                    <label class="inline-flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="inNav" class="rounded text-[rgb(var(--nx-teal-ink))]"> Show in menu</label>
                    <label class="inline-flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="fullWidth" class="rounded text-[rgb(var(--nx-teal-ink))]"> Full-width</label>
                </div>
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                        class="flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                    <x-icon name="check" class="h-4 w-4" /> Save page
                </button>
            </div>
        </div>
    </div>
</div>
