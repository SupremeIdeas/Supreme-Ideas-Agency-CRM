{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
@php $inp = 'w-full rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))] focus:border-[rgb(var(--nx-teal))] focus:ring-[rgb(var(--nx-teal))]'; @endphp
<div class="mx-auto max-w-4xl px-4 py-6 sm:px-6">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">Login notices</h1>
        <p class="mt-0.5 text-sm text-[rgb(var(--nx-text-2))]">Pop-ups shown to users on login — announcements, offers, coupons, migration nudges.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,360px)]">
        {{-- Editor --}}
        <div class="rounded-2xl border border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface))] p-5">
            <h2 class="mb-3 text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $editingId ? 'Edit notice' : 'New notice' }}</h2>

            <label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Title</label>
            <input type="text" wire:model="form.title" class="{{ $inp }}">
            @error('form.title') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror

            {{-- Rich text editor (contenteditable + execCommand, no dependency) --}}
            <label class="mb-1 mt-4 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Message</label>
            <div wire:ignore x-data="nxRichEditor(@js($form['body'] ?? ''))" class="rounded-lg border border-[rgb(var(--nx-line))]">
                <div class="flex flex-wrap gap-1 border-b border-[rgb(var(--nx-line))] p-1.5">
                    <button type="button" @click="cmd('bold')" class="rounded px-2 py-1 text-sm font-bold hover:bg-[rgb(var(--nx-surface-3))]">B</button>
                    <button type="button" @click="cmd('italic')" class="rounded px-2 py-1 text-sm italic hover:bg-[rgb(var(--nx-surface-3))]">i</button>
                    <button type="button" @click="cmd('underline')" class="rounded px-2 py-1 text-sm underline hover:bg-[rgb(var(--nx-surface-3))]">U</button>
                    <button type="button" @click="format('h2')" class="rounded px-2 py-1 text-xs font-semibold hover:bg-[rgb(var(--nx-surface-3))]">H2</button>
                    <button type="button" @click="cmd('insertUnorderedList')" class="rounded px-2 py-1 text-xs hover:bg-[rgb(var(--nx-surface-3))]">• List</button>
                    <button type="button" @click="link()" class="rounded px-2 py-1 text-xs hover:bg-[rgb(var(--nx-surface-3))]">Link</button>
                </div>
                <div x-ref="editor" contenteditable="true" @input="sync()" @blur="sync()"
                     class="nx-prose min-h-[120px] px-3 py-2 text-sm text-[rgb(var(--nx-text))] focus:outline-none"></div>
            </div>
            @error('form.body') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">CTA label</label>
                    <input type="text" wire:model="form.cta_label" class="{{ $inp }}"></div>
                <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">CTA link (URL or /path)</label>
                    <input type="text" wire:model="form.cta_url" placeholder="/merchant/apply" class="{{ $inp }}"></div>
                <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Coupon code (optional)</label>
                    <input type="text" wire:model="form.coupon_code" class="{{ $inp }}"></div>
                <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Priority</label>
                    <input type="number" wire:model="form.priority" class="{{ $inp }}"></div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Audience</label>
                    <select wire:model="form.audience" class="{{ $inp }}">
                        <option value="all">All users</option>
                        <option value="new">New users</option>
                        <option value="old">Returning users</option>
                    </select></div>
                <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">"New" window (days)</label>
                    <input type="number" wire:model="form.new_days" class="{{ $inp }}"></div>
                <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Show up to (times)</label>
                    <input type="number" wire:model="form.max_views" class="{{ $inp }}"><p class="mt-0.5 text-[11px] text-[rgb(var(--nx-text-2))]">0 = unlimited</p></div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Trigger</label>
                    <select wire:model="form.trigger" class="{{ $inp }}">
                        <option value="any">Any login</option>
                        <option value="first_registration">New sign-ups only</option>
                    </select></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Start</label>
                        <input type="datetime-local" wire:model="form.starts_at" class="{{ $inp }}"></div>
                    <div><label class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">End</label>
                        <input type="datetime-local" wire:model="form.ends_at" class="{{ $inp }}"></div>
                </div>
            </div>
            @error('form.ends_at') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror

            <label class="mt-4 flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]">
                <input type="checkbox" wire:model="form.is_active" class="rounded border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-teal-ink))] focus:ring-[rgb(var(--nx-teal))]"> Active
            </label>

            <div class="mt-5 flex gap-2">
                <button type="button" wire:click="save" class="flex-1 rounded-full bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))]">Save notice</button>
                @if ($editingId)
                    <button type="button" wire:click="newNotice" class="rounded-full border border-[rgb(var(--nx-line-strong))] px-5 py-2.5 text-sm font-semibold text-[rgb(var(--nx-text-2))]">New</button>
                @endif
            </div>
        </div>

        {{-- List --}}
        <div class="space-y-2">
            <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">All notices</h2>
            @forelse ($alerts as $a)
                <div class="rounded-xl border border-[rgb(var(--nx-line)/0.7)] bg-[rgb(var(--nx-surface))] p-3" wire:key="alert-{{ $a->id }}">
                    <div class="flex items-start justify-between gap-2">
                        <button type="button" wire:click="edit({{ $a->id }})" class="min-w-0 flex-1 text-left">
                            <p class="truncate text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $a->title }}</p>
                            <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ ucfirst($a->audience) }} · {{ $a->views_count }} view{{ $a->views_count === 1 ? '' : 's' }} · {{ $a->max_views === 0 ? '∞' : $a->max_views }}x</p>
                        </button>
                        <div class="flex items-center gap-1">
                            <button type="button" wire:click="toggle({{ $a->id }})" class="rounded px-1.5 text-xs font-semibold {{ $a->is_active ? 'text-[rgb(var(--nx-ok))]' : 'text-[rgb(var(--nx-text-2))]' }}">{{ $a->is_active ? 'On' : 'Off' }}</button>
                            <button type="button" wire:click="delete({{ $a->id }})" wire:confirm="Delete this notice?" class="text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-bad))]"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-[rgb(var(--nx-text-2))]">No notices yet.</p>
            @endforelse
        </div>
    </div>

    @once
        <script>
            window.nxRichEditor = function (initial) {
                return {
                    init() {
                        this.$refs.editor.innerHTML = initial || '';
                        // Keep the editor in sync if Livewire resets the form (new/edit).
                        this.$wire.$watch('form.body', (v) => {
                            if (v !== this.$refs.editor.innerHTML) { this.$refs.editor.innerHTML = v || ''; }
                        });
                    },
                    cmd(c) { document.execCommand(c, false, null); this.sync(); this.$refs.editor.focus(); },
                    format(tag) { document.execCommand('formatBlock', false, tag); this.sync(); },
                    link() { const u = prompt('Link URL'); if (u) { document.execCommand('createLink', false, u); this.sync(); } },
                    sync() { this.$wire.set('form.body', this.$refs.editor.innerHTML, false); },
                };
            };
        </script>
    @endonce
</div>
