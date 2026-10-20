{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
<div class="mx-auto max-w-6xl">
    <h1 class="mb-1 text-2xl font-bold text-[rgb(var(--nx-text))]">FAQ</h1>
    <p class="mb-6 text-sm text-[rgb(var(--nx-text-2))]">
        Topics and questions on the dedicated <a href="{{ route('faq') }}" target="_blank" class="font-semibold text-[rgb(var(--nx-teal-ink))] hover:underline">/faq</a> page.
        An answer can use <code class="rounded bg-[rgb(var(--nx-surface-3))] px-1">&lt;strong&gt;</code>/<code class="rounded bg-[rgb(var(--nx-surface-3))] px-1">&lt;a href=&quot;/path&quot;&gt;</code> for emphasis and links, and the tokens
        <code class="rounded bg-[rgb(var(--nx-surface-3))] px-1">@{{otp_timeout}}</code>,
        <code class="rounded bg-[rgb(var(--nx-surface-3))] px-1">@{{credit_earn_rate}}</code>,
        <code class="rounded bg-[rgb(var(--nx-surface-3))] px-1">@{{whitelabel_from_price}}</code> and
        <code class="rounded bg-[rgb(var(--nx-surface-3))] px-1">@{{whatsapp_answer}}</code>
        for facts that stay live automatically.
    </p>

    <div class="grid gap-6 lg:grid-cols-[300px_1fr]">
        {{-- Topics --}}
        <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
            <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-text))]">
                <x-icon name="help-circle" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> Topics
            </h2>
            <ul class="space-y-1">
                @foreach ($categories as $cat)
                    <li class="flex items-center gap-1">
                        <button type="button" wire:click="selectCategory({{ $cat->id }})"
                                class="flex-1 truncate rounded-lg px-2.5 py-1.5 text-left text-sm font-medium transition {{ $selectedCategoryId === $cat->id ? 'bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))]' : 'text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]' }}
                                       {{ ! $cat->is_active ? 'opacity-50' : '' }}">
                            {{ $cat->title }} <span class="text-xs text-[rgb(var(--nx-text-2))]">{{ $cat->items_count }}</span>
                        </button>
                        <button type="button" wire:click="moveCategory({{ $cat->id }}, 'up')" title="Move up" class="rounded p-1 text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-teal-ink))]"><x-icon name="chevron-right" class="h-3.5 w-3.5 -rotate-90" /></button>
                        <button type="button" wire:click="moveCategory({{ $cat->id }}, 'down')" title="Move down" class="rounded p-1 text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-teal-ink))]"><x-icon name="chevron-right" class="h-3.5 w-3.5 rotate-90" /></button>
                        <button type="button" wire:click="editCategory({{ $cat->id }})" title="Edit" class="rounded p-1 text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-teal-ink))]"><x-icon name="edit" class="h-3.5 w-3.5" /></button>
                    </li>
                @endforeach
            </ul>

            <form wire:submit="saveCategory" class="mt-4 space-y-2 border-t border-[rgb(var(--nx-line))] pt-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">{{ $editingCategoryId ? 'Edit topic' : 'New topic' }}</p>
                <input wire:model="catTitle" type="text" placeholder="Title" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-sm">
                @error('catTitle') <span class="block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                <input wire:model="catEyebrow" type="text" placeholder="Eyebrow (e.g. Section I)" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-sm">
                <textarea wire:model="catBlurb" rows="2" placeholder="One-line blurb" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-sm"></textarea>
                <div class="grid grid-cols-2 gap-2">
                    <input wire:model="catTagLabel" type="text" placeholder="Link label" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-sm">
                    <input wire:model="catTagRoute" type="text" placeholder="Route name" class="rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-sm">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="nx-btn nx-btn--primary !py-1.5 !text-xs">{{ $editingCategoryId ? 'Save' : 'Add topic' }}</button>
                    @if ($editingCategoryId)
                        <button type="button" wire:click="cancelCategoryEdit" class="nx-btn nx-btn--ghost !py-1.5 !text-xs">Cancel</button>
                        <button type="button" wire:click="deleteCategory({{ $editingCategoryId }})" wire:confirm="Delete this topic and all its questions?" class="ml-auto text-xs font-semibold text-[rgb(var(--nx-bad))] hover:underline">Delete</button>
                    @endif
                </div>
            </form>
        </section>

        {{-- Questions in the selected topic --}}
        <section class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
            @php $current = $categories->firstWhere('id', $selectedCategoryId); @endphp
            <div class="mb-3 flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-text))]">
                    <x-icon name="message-circle" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> {{ $current?->title ?? 'Select a topic' }}
                </h2>
                @if ($current)
                    <button type="button" wire:click="toggleCategory({{ $current->id }})" class="text-xs font-semibold {{ $current->is_active ? 'text-[rgb(var(--nx-ok))]' : 'text-[rgb(var(--nx-text-2))]' }}">
                        {{ $current->is_active ? 'Visible' : 'Hidden' }}
                    </button>
                @endif
            </div>

            <div class="space-y-2">
                @forelse ($items as $item)
                    <div class="rounded-xl border border-[rgb(var(--nx-line))] p-3 {{ ! $item->is_active ? 'opacity-50' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $item->question }}</p>
                            <div class="flex shrink-0 items-center gap-1">
                                <button type="button" wire:click="moveItem({{ $item->id }}, 'up')" title="Move up" class="rounded p-1 text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-teal-ink))]"><x-icon name="chevron-right" class="h-3.5 w-3.5 -rotate-90" /></button>
                                <button type="button" wire:click="moveItem({{ $item->id }}, 'down')" title="Move down" class="rounded p-1 text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-teal-ink))]"><x-icon name="chevron-right" class="h-3.5 w-3.5 rotate-90" /></button>
                                <button type="button" wire:click="toggleItem({{ $item->id }})" title="{{ $item->is_active ? 'Hide' : 'Show' }}" class="rounded p-1 text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-teal-ink))]"><x-icon name="{{ $item->is_active ? 'eye' : 'eye-off' }}" class="h-3.5 w-3.5" /></button>
                                <button type="button" wire:click="editItem({{ $item->id }})" title="Edit" class="rounded p-1 text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-teal-ink))]"><x-icon name="edit" class="h-3.5 w-3.5" /></button>
                                <button type="button" wire:click="deleteItem({{ $item->id }})" wire:confirm="Delete this question?" title="Delete" class="rounded p-1 text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-bad))]"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                            </div>
                        </div>
                        <p class="mt-1 text-xs leading-relaxed text-[rgb(var(--nx-text-2))]">{!! $item->answer !!}</p>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-[rgb(var(--nx-text-2))]">{{ $current ? 'No questions yet in this topic.' : 'Pick a topic on the left.' }}</p>
                @endforelse
            </div>

            @if ($current)
                <form wire:submit="saveItem" class="mt-4 space-y-2 border-t border-[rgb(var(--nx-line))] pt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">{{ $editingItemId ? 'Edit question' : 'New question' }}</p>
                    <input wire:model="itemQuestion" type="text" placeholder="Question" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-sm">
                    @error('itemQuestion') <span class="block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                    <textarea wire:model="itemAnswer" rows="3" placeholder="Answer" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-1.5 text-sm"></textarea>
                    @error('itemAnswer') <span class="block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                    <div class="flex gap-2">
                        <button type="submit" class="nx-btn nx-btn--primary !py-1.5 !text-xs">{{ $editingItemId ? 'Save' : 'Add question' }}</button>
                        @if ($editingItemId)
                            <button type="button" wire:click="cancelItemEdit" class="nx-btn nx-btn--ghost !py-1.5 !text-xs">Cancel</button>
                        @endif
                    </div>
                </form>
            @endif
        </section>
    </div>
</div>
