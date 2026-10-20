{{-- Provider login manager (MASTER-ONLY): where and how I sign in to each supplier dashboard. Skin tokens only. No password exists anywhere on this page. --}}
<div data-provider-logins>
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Provider logins</h1>
            <p class="mt-1 max-w-2xl text-sm text-[rgb(var(--nx-text-2))]">Where and how you sign in to each supplier's dashboard, and which email you used. It is a reminder, not a password manager: <b>no passwords are stored here</b> and none can be typed in.</p>
        </div>
    </div>

    @if ($saved)
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-[rgb(var(--nx-ok)/0.12)] p-3 text-sm text-[rgb(var(--nx-ok))]"><x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}</div>
    @endif

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <input type="search" wire:model.live.debounce.250ms="search" placeholder="Search a provider or an email" aria-label="Search providers"
               class="w-full min-w-0 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))] sm:w-auto sm:flex-1 sm:max-w-sm">
        <div class="flex gap-1 rounded-full bg-[rgb(var(--nx-surface-3))] p-1 text-xs font-semibold">
            @foreach (['all' => 'All', 'credentials' => 'API keys saved', 'unconfirmed' => 'To confirm'] as $k => $l)
                <button type="button" wire:click="$set('filter','{{ $k }}')" class="rounded-full px-3 py-1.5 {{ $filter === $k ? 'bg-[rgb(var(--nx-surface))] text-[rgb(var(--nx-teal-ink))] shadow-sm' : 'text-[rgb(var(--nx-text-2))]' }}">{{ $l }}</button>
            @endforeach
        </div>
    </div>

    <details class="mb-4 rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))]" data-provider-logins-manage>
        <summary class="cursor-pointer select-none rounded-xl px-4 py-3 text-sm font-semibold text-[rgb(var(--nx-text))]">Email hints and extra providers <span class="font-normal text-[rgb(var(--nx-text-2))]">({{ $emails->count() }} saved emails)</span></summary>
        <div class="grid gap-4 border-t border-[rgb(var(--nx-line))] p-4 md:grid-cols-2">
            <div class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
                <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Email hints</h2>
                <p class="mb-3 mt-1 text-xs text-[rgb(var(--nx-text-2))]">The emails offered in each provider's dropdown.</p>
                <ul class="space-y-1.5">
                    @foreach ($emails as $e)
                        <li class="flex items-center justify-between gap-2 text-xs" wire:key="em-{{ $e->id }}">
                            <span class="min-w-0 truncate text-[rgb(var(--nx-text))]">{{ $e->email }} @if ($e->label)<span class="text-[rgb(var(--nx-text-2))]">({{ $e->label }})</span>@endif</span>
                            <button type="button" wire:click="removeEmail({{ $e->id }})" wire:confirm="Remove this email from the dropdown? Providers that already use it keep it." class="shrink-0 text-[rgb(var(--nx-bad))] hover:underline">Remove</button>
                        </li>
                    @endforeach
                </ul>
                <form wire:submit="addEmail" class="mt-3 space-y-2">
                    <input type="email" wire:model="newEmail" placeholder="name@example.com" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    @error('newEmail') <p class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                    <input type="text" wire:model="newEmailLabel" maxlength="80" placeholder="Label (optional)" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    <button type="submit" wire:loading.attr="disabled" wire:target="addEmail" class="ns-cta w-full"><span wire:loading.remove wire:target="addEmail">Add email</span><span wire:loading wire:target="addEmail">Adding...</span></button>
                </form>
            </div>

            <div class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4">
                <h2 class="text-sm font-semibold text-[rgb(var(--nx-text))]">Add another provider</h2>
                <form wire:submit="addCustom" class="mt-3 space-y-2">
                    <input type="text" wire:model="customName" maxlength="120" placeholder="Provider name" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                    @error('customName') <p class="text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                    <select wire:model="customCategory" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                        @foreach ($categories as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                    </select>
                    <button type="submit" wire:loading.attr="disabled" wire:target="addCustom" class="ns-cta w-full"><span wire:loading.remove wire:target="addCustom">Add provider</span><span wire:loading wire:target="addCustom">Adding...</span></button>
                </form>
            </div>
        </div>
    </details>

    <div>
        <div class="space-y-2">
            @forelse ($rows as $x)
                @php($r = $x['row'])
                <div class="rounded-xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4" wire:key="pl-{{ $r->id }}" data-provider-login="{{ $r->provider_key }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $r->name }}</p>
                                <span class="rounded-full bg-[rgb(var(--nx-surface-3))] px-2 py-0.5 text-[11px] text-[rgb(var(--nx-text-2))]">{{ $categories[$r->category] ?? 'Other' }}</span>
                                @if ($x['keys'])
                                    <span class="rounded-full bg-[rgb(var(--nx-ok)/0.14)] px-2 py-0.5 text-[11px] font-semibold text-[rgb(var(--nx-ok))]">API keys saved</span>
                                @endif
                                @if ($r->confirmed_at === null)
                                    <span class="rounded-full bg-[rgb(var(--nx-warn)/0.16)] px-2 py-0.5 text-[11px] font-semibold text-[rgb(var(--nx-warn))]">Default, please confirm</span>
                                @endif
                            </div>
                            <dl class="mt-2 grid gap-x-6 gap-y-1 text-xs sm:grid-cols-2">
                                <div><dt class="inline text-[rgb(var(--nx-text-2))]">Sign in with: </dt><dd class="inline font-medium text-[rgb(var(--nx-text))]">{{ $methods[$r->login_method] ?? 'Not confirmed yet' }}</dd></div>
                                <div><dt class="inline text-[rgb(var(--nx-text-2))]">Email used: </dt><dd class="inline font-medium text-[rgb(var(--nx-text))]">{{ $r->email_hint ?: 'Not set' }}</dd></div>
                                @if ($r->username_hint)
                                    <div><dt class="inline text-[rgb(var(--nx-text-2))]">Username: </dt><dd class="inline font-medium text-[rgb(var(--nx-text))]">{{ $r->username_hint }}</dd></div>
                                @endif
                                @if ($r->notes)
                                    <div class="sm:col-span-2"><dt class="inline text-[rgb(var(--nx-text-2))]">Note: </dt><dd class="inline text-[rgb(var(--nx-text))]">{{ $r->notes }}</dd></div>
                                @endif
                            </dl>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @if ($r->login_url)
                                <a href="{{ $r->login_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 rounded-lg border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-surface-2))]">Open login <x-icon name="arrow-right" class="h-3.5 w-3.5" /></a>
                            @endif
                            <button type="button" wire:click="edit({{ $r->id }})" class="rounded-lg bg-[rgb(var(--nx-surface-3))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text))] hover:bg-[rgb(var(--nx-surface-2))]">Edit</button>
                        </div>
                    </div>

                    @if ($editingId === $r->id)
                        <form wire:submit="save" class="mt-4 grid gap-3 border-t border-[rgb(var(--nx-line))] pt-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Login link</label>
                                <input type="url" wire:model="login_url" placeholder="https://" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                                @error('login_url') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">How I sign in</label>
                                <select wire:model.live="login_method" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                                    @foreach ($methods as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                                </select>
                                @error('login_method') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Email I signed up with</label>
                                <select wire:model="email_hint" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                                    <option value="">Not set</option>
                                    @foreach ($emails as $e)<option value="{{ $e->email }}">{{ $e->email }}{{ $e->label ? ' ('.$e->label.')' : '' }}</option>@endforeach
                                </select>
                                @error('email_hint') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                            </div>
                            @if ($login_method === 'username_password')
                                <div class="sm:col-span-2">
                                    <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Username (not the password)</label>
                                    <input type="text" wire:model="username_hint" maxlength="120" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                                </div>
                            @endif
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Note (optional)</label>
                                <input type="text" wire:model="notes" maxlength="300" placeholder="For example: 2-step code goes to the owner's phone" class="w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]">
                                <p class="mt-1 text-[11px] text-[rgb(var(--nx-text-2))]">Never type a password here.</p>
                                @error('notes') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex items-center gap-2 sm:col-span-2">
                                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="ns-cta !w-auto px-8"><span wire:loading.remove wire:target="save">Save</span><span wire:loading wire:target="save">Saving...</span></button>
                                <button type="button" wire:click="cancel" class="rounded-lg px-3 py-2 text-sm text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]">Cancel</button>
                                @if ($r->is_custom)
                                    <button type="button" wire:click="deleteCustom({{ $r->id }})" wire:confirm="Remove this provider from the list?" class="ml-auto text-xs font-semibold text-[rgb(var(--nx-bad))] hover:underline">Remove provider</button>
                                @endif
                            </div>
                        </form>
                    @endif
                </div>
            @empty
                <p class="rounded-xl border border-dashed border-[rgb(var(--nx-line))] p-8 text-center text-sm text-[rgb(var(--nx-text-2))]">No provider matches.</p>
            @endforelse
        </div>

    </div>
</div>
