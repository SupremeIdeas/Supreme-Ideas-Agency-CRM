{{-- nx:converted (skin canvas + tokens) --}}
@php
    $card = 'mb-6 rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-6';
    $inp = 'w-full rounded-xl border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3.5 py-2.5 text-sm text-[rgb(var(--nx-text))]';
    $usd = fn ($v) => '$'.rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
    $statusText = ['stored' => 'Saved', 'received' => 'Saving', 'unfunded' => 'Not saved: wallet balance was too low', 'failed' => 'Not saved: it failed and you were not charged', 'purged' => 'Deleted'];
@endphp
<div>
<x-nx.page class="px-0">
<div class="mx-auto max-w-3xl pb-28">
    <div class="mb-6 flex items-center gap-3">
        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))]"><x-icon name="mic" class="h-5 w-5" /></span>
        <div>
            <h1 class="font-display text-2xl font-extrabold text-[rgb(var(--nx-text))]">Call recordings</h1>
            <p class="text-sm text-[rgb(var(--nx-text-2))]">{{ $group->name }}. Only you and your workspace admins can hear these.</p>
        </div>
    </div>

    <section class="{{ $card }}" aria-labelledby="rec-settings">
        <h2 id="rec-settings" class="mb-1 text-sm font-bold text-[rgb(var(--nx-text))]">Settings</h2>
        <p class="mb-4 text-xs text-[rgb(var(--nx-text-2))]">Recording is off until you turn it on. Every recorded call starts with a spoken notice, and in some countries the caller must press 1 first. You are responsible for telling your callers how you use recordings.</p>

        <form wire:submit="save" class="space-y-5">
            <label class="flex items-start gap-3 text-sm text-[rgb(var(--nx-text))]">
                <input type="checkbox" wire:model.live="recording" @disabled(! $recordingLive) class="mt-1 h-4 w-4 rounded">
                <span><b>Record calls on my numbers</b>
                    <span class="block text-xs text-[rgb(var(--nx-text-2))]">About {{ $usd($prices['record']) }} per minute, from your wallet. Calls are only recorded while your wallet can pay for them.</span>
                    @unless ($recordingLive)<span class="block text-xs text-[rgb(var(--nx-warn))]">Call recording is not available on this platform yet.</span>@endunless
                </span>
            </label>

            @if ($recording && $recordingLive)
                <div class="space-y-5 border-t border-[rgb(var(--nx-line))] pt-5">
                    <div>
                        <p class="mb-2 text-xs font-semibold text-[rgb(var(--nx-text-2))]">Which numbers</p>
                        @forelse ($lines as $n)
                            <label class="flex items-center gap-3 py-1.5 text-sm text-[rgb(var(--nx-text))]" wire:key="line-{{ $n->id }}">
                                <input type="checkbox" wire:model="numbers.{{ $n->id }}" class="h-4 w-4 rounded"> {{ $n->phone_number }}
                            </label>
                        @empty
                            <p class="text-xs text-[rgb(var(--nx-text-2))]">Add a number to your workspace first (Call flows).</p>
                        @endforelse
                    </div>

                    <div>
                        <label for="rec-retention" class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Keep recordings for</label>
                        <select id="rec-retention" wire:model="retention" class="{{ $inp }} sm:w-48">
                            @foreach ($this->retentionOptions() as $d)<option value="{{ $d }}">{{ $d }} days</option>@endforeach
                        </select>
                        <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">After that they are deleted automatically, with their transcripts and summaries.</p>
                        @error('retention')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]" role="alert">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="rec-sensitive" class="mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]">Never record these numbers</label>
                        <textarea id="rec-sensitive" wire:model="sensitive" rows="2" placeholder="+2348012345678, +14155550123" class="{{ $inp }}"></textarea>
                        <p class="mt-1 text-xs text-[rgb(var(--nx-text-2))]">For health, legal or financial lines. Emergency numbers are never recorded.</p>
                        @error('sensitive')<p class="mt-1 text-xs text-[rgb(var(--nx-bad))]" role="alert">{{ $message }}</p>@enderror
                    </div>

                    <label class="flex items-start gap-3 text-sm text-[rgb(var(--nx-text))]">
                        <input type="checkbox" wire:model="intelligence" @disabled(! $intelligenceLive) class="mt-1 h-4 w-4 rounded">
                        <span><b>Transcripts and call summaries</b>
                            <span class="block text-xs text-[rgb(var(--nx-text-2))]">A written transcript ({{ $usd($prices['transcribe']) }} per minute) and a short summary with sentiment and coaching notes ({{ $usd($prices['intelligence']) }} per call). The text is processed by our speech and AI suppliers and is never used to train their models. Phone numbers are not sent.</span>
                            @unless ($intelligenceLive)<span class="block text-xs text-[rgb(var(--nx-warn))]">Not available on this platform yet.</span>@endunless
                        </span>
                    </label>

                    <label class="flex items-start gap-3 text-sm text-[rgb(var(--nx-text))]">
                        <input type="checkbox" wire:model="legalHold" class="mt-1 h-4 w-4 rounded">
                        <span><b>Legal hold</b>
                            <span class="block text-xs text-[rgb(var(--nx-text-2))]">Pauses automatic deletion while you need to keep recordings. Switching it on and off is recorded.</span></span>
                    </label>
                </div>
            @endif

            <div class="flex items-center gap-3">
                <button type="submit" class="ns-cta ns-cta--pill ns-cta--sm" wire:loading.attr="disabled" wire:target="save"><span wire:loading.remove wire:target="save">Save</span><span wire:loading wire:target="save">Saving...</span></button>
                @if ($notice)<span role="status" class="text-xs text-[rgb(var(--nx-ok))]">{{ $notice }}</span>@endif
            </div>
        </form>
    </section>

    <section class="{{ $card }}" aria-labelledby="rec-list">
        <h2 id="rec-list" class="mb-4 text-sm font-bold text-[rgb(var(--nx-text))]">Recordings</h2>
        @forelse ($recordings as $r)
            <div class="border-b border-[rgb(var(--nx-line))] py-3 last:border-0" wire:key="rec-{{ $r->id }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $r->created_at->format('j M Y, H:i') }} · {{ gmdate($r->duration_seconds >= 3600 ? 'G:i:s' : 'i:s', $r->duration_seconds) }}</p>
                        <p class="text-xs text-[rgb(var(--nx-text-2))]">{{ $r->virtualNumber?->phone_number }}@if ($r->source === 'ai_agent') · AI assistant call (transcript only)@endif · {{ $statusText[$r->status] ?? $r->status }}@if ($r->expires_at && $r->status === 'stored') · kept until {{ $r->expires_at->format('j M Y') }}@endif</p>
                    </div>
                    <div class="flex items-center gap-1">
                        @if ($r->transcript || $r->intelligence)
                            <button type="button" wire:click="toggle({{ $r->id }})" aria-expanded="{{ $open === $r->id ? 'true' : 'false' }}" class="rounded-full px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-teal-ink))] hover:bg-[rgb(var(--nx-teal)/0.1)]">{{ $open === $r->id ? 'Hide text' : 'Read' }}</button>
                        @endif
                        @if ($r->status !== 'purged')
                            <button type="button" wire:click="delete({{ $r->id }})" wire:confirm="Delete this recording and its transcript for good?" wire:loading.attr="disabled" wire:target="delete({{ $r->id }})" aria-label="Delete recording" class="flex h-8 w-8 items-center justify-center rounded-full text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-bad)/0.1)] hover:text-[rgb(var(--nx-bad))] disabled:opacity-60"><x-icon name="trash" class="h-4 w-4" /></button>
                        @endif
                    </div>
                </div>
                @if ($r->isPlayable())
                    <audio controls preload="none" class="mt-2 w-full" src="{{ route('business.recordings.audio', $r) }}"></audio>
                @endif
                @if ($r->transcript_status === 'pending' || $r->transcript_status === 'processing' || $r->intelligence_status === 'processing')
                    <p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">The transcript is being prepared.</p>
                @elseif ($r->transcript_status === 'failed' || $r->intelligence_status === 'failed')
                    <p class="mt-2 text-xs text-[rgb(var(--nx-warn))]">The transcript or summary could not be made. You were not charged for the part that failed.</p>
                @endif
                @if ($open === $r->id)
                    <div class="mt-3 space-y-3 rounded-xl bg-[rgb(var(--nx-teal)/0.06)] p-4 text-sm text-[rgb(var(--nx-text))]">
                        @if ($r->intelligence)
                            <p><b>Summary.</b> {{ $r->intelligence->summary_text }}</p>
                            <p class="text-xs text-[rgb(var(--nx-text-2))]">Sentiment: {{ $r->intelligence->sentiment }}@if ($r->intelligence->keywords) · {{ implode(', ', $r->intelligence->keywords) }}@endif</p>
                            @if ($r->intelligence->coaching_notes)<p><b>Coaching.</b> {{ $r->intelligence->coaching_notes }}</p>@endif
                        @endif
                        @if ($r->transcript)<p class="whitespace-pre-line text-[rgb(var(--nx-text-2))]">{{ $r->transcript->transcript_text }}</p>@endif
                    </div>
                @endif
            </div>
        @empty
            <x-nx.empty art="bs-recording" title="No recordings yet" text="Recorded calls on your numbers show up here." />
        @endforelse
    </section>

    <a href="{{ route('business.workspace') }}" wire:navigate class="text-sm font-semibold text-[rgb(var(--nx-teal-ink))] hover:underline">Back to the workspace</a>
</div>
</x-nx.page>
</div>
