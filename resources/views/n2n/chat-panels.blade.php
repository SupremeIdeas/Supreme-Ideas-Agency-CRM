{{-- Side sheets over an open conversation (N2N Batch 12, MASTER-ONLY): search, starred, media/voice/links, theme, storage. Tokens only. --}}
@php($pd = $tools['panelData'])
<div class="nc__panel" role="dialog" aria-modal="false" aria-label="{{ ['search' => 'Search in chat', 'starred' => 'Starred messages', 'media' => 'Media, voice and links', 'theme' => 'Chat theme', 'storage' => 'Manage storage', 'scheduled' => 'Scheduled messages', 'disappear' => 'Disappearing messages', 'event' => 'Plan an event', 'agenda' => 'Upcoming events', 'followup' => 'Remind me to follow up', 'forward' => 'Forward to', 'edit' => 'Edit message', 'info' => 'Message info'][$panel] ?? 'Chat tools' }}" wire:key="panel-{{ $panel }}" @keydown.escape.window="$wire.closePanel()">
    <header class="nc__phead">
        <button type="button" wire:click="closePanel" class="nn__iconbtn" aria-label="Close"><x-nx.icon name="x" /></button>
        <h3>{{ ['search' => 'Search in chat', 'starred' => 'Starred messages', 'media' => 'Media, voice and links', 'theme' => 'Chat theme', 'storage' => 'Manage storage', 'scheduled' => 'Scheduled messages', 'disappear' => 'Disappearing messages', 'event' => 'Plan an event', 'agenda' => 'Upcoming events', 'followup' => 'Remind me to follow up', 'forward' => 'Forward to', 'edit' => 'Edit message', 'info' => 'Message info'][$panel] ?? '' }}</h3>
    </header>
    <div class="nc__pbody">

    @if ($panel === 'search')
        <label class="nc__psearch"><span class="sr-only">Search this chat</span><x-nx.icon name="search" />
            <input type="search" wire:model.live.debounce.300ms="chatSearch" placeholder="Search messages in this chat" autofocus maxlength="100" autocomplete="off"></label>
        @if (mb_strlen(trim($chatSearch)) < 2)
            <p class="nc__phint">Type at least two letters. Only this chat is searched, and only what you can see.</p>
        @elseif (empty($pd['results']))
            <p class="nc__phint">No messages match "{{ $chatSearch }}".</p>
        @else
            <ul class="nc__plist">
                @foreach ($pd['results'] as $r)
                    <li wire:key="sr-{{ $r['id'] }}"><button type="button" x-on:click="$wire.closePanel(); $nextTick(() => window.n2nJump && window.n2nJump({{ $r['id'] }}))">
                        <b>{{ $r['mine'] ? 'You' : ($peer?->name ?: 'Them') }}</b> <small>{{ $r['at']?->format('d M, H:i') }}</small><span>{{ $r['body'] }}</span></button></li>
                @endforeach
            </ul>
            <button type="button" class="nc__chip is-danger mt-3" wire:click="deleteSearchForMe" wire:confirm="Delete all {{ count($pd['results']) }} matching message(s) for you? The other person keeps them." wire:loading.attr="disabled" wire:target="deleteSearchForMe">Delete these {{ count($pd['results']) }} for me</button>
        @endif

    @elseif ($panel === 'disappear')
        <p class="nc__phint">New messages in this chat disappear for both of you after the time you choose. Either of you can change it. It does not affect messages already sent, and it cannot stop someone from copying or photographing what they have already read.</p>
        <ul class="nc__plist">
            @foreach (\App\Services\N2N\Messaging\Disappearing::OPTIONS as $sec => $label)
                <li wire:key="dis-{{ $sec }}"><button type="button" wire:click="setDisappear({{ $sec }})" wire:loading.attr="disabled" wire:target="setDisappear" aria-pressed="{{ (int) $current->disappear_after === $sec ? 'true' : 'false' }}"><b>{{ $label }}</b>@if ((int) $current->disappear_after === $sec)<small>Current</small>@endif</button></li>
            @endforeach
        </ul>

    @elseif ($panel === 'event')
        <form wire:submit="saveEvent" class="nc__pform" x-data x-init="$wire.$set('evForm.tz', Intl.DateTimeFormat().resolvedOptions().timeZone || '', false)">
            <label class="sr-only" for="ev-title">Event title</label>
            <input id="ev-title" type="text" wire:model="evForm.title" maxlength="80" class="nc__ptext" placeholder="Event title" autocomplete="off" required autofocus>
            <div class="nev__row">
                <label class="nc__date"><span class="nev__lbl">Date</span><input type="date" wire:model="evForm.date" min="{{ now()->format('Y-m-d') }}" required></label>
                <label class="nc__date"><span class="nev__lbl">Time</span><input type="time" wire:model="evForm.time" required></label>
            </div>
            <div class="nev__row">
                <label class="nc__date"><span class="nev__lbl">Length</span>
                    <select wire:model="evForm.duration" class="nev__sel">@foreach (\App\Services\N2N\Messaging\Planner::DURATIONS as $d)<option value="{{ $d }}">{{ $d === 0 ? 'No end time' : ($d >= 60 ? ($d / 60).' h' : $d.' min') }}</option>@endforeach</select></label>
                <label class="nc__date"><span class="nev__lbl">Remind me</span>
                    <select wire:model="evForm.reminder" class="nev__sel">@foreach (\App\Services\N2N\Messaging\Planner::REMINDERS as $r)<option value="{{ $r }}">{{ $r === 0 ? 'At the start' : ($r >= 1440 ? '1 day before' : ($r >= 60 ? ($r / 60).' h before' : $r.' min before')) }}</option>@endforeach</select></label>
            </div>
            <label class="sr-only" for="ev-desc">Notes</label>
            <textarea id="ev-desc" wire:model="evForm.description" rows="3" maxlength="500" class="nc__ptext" placeholder="Notes (optional)"></textarea>
            <p class="nc__phint">Your time is saved in your own timezone and shown to them in theirs. Both of you get one reminder. @if (! $evForm['id'])Planning an event is sent as a message ({{ \App\Services\N2N\Credit\Money::credits(\App\Services\N2N\Credit\N2NPricing::messageCostCents()) }} credit). @else Editing is free; moving the time asks them to answer again. @endif Links are not allowed.</p>
            @if ($error)<p class="nc__sheeterr" role="alert">{{ $error }}</p>@endif
            <button type="submit" wire:loading.attr="disabled" wire:target="saveEvent" class="ns-cta ns-cta--pill ns-cta--sm">{{ $evForm['id'] ? 'Save changes' : 'Plan event' }}</button>
        </form>

    @elseif ($panel === 'followup')
        <p class="nc__phint">A private reminder just for you. {{ $peer?->name ?: 'The other person' }} is never told, and it is not a message.</p>
        <div x-data="{ title: '', custom: '',
                at(kind) { const d = new Date(); if (kind === 'h') { d.setHours(d.getHours() + 1); } else if (kind === 't') { d.setHours(18, 0, 0, 0); if (d <= new Date()) { d.setDate(d.getDate() + 1); } } else if (kind === 'm') { d.setDate(d.getDate() + 1); d.setHours(9, 0, 0, 0); } else { d.setDate(d.getDate() + 3); d.setHours(9, 0, 0, 0); } return d; },
                go(d) { if (!this.title.trim() || !d || isNaN(d)) { return; } $wire.addFollowup(this.title, d.toISOString()); } }">
            <label class="nc__psearch"><span class="sr-only">What to follow up on</span>
                <input type="text" x-model="title" maxlength="120" placeholder="e.g. Ask about the invoice" autocomplete="off"></label>
            <ul class="nc__plist">
                <li><button type="button" x-on:click="go(at('h'))" wire:loading.attr="disabled" wire:target="addFollowup"><b>In 1 hour</b></button></li>
                <li><button type="button" x-on:click="go(at('t'))" wire:loading.attr="disabled" wire:target="addFollowup"><b>This evening</b><small>18:00</small></button></li>
                <li><button type="button" x-on:click="go(at('m'))" wire:loading.attr="disabled" wire:target="addFollowup"><b>Tomorrow morning</b><small>09:00</small></button></li>
                <li><button type="button" x-on:click="go(at('d'))" wire:loading.attr="disabled" wire:target="addFollowup"><b>In 3 days</b><small>09:00</small></button></li>
            </ul>
            <label class="nc__phint" for="fu-custom">Or pick a time</label>
            <div class="flex items-center gap-2">
                <input id="fu-custom" type="datetime-local" x-model="custom" class="min-w-0 flex-1 rounded-lg border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2 text-[rgb(var(--nx-text))]">
                <button type="button" class="ns-cta ns-cta--pill ns-cta--sm" x-on:click="go(custom ? new Date(custom) : null)" wire:loading.attr="disabled" wire:target="addFollowup">Save</button>
            </div>
            <p class="nc__phint"><a href="{{ route('n2n.planner') }}" wire:navigate class="underline">See all your follow-ups</a></p>
        </div>

    @elseif ($panel === 'agenda')
        @if ($pd['items']->isEmpty())
            <p class="nc__phint">No upcoming events in this chat. Open the menu and choose Plan an event.</p>
        @else
            @php($lab = ['going' => 'Going', 'maybe' => 'Maybe', 'declined' => 'Can\'t go', 'no_response' => 'Not responded'])
            <ul class="nc__plist">
                @foreach ($pd['items'] as $row)
                    @php($ev = $row['event'])
                    <li wire:key="ag-{{ $ev->id }}" class="nc__prow">
                        <button type="button" class="min-w-0 flex-1 text-start" x-on:click="$wire.closePanel(); $nextTick(() => window.n2nJump && window.n2nJump({{ $ev->message_id }}))">
                            <b class="block truncate">{{ $ev->title }}</b>
                            <small class="block"><time datetime="{{ $ev->starts_at->toIso8601String() }}" x-data x-init="$el.textContent = new Date($el.dateTime).toLocaleString([], { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })">{{ $ev->starts_at->copy()->setTimezone($ev->timezone)->format('D j M, H:i') }}</time></small>
                            <small class="block">You: {{ $lab[$row['mine']] }} · {{ $peer?->name ?: 'Them' }}: {{ $lab[$row['theirs']] }}</small>
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif

    @elseif ($panel === 'starred')
        @if (empty($pd['items']))
            <p class="nc__phint">No starred messages yet. Open the menu on any message and choose Star. Stars are private to you.</p>
        @else
            <ul class="nc__plist">
                @foreach ($pd['items'] as $r)
                    <li wire:key="st-{{ $r['id'] }}" class="nc__prow"><button type="button" x-on:click="$wire.closePanel(); $nextTick(() => window.n2nJump && window.n2nJump({{ $r['id'] }}))">
                        <b>{{ $r['mine'] ? 'You' : ($peer?->name ?: 'Them') }}</b> <small>{{ $r['at']?->format('d M, H:i') }}</small><span>{{ $r['body'] }}</span></button>
                        <button type="button" class="nn__iconbtn" wire:click="toggleStar({{ $r['id'] }})" aria-label="Remove star"><x-nx.icon name="x" style="font-size:14px" /></button></li>
                @endforeach
            </ul>
        @endif

    @elseif ($panel === 'media')
        <div class="nc__ptabs" role="tablist">
            @foreach (['media' => 'Media', 'voice' => 'Voice', 'docs' => 'Documents', 'links' => 'Links'] as $k => $label)
                <button type="button" role="tab" aria-selected="{{ $mediaTab === $k ? 'true' : 'false' }}" wire:click="setMediaTab('{{ $k }}')" class="{{ $mediaTab === $k ? 'is-on' : '' }}">{{ $label }}</button>
            @endforeach
        </div>
        @if ($mediaTab === 'links')
            @if (empty($pd['links']))<p class="nc__phint">No links yet. Only Naara links can be shared in chats.</p>
            @else<ul class="nc__plist">@foreach ($pd['links'] as $l)<li wire:key="ln-{{ $loop->index }}"><a href="{{ $l['url'] }}" target="_blank" rel="noopener"><span class="break-all">{{ $l['url'] }}</span><small>{{ $l['at']?->format('d M Y') }}</small></a></li>@endforeach</ul>@endif
        @elseif ($mediaTab === 'voice')
            @if (empty($pd['items']))<p class="nc__phint">No voice notes in this chat.</p>
            @else<ul class="nc__plist">@foreach ($pd['items'] as $it)<li wire:key="vo-{{ $it['id'] }}"><b>{{ $it['mine'] ? 'You' : ($peer?->name ?: 'Them') }}</b> <small>{{ $it['at']?->format('d M, H:i') }} &middot; {{ $it['seconds'] ?? 0 }}s</small>
                <audio controls preload="none" src="{{ route('n2n.media', $it['id']) }}" class="mt-1 w-full" aria-label="Voice message"></audio></li>@endforeach</ul>@endif
        @elseif ($mediaTab === 'docs')
            @if (empty($pd['items']))<p class="nc__phint">No documents in this chat.</p>
            @else<ul class="nc__plist">@foreach ($pd['items'] as $it)<li wire:key="dc-{{ $it['id'] }}"><a href="{{ route('n2n.media', ['attachment' => $it['id'], 'download' => 1]) }}" download><span class="break-all">{{ $it['name'] ?? 'Document' }}</span><small>{{ $it['at']?->format('d M Y') }} &middot; {{ round($it['bytes'] / 1048576, 1) }} MB</small></a></li>@endforeach</ul>@endif
        @else
            @if (empty($pd['items']))<p class="nc__phint">No photos or videos in this chat yet.</p>
            @else
                <div class="nc__pgrid">
                    @foreach ($pd['items'] as $it)
                        <a href="{{ route('n2n.media', $it['id']) }}" target="_blank" rel="noopener" wire:key="md-{{ $it['id'] }}" class="nc__pthumb" aria-label="{{ $it['kind'] === 'video' ? 'Video' : 'Photo' }} from {{ $it['at']?->format('d M Y') }}">
                            @if ($it['kind'] === 'image')<img src="{{ route('n2n.media', ['attachment' => $it['id'], 'thumb' => 1]) }}" alt="" loading="lazy">@else<span><x-nx.icon name="play" /></span>@endif
                        </a>
                    @endforeach
                </div>
            @endif
        @endif

    @elseif ($panel === 'theme')
        <div x-data="{ accent: @js($tools['theme']['accent']), wp: @js($tools['theme']['wallpaper']), dim: {{ (int) $tools['theme']['dim'] }}, map: @js($bubbleMap), wps: @js($wallMap), hasPhoto: {{ $tools['theme']['photo_id'] ? 'true' : 'false' }}, busy: false, err: '',
              async upload(e) {
                  const f = e.target.files[0]; if (!f) return; this.busy = true; this.err = '';
                  const fd = new FormData(); fd.append('photo', f);
                  try {
                      const r = await fetch(@js(route('n2n.api.wallpaper', $current->id)), { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json' } });
                      const j = await r.json().catch(() => ({})); if (!r.ok) { this.err = j.message || 'That photo could not be used.'; } else { this.hasPhoto = true; $wire.$refresh(); }
                  } catch (x) { this.err = 'Network problem. Try again.'; }
                  this.busy = false; e.target.value = '';
              } }">
            <p class="nc__phint">Only you will see these changes. The other person keeps their own theme.</p>
            <div class="ncp__preview" :style="`--pv-out-bg-l:${map[accent].light.out_bg};--pv-out-fg-l:${map[accent].light.out_fg};--pv-in-bg-l:${map[accent].light.in_bg};--pv-in-fg-l:${map[accent].light.in_fg};--pv-out-bg-d:${map[accent].dark.out_bg};--pv-out-fg-d:${map[accent].dark.out_fg};--pv-in-bg-d:${map[accent].dark.in_bg};--pv-in-fg-d:${map[accent].dark.in_fg};--pv-wall-l:${wps[wp].l};--pv-wall-d:${wps[wp].d}`">
                <span class="ncp__b ncp__b--in">Hi, are we still on for today?</span>
                <span class="ncp__b ncp__b--out">Yes, see you at five.</span>
            </div>
            <h4 class="nc__ph4">Colour</h4>
            <div class="ncp__swatches" role="radiogroup" aria-label="Bubble colour">
                @foreach ($accents as $k => $a)
                    <button type="button" role="radio" :aria-checked="accent === '{{ $k }}'" aria-label="{{ $a['label'] }}" @click="accent = '{{ $k }}'" :class="accent === '{{ $k }}' ? 'is-on' : ''" style="background: {{ $a['hex'] }}"><x-nx.icon name="check" x-show="accent === '{{ $k }}'" /></button>
                @endforeach
            </div>
            <h4 class="nc__ph4">Wallpaper</h4>
            <div class="ncp__walls" role="radiogroup" aria-label="Wallpaper">
                @foreach ($wallpapers as $k => $w)
                    <button type="button" role="radio" :aria-checked="wp === '{{ $k }}'" @click="wp = '{{ $k }}'" :class="wp === '{{ $k }}' ? 'is-on' : ''" class="ncw" style="--wp-l: {{ $w['light'] }}; --wp-d: {{ $w['dark'] }}"><span>{{ $w['label'] }}</span></button>
                @endforeach
            </div>
            <h4 class="nc__ph4">Your own photo</h4>
            <p class="nc__phint">One photo per chat, kept private. It replaces the wallpaper above on your screen only.</p>
            <div class="ncp__photo">
                @if ($tools['theme']['photo_id'])<img src="{{ route('n2n.wallpaper', $tools['theme']['photo_id']) }}" alt="Your wallpaper photo" class="ncp__thumb">@endif
                <label class="ns-cta ns-cta--pill ns-cta--sm cursor-pointer"><input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="upload($event)" :disabled="busy"><span x-text="busy ? 'Uploading…' : (hasPhoto ? 'Change photo' : 'Choose a photo')"></span></label>
                @if ($tools['theme']['photo_id'])<button type="button" wire:click="removeWallpaperPhoto" class="nc__plink">Remove photo</button>@endif
            </div>
            <p class="nc__perr" x-show="err" x-text="err" role="alert"></p>
            <div x-show="hasPhoto">
                <label class="nc__ph4" for="ncp-dim">Photo dimming</label>
                <input id="ncp-dim" type="range" min="0" max="60" step="5" x-model.number="dim" class="w-full">
            </div>
            <p class="nc__phint">Text on your bubbles is checked for readability (WCAG AA, at least {{ \App\Services\N2N\Messaging\ChatThemes::MIN_RATIO }}:1) in light and dark mode. A colour that would be hard to read is adjusted automatically.</p>
            <div class="ncp__actions">
                <button type="button" class="ns-cta ns-cta--pill" @click="$wire.saveTheme(accent, wp, dim)" wire:loading.attr="disabled" wire:target="saveTheme">Save theme</button>
                <button type="button" wire:click="resetTheme" wire:confirm="Reset this chat to the default look?" class="nc__plink">Reset</button>
            </div>
        </div>

    @elseif ($panel === 'forward')
        @php($cap = \App\Services\N2N\Messaging\MessageActions::forwardMaxTargets())
        @if ($pd['targets']->isEmpty())
            <p class="nc__phint">You have no other chats to forward to yet.</p>
        @else
            <ul class="nc__plist">
                @foreach ($pd['targets'] as $t)
                    <li wire:key="fw-{{ $t['id'] }}" class="nc__prow">
                        <button type="button" wire:click="toggleForwardTo({{ $t['id'] }})" aria-pressed="{{ in_array($t['id'], $forwardTo, true) ? 'true' : 'false' }}" class="nc__fw {{ in_array($t['id'], $forwardTo, true) ? 'is-on' : '' }}">
                            <span class="nc__fwck" aria-hidden="true">@if (in_array($t['id'], $forwardTo, true))<x-nx.icon name="check" style="font-size:13px" />@endif</span>
                            <b>{{ $t['title'] }}</b>@if ($t['naara_id'])<small>{{ $t['naara_id'] }}</small>@endif
                        </button>
                    </li>
                @endforeach
            </ul>
            <div class="nc__pfoot">
                <p class="nc__phint">{{ count($forwardTo) }} of {{ $cap }} chosen. Each copy costs {{ $cost }} credit ({{ \App\Services\N2N\Credit\Money::credits(count($forwardTo) * \App\Services\N2N\Credit\N2NPricing::messageCostCents()) }} in total) and is marked Forwarded. Photos, videos and voice notes cannot be forwarded. The person who wrote a message did not agree to it being shared further, so only forward what you are comfortable with them knowing about. Forwarded copies cannot be pulled back once sent.</p>
                <button type="button" wire:click="sendForward" wire:loading.attr="disabled" wire:target="sendForward" @disabled(count($forwardTo) === 0) class="ns-cta ns-cta--pill ns-cta--sm">Forward</button>
            </div>
        @endif

    @elseif ($panel === 'edit')
        <form wire:submit="saveEdit" class="nc__pform">
            <label class="sr-only" for="n2n-edit-body">Edit your message</label>
            <textarea id="n2n-edit-body" wire:model="editBody" rows="5" maxlength="4000" class="nc__ptext" autofocus></textarea>
            <p class="nc__phint">You can edit a text message for {{ \App\Services\N2N\Messaging\MessageActions::editWindowMinutes() }} minutes after sending. It will show as edited. Editing is free.</p>
            <button type="submit" wire:loading.attr="disabled" wire:target="saveEdit" class="ns-cta ns-cta--pill ns-cta--sm">Save</button>
        </form>

    @elseif ($panel === 'info')
        @if (empty($pd))
            <p class="nc__phint">Details are not available for this message.</p>
        @else
            <dl class="nc__pinfo">
                <div><dt>Sent</dt><dd>{{ $pd['sent']->format('D d M, H:i:s') }}</dd></div>
                <div><dt>Delivered</dt><dd>{{ $pd['delivered'] ? $pd['delivered']->format('D d M, H:i:s') : 'Not yet' }}</dd></div>
                <div><dt>Read</dt><dd>{{ $pd['read'] ? $pd['read']->format('D d M, H:i:s') : 'Not shown' }}</dd></div>
                @if ($pd['edited'])<div><dt>Edited</dt><dd>{{ $pd['edited']->format('D d M, H:i') }}</dd></div>@endif
                @if ($pd['forwarded'])<div><dt>Type</dt><dd>Forwarded message</dd></div>@endif
                <div><dt>Cost</dt><dd>{{ $pd['cost'] }} credit</dd></div>
            </dl>
            <p class="nc__phint">Read time appears only when the other person has read receipts switched on.</p>
        @endif

    @elseif ($panel === 'scheduled')
        @if ($pd['items']->isEmpty())
            <p class="nc__phint">Nothing scheduled. In the composer, tap the clock, pick a time, then send. Scheduled messages (text, photos, videos, voice notes and documents) cost nothing until they are delivered, and only you can see them.</p>
        @else
            <ul class="nc__plist" wire:poll.15s>
                @foreach ($pd['items'] as $sm)
                    <li wire:key="sm-{{ $sm->id }}" class="nc__prow">
                        <span class="min-w-0 flex-1">
                            <b>{{ $sm->send_at->timezone(config('app.display_timezone', config('app.timezone')))->format('D d M, H:i') }}</b>
                            <small>@if ($sm->status === 'failed') {{ ['credit' => 'Not sent: not enough credit.', 'undelivered' => 'Could not be delivered.', 'error' => 'Something went wrong. Try again.'][$sm->fail_reason] ?? 'Not sent.' }} @elseif ($sm->status === 'sending') Sending now @else Waiting @endif</small>
                            @if (! empty($sm->attachment_ids))<span class="block"><x-nx.icon name="file-text" style="font-size:12px" /> {{ count($sm->attachment_ids) }} {{ count($sm->attachment_ids) === 1 ? 'file' : 'files' }}</span>@endif
                            @if ($sm->body !== '')<span class="block break-words">{{ \App\Support\N2N\ChatFormat::plain($sm->body) }}</span>@endif
                        </span>
                        @if ($sm->status === 'failed')
                            <button type="button" class="nn__iconbtn" wire:click="dismissScheduled({{ $sm->id }})" aria-label="Dismiss"><x-nx.icon name="x" style="font-size:14px" /></button>
                        @elseif ($sm->status === 'pending')
                            <button type="button" class="nc__chip" wire:click="cancelScheduled({{ $sm->id }})" wire:confirm="Cancel this scheduled message?">Cancel</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

    @elseif ($panel === 'storage')
        @php($u = $pd['usage'])
        <p class="nc__phint"><b>{{ $u['count'] }}</b> {{ $u['count'] === 1 ? 'item' : 'items' }} in this chat, <b>{{ \App\Services\N2N\Messaging\ChatLibrary::human($u['bytes']) }}</b>. Removing an item hides it for you only. The other person keeps their copy, and the file is deleted from storage only once you have both removed it. This may not lower any cost, so do not rely on it for savings.</p>
        @if (empty($pd['items']))
            <p class="nc__phint">Nothing to manage here yet.</p>
        @else
            <ul class="nc__plist">
                @foreach ($pd['items'] as $it)
                    <li wire:key="sm-{{ $it['id'] }}" class="nc__prow">
                        <label class="nc__pcheck"><input type="checkbox" wire:model.live="selected" value="{{ $it['id'] }}">
                            <span><b>{{ ['image' => 'Photo', 'video' => 'Video', 'voice' => 'Voice note'][$it['kind']] ?? 'File' }}</b> <small>{{ $it['mine'] ? 'You' : ($peer?->name ?: 'Them') }}, {{ $it['at']?->format('d M Y') }}</small></span></label>
                        <span class="nc__psize">{{ \App\Services\N2N\Messaging\ChatLibrary::human($it['bytes']) }}</span>
                    </li>
                @endforeach
            </ul>
            <button type="button" wire:click="hideSelected" wire:confirm="Remove the selected items for you?" @disabled(count($selected) === 0) class="ns-cta ns-cta--pill mt-3" wire:loading.attr="disabled" wire:target="hideSelected">Remove {{ count($selected) ?: '' }} for me</button>
        @endif
    @endif
    </div>
</div>
