{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-3xl">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">Numbers cards</h1>
        <p class="text-sm text-[rgb(var(--nx-text-2))]">The six landing bento cards. Edit copy, badge, image and bullets, or toggle a card off — no deploy. The layout order is fixed.</p>
    </div>

    @if ($saved)
        <div class="mb-4 rounded-lg border border-[rgb(var(--nx-ok))] bg-[rgb(var(--nx-ok)/0.12)] px-4 py-2.5 text-sm text-[rgb(var(--nx-ok))]">{{ $saved }}</div>
    @endif

    {{-- Platform-wide Contacts default view (users can still toggle their own). --}}
    <div class="mb-6 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
        <h2 class="mb-1 text-sm font-bold text-[rgb(var(--nx-text))]">Contacts default view</h2>
        <p class="mb-3 text-xs text-[rgb(var(--nx-text-2))]">The view new users see first in their contact book. Each user can still switch it themselves — this only sets the starting point.</p>
        <div class="flex items-center gap-3">
            <select wire:model="contacts_default_view" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                <option value="list">List</option>
                <option value="grid">Grid</option>
            </select>
            <button type="button" wire:click="saveContactsView" wire:loading.attr="disabled" wire:target="saveContactsView"
                    class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">Save</button>
        </div>
        @error('contacts_default_view') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
    </div>

    <div class="space-y-4">
        @foreach ($order as $key)
            @php($f = $form[$key])
            <div wire:key="card-{{ $key }}" class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <img src="{{ $f['image_path'] ?: \App\Support\NumbersBento::defaults()[$key]['image'] }}" class="h-12 w-16 shrink-0 rounded-lg object-cover">
                        <div>
                            <p class="font-semibold text-[rgb(var(--nx-text))]">{{ $f['title'] }}</p>
                            <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $key }}</p>
                        </div>
                    </div>
                    {{-- Visibility toggle --}}
                    <button type="button" wire:click="toggle('{{ $key }}')"
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition {{ $f['is_active'] ? 'bg-[rgb(var(--nx-ok)/0.16)] text-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-surface-3))] text-[rgb(var(--nx-text-2))]' }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $f['is_active'] ? 'bg-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-line-strong))]' }}"></span>
                        {{ $f['is_active'] ? 'Visible' : 'Hidden' }}
                    </button>
                </div>

                @if ($saved === $key)
                    <div class="mb-3 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] px-3 py-2 text-xs text-[rgb(var(--nx-ok))]">
                        <x-icon name="badge-check" class="h-4 w-4" /> Saved.
                    </div>
                @endif

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Badge (optional)</label>
                        <input type="text" wire:model="form.{{ $key }}.badge_label" maxlength="24" placeholder="e.g. POPULAR"
                               class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm uppercase">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Title</label>
                        <input type="text" wire:model="form.{{ $key }}.title" maxlength="60"
                               class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm">
                        @error("form.{$key}.title") <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-3">
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Subtitle</label>
                    <textarea wire:model="form.{{ $key }}.subtitle" rows="2" maxlength="500"
                              class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm"></textarea>
                    @error("form.{$key}.subtitle") <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                </div>

                @if (in_array($key, \App\Support\NumbersBento::TOGGLABLE, true))
                    <div class="mt-3">
                        <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Opens as</label>
                        <select wire:model="form.{{ $key }}.display_mode"
                                class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm sm:w-64">
                            <option value="modal">Modal (pop-up sheet)</option>
                            <option value="page">Dedicated page</option>
                        </select>
                        <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">A dedicated page replaces the bento grid with this flow full-page, like the eSIM catalogue's purchase screen — the flow itself is unchanged either way.</p>
                    </div>
                @endif

                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Bullets (one per line, max 4)</label>
                        <textarea wire:model="form.{{ $key }}.bullets" rows="3"
                                  class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm"></textarea>
                        @if ($key === 'verify')<p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">A live “+N more” is appended automatically from the service catalogue.</p>@endif
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Replace image (WebP/JPG/PNG, ≤800&nbsp;KB)</label>
                        <input type="file" wire:model="images.{{ $key }}" accept="image/webp,image/jpeg,image/png"
                               class="block w-full text-[11px] text-[rgb(var(--nx-text-2))] file:mr-2 file:rounded file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-2 file:py-1 file:text-[11px] file:font-medium file:text-[rgb(var(--nx-teal-ink))]">
                        @error("images.{$key}") <p class="mt-1 text-[11px] text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <button type="button" wire:click="save('{{ $key }}')" wire:loading.attr="disabled" wire:target="save('{{ $key }}'),images.{{ $key }}"
                            class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                        <span wire:loading.remove wire:target="save('{{ $key }}')">Save card</span>
                        <span wire:loading wire:target="save('{{ $key }}')" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
                    </button>
                </div>
            </div>
        @endforeach
    </div>
</div>
