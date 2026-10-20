{{-- nx:converted (skin tokens only) --}}
@php
    $inp = 'w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]';
    $btn = 'inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold disabled:opacity-60';
@endphp
<div class="mx-auto max-w-5xl space-y-4 p-4 pb-48 lg:pb-28">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-[rgb(var(--nx-text))]">GIF library</h1>
            <p class="text-sm text-[rgb(var(--nx-text-2))]">The GIFs and stickers every chat composer offers (Naara chat, support, the Business inbox, Messages). Make your own categories, then add stickers from files, a web address, or by turning a video into a GIF right here. Whatever you save shows in chat straight away.</p>
        </div>
        <button type="button" wire:click="resync" wire:loading.attr="disabled" class="{{ $btn }} border border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-text))]">
            <x-nx.icon name="refresh" class="h-4 w-4" /> Register shipped GIFs
        </button>
    </div>

    <p class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-3 text-xs text-[rgb(var(--nx-text-2))]">
        The shipped set is original artwork made for Naara, free to use. Add only GIFs you have the right to use: memes and film clips are usually copyrighted, and you are responsible for what you add.
        @if ($searchOn) Live search through your GIPHY or Tenor key is on. @else For live search across millions of GIFs, add a GIPHY or Tenor key in Provider keys (section "Chat GIF search"). @endif
    </p>

    <div role="tablist" aria-label="GIF categories" class="flex flex-wrap gap-1.5">
        @foreach ($categories as $c)
            <button type="button" role="tab" aria-selected="{{ $category === $c->slug ? 'true' : 'false' }}" wire:click="setCategory('{{ $c->slug }}')" wire:key="tab-{{ $c->id }}"
                class="rounded-full border px-3 py-1.5 text-sm font-semibold {{ $category === $c->slug ? 'border-transparent bg-[rgb(var(--nx-cta-b))] text-white' : 'border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-text))]' }} {{ $c->is_active ? '' : 'opacity-60' }}">
                {{ $c->label() }} <span>{{ $counts[$c->slug] ?? 0 }}</span>@unless ($c->is_active) <span class="text-[10px] font-normal">(hidden)</span>@endunless
            </button>
        @endforeach
    </div>

    @if ($notice)<p role="status" class="text-sm font-semibold text-[rgb(var(--nx-ok))]">{{ $notice }}</p>@endif
    @foreach ($problems as $p)<p role="alert" class="text-sm font-semibold text-[rgb(var(--nx-bad))]">{{ $p }}</p>@endforeach

    <details class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4" @if ($editing || $deleting) open @endif>
        <summary class="cursor-pointer text-base font-bold text-[rgb(var(--nx-text))]">Manage categories</summary>
        <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">A category is a tab in the chat's GIF panel. Choose which chats show it: for example a "Business" category only in the Business inbox. A category with no GIFs, or switched off, is not shown to members.</p>

        <ul class="mt-3 divide-y divide-[rgb(var(--nx-line))]">
            @foreach ($categories as $c)
                <li class="py-2.5" wire:key="cat-{{ $c->id }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="min-w-0 flex-1 text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $c->label() }}
                            <span class="ms-1 text-xs font-normal text-[rgb(var(--nx-text-2))]">{{ $counts[$c->slug] ?? 0 }} GIF(s) · {{ $c->surfaces ? implode(', ', array_map(fn ($k) => $surfaces[$k] ?? $k, $c->surfaces)) : 'every chat' }}@unless ($c->is_active) · hidden @endunless</span>
                        </span>
                        <button type="button" wire:click="moveCategory({{ $c->id }}, 'up')" @disabled($loop->first) aria-label="Move {{ $c->label() }} up" class="{{ $btn }} border border-[rgb(var(--nx-line-strong))] !px-2 !py-1 text-xs text-[rgb(var(--nx-text))]">Up</button>
                        <button type="button" wire:click="moveCategory({{ $c->id }}, 'down')" @disabled($loop->last) aria-label="Move {{ $c->label() }} down" class="{{ $btn }} border border-[rgb(var(--nx-line-strong))] !px-2 !py-1 text-xs text-[rgb(var(--nx-text))]">Down</button>
                        <button type="button" wire:click="editCategory({{ $c->id }})" class="{{ $btn }} border border-[rgb(var(--nx-line-strong))] !px-2 !py-1 text-xs text-[rgb(var(--nx-text))]">Edit</button>
                        <button type="button" wire:click="askDelete({{ $c->id }})" class="{{ $btn }} border border-[rgb(var(--nx-bad))] !px-2 !py-1 text-xs text-[rgb(var(--nx-bad))]">Delete</button>
                    </div>

                    @if ($editing === $c->id)
                        <form wire:submit="saveCategory" class="mt-3 space-y-3 rounded-xl border border-[rgb(var(--nx-line))] p-3">
                            <label class="block text-xs font-semibold text-[rgb(var(--nx-text))]" for="ec-name">Name</label>
                            <input id="ec-name" type="text" wire:model="editName" maxlength="40" class="{{ $inp }}">
                            <fieldset>
                                <legend class="text-xs font-semibold text-[rgb(var(--nx-text))]">Show this category in</legend>
                                <div class="mt-1 grid gap-1 sm:grid-cols-2">
                                    @foreach ($surfaces as $key => $label)
                                        <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" value="{{ $key }}" wire:model="editSurfaces" class="rounded"> {{ $label }}</label>
                                    @endforeach
                                </div>
                            </fieldset>
                            <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" wire:model="editActive" class="rounded"> Visible to members</label>
                            <div class="flex gap-2">
                                <button type="submit" class="{{ $btn }} bg-[rgb(var(--nx-cta-b))] text-white" wire:loading.attr="disabled" wire:target="saveCategory"><span wire:loading.remove wire:target="saveCategory">Save</span><span wire:loading wire:target="saveCategory">Saving...</span></button>
                                <button type="button" wire:click="$set('editing', null)" class="{{ $btn }} border border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-text))]">Cancel</button>
                            </div>
                        </form>
                    @endif

                    @if ($deleting === $c->id)
                        <div class="mt-3 space-y-2 rounded-xl border border-[rgb(var(--nx-bad))] p-3">
                            <p class="text-sm text-[rgb(var(--nx-text))]">Delete “{{ $c->label() }}”? It has {{ $counts[$c->slug] ?? 0 }} GIF(s). Choose where they go, or remove them with the category.</p>
                            <label class="block text-xs font-semibold text-[rgb(var(--nx-text))]" for="dc-move">GIFs in this category</label>
                            <select id="dc-move" wire:model="moveTo" class="{{ $inp }}">
                                <option value="">Remove them too</option>
                                @foreach ($categories as $o)@if ($o->id !== $c->id)<option value="{{ $o->slug }}">Move to {{ $o->label() }}</option>@endif @endforeach
                            </select>
                            <div class="flex gap-2">
                                <button type="button" wire:click="deleteCategory" wire:loading.attr="disabled" wire:target="deleteCategory" class="{{ $btn }} bg-[rgb(var(--nx-bad))] text-white"><span wire:loading.remove wire:target="deleteCategory">Delete category</span><span wire:loading wire:target="deleteCategory">Deleting...</span></button>
                                <button type="button" wire:click="$set('deleting', null)" class="{{ $btn }} border border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-text))]">Keep it</button>
                            </div>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>

        <form wire:submit="createCategory" class="mt-4 space-y-3 border-t border-[rgb(var(--nx-line))] pt-4">
            <h3 class="text-sm font-bold text-[rgb(var(--nx-text))]">New category</h3>
            <label class="block text-xs font-semibold text-[rgb(var(--nx-text))]" for="nc-name">Name</label>
            <input id="nc-name" type="text" wire:model="newName" maxlength="40" placeholder="Football fever" class="{{ $inp }}">
            <fieldset>
                <legend class="text-xs font-semibold text-[rgb(var(--nx-text))]">Show it in</legend>
                <div class="mt-1 grid gap-1 sm:grid-cols-2">
                    @foreach ($surfaces as $key => $label)
                        <label class="flex items-center gap-2 text-sm text-[rgb(var(--nx-text))]"><input type="checkbox" value="{{ $key }}" wire:model="newSurfaces" class="rounded"> {{ $label }}</label>
                    @endforeach
                </div>
            </fieldset>
            <button type="submit" class="{{ $btn }} bg-[rgb(var(--nx-cta-b))] text-white" wire:loading.attr="disabled" wire:target="createCategory" ><span wire:loading.remove wire:target="createCategory">Create category</span><span wire:loading wire:target="createCategory">Creating...</span></button>
        </form>
    </details>

    <section class="grid gap-4 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 md:grid-cols-2" aria-labelledby="gl-add">
        <h2 id="gl-add" class="text-base font-bold text-[rgb(var(--nx-text))] md:col-span-2">Add to {{ $categories->firstWhere('slug', $category)?->label() }}</h2>
        <form wire:submit="addFiles" class="space-y-2">
            <label class="block text-sm font-semibold text-[rgb(var(--nx-text))]" for="gl-files">From files</label>
            <input id="gl-files" type="file" wire:model="files" accept="image/gif" multiple class="{{ $inp }}">
            <div wire:loading wire:target="files" class="text-xs text-[rgb(var(--nx-text-2))]">Uploading...</div>
            <button type="submit" class="{{ $btn }} bg-[rgb(var(--nx-cta-b))] text-white" wire:loading.attr="disabled" wire:target="addFiles,files" @disabled(count($files) === 0)>
                <span wire:loading.remove wire:target="addFiles">Add {{ count($files) ?: '' }} file{{ count($files) === 1 ? '' : 's' }}</span><span wire:loading wire:target="addFiles">Adding...</span>
            </button>
        </form>
        <form wire:submit="addUrl" class="space-y-2">
            <label class="block text-sm font-semibold text-[rgb(var(--nx-text))]" for="gl-url">From a web address</label>
            <input id="gl-url" type="url" wire:model="url" placeholder="https://example.com/thank-you.gif" class="{{ $inp }}">
            <button type="submit" class="{{ $btn }} bg-[rgb(var(--nx-cta-b))] text-white" wire:loading.attr="disabled" wire:target="addUrl" @disabled(trim($url) === '')>
                <span wire:loading.remove wire:target="addUrl">Import</span><span wire:loading wire:target="addUrl">Fetching...</span>
            </button>
        </form>
        <div class="md:col-span-2">
            <label class="block text-sm font-semibold text-[rgb(var(--nx-text))]" for="gl-name">Name (optional, for a single GIF)</label>
            <input id="gl-name" type="text" wire:model="name" maxlength="120" placeholder="Thank you dance" class="{{ $inp }} mt-1">
        </div>
        @error('files.*')<p role="alert" class="md:col-span-2 text-sm font-semibold text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
    </section>

    <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4" aria-labelledby="gl-make"
        x-data="{ busy: false, fail: '' }"
        x-on:gifmaker-made="busy = true; fail = ''; const f = new File([$event.detail.blob], 'made.gif', { type: 'image/gif' }); const nm = $event.detail.name; $wire.upload('made', f, () => { $wire.saveMade(nm).then(() => { busy = false; }); }, () => { busy = false; fail = 'The upload did not finish. Try again.'; })">
        <h2 id="gl-make" class="text-base font-bold text-[rgb(var(--nx-text))]">Make a GIF from a video</h2>
        <p class="mt-0.5 text-xs text-[rgb(var(--nx-text-2))]">The same studio members use in chat. Choose a video, drag the handles to pick the moment (up to 8 seconds), press Save, and it is added to <span class="font-semibold">{{ $categories->firstWhere('slug', $category)?->label() }}</span>. Pick another category tab above first to put it elsewhere. The video never leaves your browser; only the finished GIF is uploaded.</p>
        <div class="mt-3" wire:ignore>
            <naara-gif-maker data-config="{{ json_encode(['labels' => $makerLabels, 'maxBytes' => $maxBytes, 'hint' => 'Choose a video from your device. A short clip with a clear subject makes the best sticker.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"></naara-gif-maker>
        </div>
        <p x-show="busy" x-cloak class="mt-2 text-sm text-[rgb(var(--nx-text-2))]" role="status">Saving to the library...</p>
        <p x-show="fail" x-cloak x-text="fail" class="mt-2 text-sm font-semibold text-[rgb(var(--nx-bad))]" role="alert"></p>
        @error('made')<p role="alert" class="mt-2 text-sm font-semibold text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror
    </section>

    <section class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4" aria-label="{{ $categories->firstWhere('slug', $category)?->label() }}">
        @forelse ($items as $i)
            <div class="overflow-hidden rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] {{ $i->is_active ? '' : 'opacity-60' }}" wire:key="gif-{{ $i->id }}">
                <img src="{{ $i->url() }}" alt="{{ $i->name }}" loading="lazy" class="aspect-square w-full bg-[rgb(var(--nx-surface-3))] object-contain">
                <div class="space-y-2 p-2.5">
                    <input type="text" value="{{ $i->name }}" aria-label="Name" maxlength="120" class="{{ $inp }} !py-1.5" wire:change="rename({{ $i->id }}, $event.target.value)">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <select aria-label="Move to category" class="{{ $inp }} !w-auto !py-1 text-xs" wire:change="move({{ $i->id }}, $event.target.value)">
                            @foreach ($categories as $c)<option value="{{ $c->slug }}" @selected($c->slug === $i->category)>{{ $c->label() }}</option>@endforeach
                        </select>
                        <button type="button" wire:click="toggle({{ $i->id }})" class="{{ $btn }} border border-[rgb(var(--nx-line-strong))] !px-2 !py-1 text-xs text-[rgb(var(--nx-text))]">{{ $i->is_active ? 'Hide' : 'Show' }}</button>
                        <button type="button" wire:click="remove({{ $i->id }})" wire:confirm="Remove this GIF from the library?" class="{{ $btn }} border border-[rgb(var(--nx-bad))] !px-2 !py-1 text-xs text-[rgb(var(--nx-bad))]">Remove</button>
                    </div>
                    <p class="text-[11px] text-[rgb(var(--nx-text-2))]">{{ $i->source === 'seed' ? 'Shipped' : ucfirst($i->source) }} · {{ number_format($i->bytes / 1024) }} KB</p>
                </div>
            </div>
        @empty
            <p class="col-span-full rounded-xl border border-dashed border-[rgb(var(--nx-line-strong))] p-6 text-center text-sm text-[rgb(var(--nx-text-2))]">Nothing here yet. Add a GIF above.</p>
        @endforelse
    </section>
</div>
