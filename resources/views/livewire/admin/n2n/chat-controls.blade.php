{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
@php
    $in = 'w-full rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]';
    $lb = 'mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]';
    $card = 'rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4 sm:p-5';
    $names = ['double_tap' => 'Double-tap react', 'swipe_end' => 'Swipe to reply', 'swipe_start' => 'Swipe toward start', 'long_press' => 'Press and hold / menu', 'list_pin' => 'Chat list: swipe to pin', 'list_archive' => 'Chat list: swipe to archive', 'lightbox_zoom' => 'Photo viewer: pinch or double-tap zoom', 'lightbox_dismiss' => 'Photo viewer: swipe down to close', 'vn_skip' => 'Voice note: double-tap to skip 5 seconds', 'peek' => 'Chat list: peek card on hold', 'pull_search' => 'Chat list: pull down to search', 'drag_select' => 'Select mode: hold and drag to select', 'call_minimize' => 'Call screen: swipe down or move the small window'];
@endphp
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-[rgb(var(--nx-text))]">Chat controls</h1>
        <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))]">The gesture settings every member starts from. Members can change their own in Account > Chat gestures and controls; "Buttons only" is always a personal choice.</p>
    </div>

    <form wire:submit="save" class="{{ $card }} space-y-3">
        <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Platform defaults</h2>
        <div class="flex flex-wrap gap-4 text-sm text-[rgb(var(--nx-text))]">
            <label class="flex items-center gap-2"><input type="checkbox" wire:model="swipeReply"> Swipe to reply</label>
            <label class="flex items-center gap-2"><input type="checkbox" wire:model="listSwipe"> Swipe chats in the list (pin / archive)</label>
            <label class="flex items-center gap-2"><input type="checkbox" wire:model="doubleTapReact"> Double-tap to react</label>
            <label class="flex items-center gap-2"><input type="checkbox" wire:model="haptics"> Vibration feedback</label>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div><label class="{{ $lb }}">Double-tap reaction</label><select wire:model="doubleTapCode" class="{{ $in }}">@foreach ($reactions as $code => $glyph)<option value="{{ $code }}">{{ str_replace('_', ' ', $code) }}</option>@endforeach</select></div>
            <div><label class="{{ $lb }}">Swipe toward the start does</label><select wire:model="swipeStart" class="{{ $in }}"><option value="info">Message info</option><option value="forward">Forward</option><option value="star">Star</option><option value="none">Nothing</option></select></div>
            <div><label class="{{ $lb }}">Press and hold delay (ms, 300 to 1000)</label><input type="number" wire:model="longPressMs" min="300" max="1000" class="{{ $in }}">@error('longPressMs')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
            <div><label class="{{ $lb }}">Swipe distance (px, 40 to 120)</label><input type="number" wire:model="swipeCommitPx" min="40" max="120" class="{{ $in }}">@error('swipeCommitPx')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p>@enderror</div>
        </div>
        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-lg bg-[rgb(var(--nx-cta-b))] px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"><span wire:loading.remove wire:target="save">Save defaults</span><span wire:loading wire:target="save">Saving...</span></button>
    </form>

    <div class="{{ $card }}">
        <h2 class="text-base font-semibold text-[rgb(var(--nx-text))]">Last 14 days (anonymous totals)</h2>
        <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">No account, message or time of day is kept, only counts per gesture. A high cancel rate means the gesture is being started by accident or the distance is too short; a high "menu used instead" means people do not find or trust the gesture.</p>
        <div class="mt-3 overflow-x-auto"><table class="w-full text-left text-sm text-[rgb(var(--nx-text))]">
            <thead><tr class="text-xs text-[rgb(var(--nx-text-2))]"><th class="py-1 pr-3">Gesture</th><th class="pr-3">Started</th><th class="pr-3">Completed</th><th class="pr-3">Cancelled</th><th class="pr-3">Cancel rate</th><th class="pr-3">Undone</th><th class="pr-3">Undone rate</th><th>Menu used instead</th></tr></thead>
            <tbody>@foreach ($stats as $g => $s)
                <tr class="border-t border-[rgb(var(--nx-line))]"><td class="py-2 pr-3">{{ $names[$g] }}</td><td class="pr-3">{{ number_format($s['fired']) }}</td><td class="pr-3">{{ number_format($s['committed']) }}</td><td class="pr-3">{{ number_format($s['cancelled']) }}</td>
                    <td class="pr-3 {{ ($s['cancel_pct'] ?? 0) > 40 ? 'text-[rgb(var(--nx-bad))] font-semibold' : '' }}">{{ $s['cancel_pct'] === null ? 'n/a' : $s['cancel_pct'].'%' }}</td><td class="pr-3">{{ number_format($s['undone']) }}</td><td class="pr-3 {{ ($s['misfire_pct'] ?? 0) > 8 ? 'text-[rgb(var(--nx-bad))] font-semibold' : '' }}" title="Taken back with Undo: above 8% means the gesture is firing by accident">{{ $s['misfire_pct'] === null ? 'n/a' : $s['misfire_pct'].'%' }}</td><td>{{ number_format($s['fallback']) }}</td></tr>
            @endforeach</tbody></table></div>
    </div>
</div>
