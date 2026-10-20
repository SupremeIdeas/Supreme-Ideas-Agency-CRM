{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
<div class="mx-auto max-w-4xl">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Blog</h1>
        @unless ($showForm)
            <button type="button" wire:click="newPost" class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">
                <x-icon name="check" class="h-4 w-4" /> New post
            </button>
        @endunless
    </div>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b)/0.1)] p-3 text-sm text-[rgb(var(--nx-teal-ink))]">
            <x-icon name="badge-check" class="h-4 w-4 shrink-0" /> {{ $saved }}
        </div>
    @endif

    {{-- Blog hero (4-image reveal) — admin-managed. --}}
    <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-text))]"><x-icon name="image" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> Blog hero</h2>
        <div class="grid gap-3 sm:grid-cols-2">
            <div><label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Title</label>
                <input type="text" wire:model="heroTitle" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]"></div>
            <div><label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Subtitle</label>
                <input type="text" wire:model="heroSubtitle" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]"></div>
        </div>
        <div class="mt-3">
            <label class="mb-1.5 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Hero images (up to 4, interchanging)</label>
            @if ($heroImages)
                <div class="mb-2 grid grid-cols-4 gap-2">
                    @foreach ($heroImages as $i => $img)
                        <div class="group relative aspect-video overflow-hidden rounded-lg border border-[rgb(var(--nx-line))]">
                            <img src="{{ $img }}" class="h-full w-full object-cover">
                            <button type="button" wire:click="removeHeroImage({{ $i }})" class="absolute right-1 top-1 rounded-full bg-black/60 p-1 text-white opacity-0 transition group-hover:opacity-100"><x-icon name="x" class="h-3 w-3" /></button>
                        </div>
                    @endforeach
                </div>
            @endif
            @if (count($heroImages) < 4)
                <div class="flex items-center gap-2">
                    <input type="file" wire:model="heroUpload" accept="image/*" class="block w-full text-xs text-[rgb(var(--nx-text-2))] file:mr-2 file:rounded-full file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-[rgb(var(--nx-teal-ink))]">
                    <button type="button" wire:click="addHeroImage" class="shrink-0 rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-teal-ink))]">Add</button>
                </div>
                @error('heroUpload') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
            @endif
        </div>
        <button type="button" wire:click="saveHero" class="mt-3 rounded-full bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-xs font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">Save hero</button>
    </div>

    {{-- Claude assist: suggest the next article to write. --}}
    @if ($aiEnabled)
        <div class="mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="flex items-center gap-2 text-sm font-semibold text-[rgb(var(--nx-text))]">
                    <x-icon name="zap" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> Write with Claude
                </h2>
                <button type="button" wire:click="suggestTopic" wire:loading.attr="disabled" wire:target="suggestTopic"
                    class="inline-flex items-center gap-1.5 rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-cta-b)/0.2)]">
                    <x-ui.spinner class="h-3.5 w-3.5" wire:loading wire:target="suggestTopic" />
                    Suggest the next article
                </button>
            </div>
            @if ($aiError)<p class="mt-2 text-xs text-[rgb(var(--nx-bad))]">{{ $aiError }}</p>@endif
            @if ($suggestion)
                <div class="mt-3 rounded-xl bg-[rgb(var(--nx-surface-2))] p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">{{ $suggestion['category'] ?? '' }}</p>
                    <p class="mt-0.5 font-semibold text-[rgb(var(--nx-text))]">{{ $suggestion['title'] ?? '' }}</p>
                    @if (! empty($suggestion['angle']))<p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">{{ $suggestion['angle'] }}</p>@endif
                    @if (! empty($suggestion['rationale']))<p class="mt-1 text-xs italic text-[rgb(var(--nx-text-2))]">{{ $suggestion['rationale'] }}</p>@endif
                    <button type="button" wire:click="useSuggestion" class="mt-3 rounded-full bg-[rgb(var(--nx-cta-b))] px-4 py-1.5 text-xs font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">Use this — open a draft</button>
                </div>
            @endif
        </div>
    @endif

    @if ($showForm)
        <form wire:submit="save" class="space-y-4 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $editingId ? 'Edit post' : 'New post' }}</h2>
                <button type="button" wire:click="resetForm" class="text-xs text-[rgb(var(--nx-text-2))] underline hover:text-[rgb(var(--nx-text-2))]">Cancel</button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Title</label>
                    <input wire:model.blur="title" type="text" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    @error('title') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Slug <span class="font-normal text-[rgb(var(--nx-text-2))]">(URL)</span></label>
                    <input wire:model="slug" type="text" placeholder="auto from title" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    @error('slug') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Category</label>
                    <input wire:model="category" type="text" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Status</label>
                    <select wire:model="status" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                        <option value="draft">Draft (hidden)</option>
                        <option value="published">Published (public)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Cover image <span class="font-normal text-[rgb(var(--nx-text-2))]">(WebP/JPEG, ≤ 2 MB)</span></label>
                <input wire:model="cover" type="file" accept="image/webp,image/jpeg,image/png"
                       class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text-2))] file:mr-3 file:rounded-md file:border-0 file:bg-[rgb(var(--nx-cta-b)/0.1)] file:px-3 file:py-1 file:text-xs file:font-semibold file:text-[rgb(var(--nx-teal-ink))]">
                <div wire:loading wire:target="cover" class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Uploading…</div>
                @error('cover') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
                @if ($coverUrl)
                    <img src="{{ $coverUrl }}" alt="" class="mt-2 h-24 w-40 rounded-lg border border-[rgb(var(--nx-line))] object-cover">
                @endif
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Accent colour <span class="font-normal text-[rgb(var(--nx-text-2))]">(scroll-tint; blank = auto from category)</span></label>
                <div class="flex items-center gap-2">
                    <input type="color" wire:model="accent_color" class="h-9 w-14 rounded-lg border border-[rgb(var(--nx-line-strong))]">
                    <input type="text" wire:model="accent_color" placeholder="Hex colour" class="w-32 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    @if ($accent_color)<button type="button" wire:click="$set('accent_color', '')" class="text-xs text-[rgb(var(--nx-text-2))] hover:text-[rgb(var(--nx-text-2))]">Clear</button>@endif
                </div>
                @error('accent_color') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Excerpt <span class="font-normal text-[rgb(var(--nx-text-2))]">(short summary)</span></label>
                <textarea wire:model="excerpt" rows="2" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]"></textarea>
                @error('excerpt') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror
            </div>

            <div>
                <div class="mb-1 flex items-center justify-between">
                    <label class="block text-xs font-medium text-[rgb(var(--nx-text-2))]">Body</label>
                    @if ($aiEnabled)
                        <div class="flex gap-1.5">
                            <button type="button" wire:click="generateDraft" wire:loading.attr="disabled" wire:target="generateDraft"
                                class="inline-flex items-center gap-1 rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-2.5 py-1 text-[11px] font-semibold text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-cta-b)/0.2)]">
                                <x-ui.spinner class="h-3 w-3" wire:loading wire:target="generateDraft" /> Generate with Claude
                            </button>
                            <button type="button" wire:click="reformatBody" wire:loading.attr="disabled" wire:target="reformatBody"
                                class="inline-flex items-center gap-1 rounded-full bg-[rgb(var(--nx-surface-3))] px-2.5 py-1 text-[11px] font-semibold text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]">
                                <x-ui.spinner class="h-3 w-3" wire:loading wire:target="reformatBody" /> Reformat
                            </button>
                        </div>
                    @endif
                </div>
                <p class="mb-1 text-[11px] text-[rgb(var(--nx-text-2))]">Use <code class="font-mono">## Heading</code>, <code class="font-mono">- bullet</code>, and blank lines between paragraphs. HTML is rendered as plain text for safety.</p>
                <textarea wire:model="body" rows="14" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm leading-relaxed text-[rgb(var(--nx-text))]"></textarea>
                @error('body') <span class="mt-1 block text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</span> @enderror

                @if ($aiEnabled)
                    <div class="mt-2 rounded-lg border border-[rgb(var(--nx-line))] p-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-[rgb(var(--nx-text-2))]">Cover image prompt</span>
                            <button type="button" wire:click="getImagePrompt" wire:loading.attr="disabled" wire:target="getImagePrompt"
                                class="inline-flex items-center gap-1 rounded-full bg-[rgb(var(--nx-cta-b)/0.1)] px-2.5 py-1 text-[11px] font-semibold text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-cta-b)/0.2)]">
                                <x-ui.spinner class="h-3 w-3" wire:loading wire:target="getImagePrompt" /> Get image prompt
                            </button>
                        </div>
                        @if ($aiImagePrompt)
                            <div x-data="{ copied: false }" class="mt-2">
                                <p class="rounded bg-[rgb(var(--nx-surface-2))] p-2 text-xs text-[rgb(var(--nx-text-2))]">{{ $aiImagePrompt }}</p>
                                <button type="button" @click="navigator.clipboard.writeText(@js($aiImagePrompt)); copied = true; setTimeout(() => copied = false, 1500)"
                                    class="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold text-[rgb(var(--nx-teal-ink))]">
                                    <x-icon name="copy" class="h-3 w-3" /> <span x-text="copied ? 'Copied!' : 'Copy prompt'"></span>
                                </button>
                                <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Generate the image elsewhere, then upload it as the cover above.</p>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <details class="rounded-lg border border-[rgb(var(--nx-line))] p-3">
                <summary class="cursor-pointer text-xs font-semibold text-[rgb(var(--nx-text-2))]">SEO (optional)</summary>
                <div class="mt-3 space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Meta title <span class="font-normal text-[rgb(var(--nx-text-2))]">(defaults to title)</span></label>
                        <input wire:model="meta_title" type="text" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Meta description <span class="font-normal text-[rgb(var(--nx-text-2))]">(defaults to excerpt)</span></label>
                        <textarea wire:model="meta_description" rows="2" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]"></textarea>
                    </div>
                </div>
            </details>

            <button type="submit" wire:loading.attr="disabled" wire:target="save,cover"
                    class="inline-flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-cta-b))] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-60">
                <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2"><x-icon name="check" class="h-4 w-4" /> Save post</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
            </button>
        </form>
    @else
        <div class="overflow-hidden rounded-xl border border-[rgb(var(--nx-line))]">
            <table class="w-full text-left text-sm">
                <thead class="bg-[rgb(var(--nx-surface-2))] text-[rgb(var(--nx-text-2))]">
                    <tr>
                        <th class="px-4 py-2 font-medium">Title</th>
                        <th class="px-4 py-2 font-medium">Category</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                        <th class="px-4 py-2 font-medium">Date</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]">
                    @forelse ($posts as $post)
                        <tr wire:key="post-{{ $post->id }}" class="text-[rgb(var(--nx-text))]">
                            <td class="px-4 py-2.5 font-medium">{{ $post->title }}</td>
                            <td class="px-4 py-2.5">{{ $post->category }}</td>
                            <td class="px-4 py-2.5">
                                <x-ui.tag :variant="$post->status === 'published' ? 'live' : 'soon'">{{ ucfirst($post->status) }}</x-ui.tag>
                            </td>
                            <td class="px-4 py-2.5 text-[rgb(var(--nx-text-2))]">{{ optional($post->published_at ?? $post->created_at)->format('M j, Y') }}</td>
                            <td class="px-4 py-2.5 text-right">
                                <button type="button" wire:click="edit({{ $post->id }})" class="text-xs font-medium text-[rgb(var(--nx-teal-ink))] underline hover:text-[rgb(var(--nx-teal-ink))]">Edit</button>
                                <button type="button" wire:click="delete({{ $post->id }})" wire:confirm="Delete this post permanently?" class="ml-2 text-xs font-medium text-[rgb(var(--nx-bad))] underline hover:text-[rgb(var(--nx-bad))]">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-[rgb(var(--nx-text-2))]">No posts yet. Click “New post” to write your first.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $posts->links() }}</div>
    @endif
</div>
