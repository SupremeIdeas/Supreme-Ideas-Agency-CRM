{{-- nx:converted (skin canvas + tokens) --}}
@php
    $card = 'rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-4';
    $inp = 'w-full rounded-xl border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface-2))] px-3 py-2 text-sm text-[rgb(var(--nx-text))]';
    $lbl = 'mb-1 block text-xs font-semibold text-[rgb(var(--nx-text-2))]';
    $btn = 'rounded-full border border-[rgb(var(--nx-line-strong))] px-3 py-1.5 text-xs font-semibold text-[rgb(var(--nx-text))]';
    $h2 = 'mb-1 text-sm font-bold text-[rgb(var(--nx-text))]';
    $hint = 'mb-3 text-xs text-[rgb(var(--nx-text-2))]';
    $tabs = ['knowledge' => 'Knowledge', 'agent' => 'AI assistant', 'flows' => 'Flows', 'broadcasts' => 'Broadcasts', 'integrations' => 'Webhooks', 'insights' => 'Insights'];
@endphp
<div>
<x-nx.page class="px-0">
<div class="mx-auto max-w-4xl space-y-4 pb-28" data-respond-automation>
    <div class="flex items-center gap-3 px-1">
        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[rgb(var(--nx-cta-b)/0.1)] text-[rgb(var(--nx-teal-ink))]"><x-icon name="sparkles" class="h-5 w-5" /></span>
        <div class="min-w-0 flex-1">
            <h1 class="font-display text-2xl font-extrabold text-[rgb(var(--nx-text))]">Respond automation</h1>
            <p class="text-sm text-[rgb(var(--nx-text-2))]">Teach it your business, let it answer, and run follow-ups for you.</p>
        </div>
        <a href="{{ route('business.respond.templates') }}" wire:navigate class="{{ $btn }}">Templates</a>
        <a href="{{ route('business.respond') }}" wire:navigate class="{{ $btn }}">Inbox</a>
    </div>

    <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="Automation sections" data-tour="automation.tabs">
        @foreach ($tabs as $k => $l)
            <button type="button" role="tab" aria-selected="{{ $tab === $k ? 'true' : 'false' }}" wire:click="setTab('{{ $k }}')" data-auto-tab="{{ $k }}" data-tour="automation.tab-{{ $k }}"
                    @class(['rounded-full border px-3 py-1.5 text-xs font-semibold', 'border-[rgb(var(--nx-teal))] bg-[rgb(var(--nx-teal)/0.18)] text-[rgb(var(--nx-text))]' => $tab === $k, 'border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-text))]' => $tab !== $k])>{{ $l }}</button>
        @endforeach
    </div>

    @if ($ok)<p class="rounded-xl bg-[rgb(var(--nx-ok)/0.12)] px-3 py-2 text-sm text-[rgb(var(--nx-ok))]" role="status">{{ $ok }}</p>@endif
    @if ($error)<p class="rounded-xl bg-[rgb(var(--nx-bad)/0.1)] px-3 py-2 text-sm text-[rgb(var(--nx-bad))]" role="alert">{{ $error }}</p>@endif

    {{-- KNOWLEDGE --}}
    @if ($tab === 'knowledge')
        <section class="{{ $card }}" aria-label="Upload documents" data-upload-card data-tour="automation.knowledge">
            <h2 class="{{ $h2 }}">Teach it from your own documents</h2>
            <p class="{{ $hint }}">Upload your FAQ, price list, policies, brand story or product details as <b>.txt, .md or .docx</b> (up to {{ $limits['max_mb'] }} MB each, at least {{ number_format($limits['min_bytes']) }} bytes, up to {{ $limits['max_files'] }} files at once). Your assistant learns from your files only, and only for your workspace: nothing is shared with any other business. We keep the text we read, not the file itself.</p>
            <form wire:submit="uploadDocument" class="space-y-2">
                <div class="grid gap-2 sm:grid-cols-[1fr_14rem]">
                    <input type="file" wire:model="uploads" multiple accept=".txt,.md,.docx" aria-label="Documents to upload" data-upload-input class="{{ $inp }}">
                    <select wire:model="uploadRole" aria-label="What this document is" class="{{ $inp }}">
                        <option value="facts">Facts about my business</option>
                        <option value="voice">How we write (brand voice samples)</option>
                    </select>
                </div>
                <div wire:loading wire:target="uploads" class="text-xs text-[rgb(var(--nx-text-2))]" role="status">Uploading...</div>
                <button type="submit" wire:loading.attr="disabled" wire:target="uploadDocument,uploads" class="ns-cta ns-cta--pill ns-cta--sm"><span wire:loading.remove wire:target="uploadDocument">Read and learn these files</span><span wire:loading wire:target="uploadDocument">Reading...</span></button>
                @error('uploads')<p class="text-xs text-[rgb(var(--nx-bad))]" role="alert">{{ $message }}</p>@enderror
                @error('uploads.*')<p class="text-xs text-[rgb(var(--nx-bad))]" role="alert">{{ $message }}</p>@enderror
            </form>
            <p class="mt-3 text-xs text-[rgb(var(--nx-text-2))]">Safety checks on every file: real file type, no macros, no passwords, card numbers or API keys inside (they are refused, never learned), instruction-style lines removed, duplicates refused, and a learning check that proves the assistant can find what is in it.</p>

            @if (count($results) > 1)
                <ul class="mt-4 space-y-1 text-sm" data-upload-results>
                    @foreach ($results as $r)
                        <li class="flex flex-wrap gap-x-2 rounded-lg px-3 py-1.5 {{ $r['ok'] ? 'bg-[rgb(var(--nx-ok)/0.1)] text-[rgb(var(--nx-ok))]' : 'bg-[rgb(var(--nx-bad)/0.1)] text-[rgb(var(--nx-bad))]' }}"><b>{{ $r['name'] }}</b><span>{{ $r['message'] }}</span></li>
                    @endforeach
                </ul>
            @endif

            @if ($report)
                <div class="mt-4 rounded-xl border border-[rgb(var(--nx-ok)/0.4)] bg-[rgb(var(--nx-ok)/0.07)] p-3 text-sm" data-learning-report role="status">
                    <b class="text-[rgb(var(--nx-text))]">What we learned from "{{ $report['title'] }}"</b>
                    <ul class="mt-1 list-disc pl-5 text-[rgb(var(--nx-text))]">
                        <li>{{ number_format($report['words']) }} words read{{ $report['role'] === 'facts' ? ', cut into '.$report['chunks'].' searchable passages' : ' as brand voice (used for tone only, never as facts)' }}.</li>
                        @if ($report['role'] === 'facts' && $report['total'])<li>Learning check: <b>{{ $report['passed'] }} of {{ $report['total'] }}</b> passages found again by a question made from their own words.</li> @endif
                        @foreach (['emails' => 'Emails', 'phones' => 'Phone numbers', 'links' => 'Links', 'prices' => 'Prices', 'hours' => 'Opening hours', 'headings' => 'Sections', 'questions' => 'Questions answered'] as $k => $label)
                            @if (! empty($report['facts'][$k]))<li>{{ $label }} found: <span class="text-[rgb(var(--nx-text-2))]">{{ \Illuminate\Support\Str::limit(implode(' | ', array_slice($report['facts'][$k], 0, 4)), 200) }}</span></li>@endif
                        @endforeach
                        @foreach ($report['notes'] as $n)<li class="text-[rgb(var(--nx-warn))]">{{ $n }}</li>@endforeach
                    </ul>
                    <p class="mt-2 text-xs text-[rgb(var(--nx-text-2))]">If something above looks wrong or missing, fix the file and upload it again. Next, test it below.</p>
                </div>
            @endif
        </section>

        <section class="{{ $card }}" aria-label="Test your assistant" data-test-assistant>
            <h2 class="{{ $h2 }}">Test your assistant</h2>
            <p class="{{ $hint }}">Ask a question a customer might ask. Nothing is sent to anyone. You see the exact passages from your documents it used, and the reply it would write from them.</p>
            <form wire:submit="askAssistant" class="flex gap-2">
                <input type="text" wire:model="ask" maxlength="500" placeholder="For example: How long does delivery take?" aria-label="Test question" class="{{ $inp }} flex-1">
                <button type="submit" wire:loading.attr="disabled" wire:target="askAssistant,tryQuestion" class="ns-cta ns-cta--pill ns-cta--sm"><span wire:loading.remove wire:target="askAssistant,tryQuestion">Ask</span><span wire:loading wire:target="askAssistant,tryQuestion">Thinking...</span></button>
            </form>
            @if ($suggested)
                <div class="mt-2 flex flex-wrap gap-1.5" aria-label="Questions from your documents">
                    @foreach ($suggested as $q)<button type="button" wire:click="tryQuestion(@js($q))" class="rounded-full border border-[rgb(var(--nx-line-strong))] px-2.5 py-1 text-xs text-[rgb(var(--nx-text))]">{{ \Illuminate\Support\Str::limit($q, 60) }}</button>@endforeach
                </div>
            @endif
            @if ($answer)
                <div class="mt-3 space-y-2 text-sm" data-test-result="{{ $answer['decision'] }}" role="status">
                    <p class="rounded-xl bg-[rgb(var(--nx-surface-2))] px-3 py-2 text-[rgb(var(--nx-text))]">{{ $answer['note'] }}</p>
                    @if ($answer['reply'])<div class="rounded-xl bg-[rgb(var(--nx-cta-b)/0.12)] px-3 py-2 text-[rgb(var(--nx-text))]"><b class="block text-xs">Reply it would write @if ($answer['voice']) <span class="font-normal">(in your brand voice)</span>@endif</b><span class="whitespace-pre-wrap">{{ $answer['reply'] }}</span></div>@endif
                    @foreach ($answer['passages'] as $p)
                        <div class="rounded-xl border border-[rgb(var(--nx-line))] px-3 py-2 text-xs text-[rgb(var(--nx-text-2))]"><b class="text-[rgb(var(--nx-text))]">From: {{ $p['title'] }}</b><p class="mt-0.5 whitespace-pre-wrap">{{ \Illuminate\Support\Str::limit($p['content'], 400) }}</p></div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="{{ $card }}" aria-label="Knowledge base">
            <h2 class="{{ $h2 }}">Or type it in</h2>
            <p class="{{ $hint }}">Add a single FAQ, some notes, or read a page from your website.</p>
            <form wire:submit="addKbText" class="space-y-2">
                <div class="grid gap-2 sm:grid-cols-[8rem_1fr]">
                    <select wire:model="kbKind" aria-label="Type" class="{{ $inp }}"><option value="faq">FAQ</option><option value="text">Notes</option></select>
                    <input type="text" wire:model="kbTitle" maxlength="160" placeholder="Title, for example Delivery times" aria-label="Title" class="{{ $inp }}">
                </div>
                <textarea wire:model="kbText" rows="4" maxlength="60000" placeholder="Write the answer or paste the policy" aria-label="Text" class="{{ $inp }}"></textarea>
                <button type="submit" wire:loading.attr="disabled" wire:target="addKbText" class="ns-cta ns-cta--pill ns-cta--sm"><span wire:loading.remove wire:target="addKbText">Add to knowledge</span><span wire:loading wire:target="addKbText">Adding...</span></button>
            </form>
            <form wire:submit="addKbUrl" class="mt-4 flex gap-2 border-t border-[rgb(var(--nx-line))] pt-4">
                <input type="url" wire:model="kbUrl" placeholder="https://yourshop.com/delivery" aria-label="Web page address" class="{{ $inp }} flex-1">
                <button type="submit" wire:loading.attr="disabled" wire:target="addKbUrl" class="{{ $btn }}"><span wire:loading.remove wire:target="addKbUrl">Read page</span><span wire:loading wire:target="addKbUrl">Reading...</span></button>
            </form>
        </section>
        <section class="{{ $card }}" aria-label="Sources">
            <h2 class="{{ $h2 }}">What your assistant has learned ({{ $sources->count() }})</h2>
            @forelse ($sources as $s)
                <div wire:key="kb-{{ $s->id }}" class="border-t border-[rgb(var(--nx-line)/0.6)] py-2 first:border-0">
                    <div class="flex items-center gap-2">
                        <div class="min-w-0 flex-1">
                            <b class="block truncate text-sm text-[rgb(var(--nx-text))]">{{ $s->title }}</b>
                            <small class="text-[rgb(var(--nx-text-2))]">{{ ['faq' => 'FAQ', 'text' => 'Notes', 'url' => 'Web page', 'file' => 'File'][$s->kind] ?? strtoupper($s->kind) }}@if ($s->role === 'voice') · Brand voice @endif @if ($s->file_name) · {{ $s->file_name }}@endif @if ($s->url) · {{ $s->url }}@endif @if ($s->word_count) · {{ number_format($s->word_count) }} words @endif @if ($s->chunk_count) · {{ $s->chunk_count }} passages @endif @if ($s->status === 'failed') · <span class="text-[rgb(var(--nx-bad))]">{{ $s->error }}</span>@endif</small>
                            @if ($s->check_total)<small class="block {{ $s->check_passed === $s->check_total ? 'text-[rgb(var(--nx-ok))]' : 'text-[rgb(var(--nx-warn))]' }}" data-source-check>Learning check: {{ $s->check_passed }} of {{ $s->check_total }} found @if ($s->check_passed === $s->check_total)(fully learned) @else (some passages are hard to find: add clearer sentences) @endif</small>@endif
                        </div>
                        @if ($s->role === 'facts' && $s->kind !== 'url')<button type="button" wire:click="recheck({{ $s->id }})" wire:loading.attr="disabled" class="{{ $btn }}">Re-check</button>@endif
                        @if ($s->kind === 'url')<button type="button" wire:click="refreshKb({{ $s->id }})" wire:loading.attr="disabled" class="{{ $btn }}">Refresh</button>@endif
                        <button type="button" wire:click="removeKb({{ $s->id }})" wire:confirm="Remove this source? The assistant will forget it." class="{{ $btn }}">Remove</button>
                    </div>
                    @foreach ((array) $s->notes as $n)<p class="mt-1 text-xs text-[rgb(var(--nx-warn))]">{{ $n }}</p>@endforeach
                </div>
            @empty
                <p class="text-sm text-[rgb(var(--nx-text-2))]">Nothing yet. Upload a document above.</p>
            @endforelse
        </section>
    @endif

    {{-- AGENT --}}
    @if ($tab === 'agent')
        <section class="{{ $card }}" aria-label="AI assistant">
            <h2 class="{{ $h2 }}">AI assistant</h2>
            <p class="{{ $hint }}">It answers from your knowledge, says so when it does not know, and passes anything sensitive to your team. Start with suggestions you approve.</p>
            @if ($allowance)
                <p class="mb-3 rounded-xl bg-[rgb(var(--nx-surface-2))] px-3 py-2 text-xs text-[rgb(var(--nx-text))]" data-ai-allowance>
                    @if ($allowance['available'] || $allowance['allowance'] > 0)<b>{{ number_format($allowance['used']) }} of {{ number_format($allowance['allowance']) }}</b> assistant actions used this month. AI replies and inbox drafts share this allowance. It resets on the 1st.@else The assistant is not part of your plan yet.@endif
                </p>
            @endif
            <form wire:submit="saveAgent" class="space-y-3">
                <fieldset>
                    <legend class="{{ $lbl }}">Mode</legend>
                    <div class="grid gap-2 sm:grid-cols-3">
                        @foreach (['off' => ['Off', 'Your team answers everything.'], 'suggest' => ['Suggest', 'Drafts a reply, you approve it.'], 'auto' => ['Auto', 'Answers by itself from your knowledge.']] as $k => [$t, $d])
                            <label @class(['cursor-pointer rounded-xl border p-3 text-xs', 'border-[rgb(var(--nx-teal))] bg-[rgb(var(--nx-teal)/0.12)]' => $mode === $k, 'border-[rgb(var(--nx-line-strong))]' => $mode !== $k])>
                                <input type="radio" wire:model.live="mode" value="{{ $k }}" class="sr-only"><b class="block text-sm text-[rgb(var(--nx-text))]">{{ $t }}</b><span class="text-[rgb(var(--nx-text-2))]">{{ $d }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div><label class="{{ $lbl }}" for="ag-tone">Tone</label><select id="ag-tone" wire:model="tone" class="{{ $inp }}"><option value="friendly">Friendly</option><option value="formal">Formal</option><option value="short">Short and direct</option></select></div>
                    <div><label class="{{ $lbl }}" for="ag-cap">Daily limit (AI replies per day)</label><input id="ag-cap" type="number" min="1" max="5000" wire:model="cap" class="{{ $inp }}"></div>
                </div>
                <div><label class="{{ $lbl }}" for="ag-sum">About your business (one or two sentences)</label><textarea id="ag-sum" rows="2" maxlength="1000" wire:model="summary" class="{{ $inp }}"></textarea></div>
                <div><label class="{{ $lbl }}" for="ag-words">Hand over to a person when the customer says</label><input id="ag-words" type="text" wire:model="handoff" class="{{ $inp }}"></div>
                <button type="submit" wire:loading.attr="disabled" wire:target="saveAgent" class="ns-cta ns-cta--pill ns-cta--sm"><span wire:loading.remove wire:target="saveAgent">Save</span><span wire:loading wire:target="saveAgent">Saving...</span></button>
            </form>
        </section>
        <section class="{{ $card }}" aria-label="Recent AI decisions">
            <h2 class="{{ $h2 }}">What it did recently</h2>
            @forelse ($runs as $r)
                <div wire:key="run-{{ $r->id }}" class="border-t border-[rgb(var(--nx-line)/0.6)] py-2 text-xs first:border-0">
                    <b class="text-[rgb(var(--nx-text))]">{{ ['answered' => 'Answered', 'suggested' => 'Suggested', 'handoff' => 'Handed to team', 'refused' => 'Refused and handed over', 'capped' => 'Daily limit reached', 'error' => 'Error'][$r->outcome] ?? $r->outcome }}</b>
                    <span class="text-[rgb(var(--nx-text-2))]">· {{ $r->created_at?->diffForHumans() }}@if ($r->note) · {{ $r->note }}@endif</span>
                    @if ($r->reply)<p class="mt-0.5 text-[rgb(var(--nx-text-2))]">{{ \Illuminate\Support\Str::limit($r->reply, 160) }}</p>@endif
                </div>
            @empty
                <p class="text-sm text-[rgb(var(--nx-text-2))]">Nothing yet.</p>
            @endforelse
        </section>
    @endif

    {{-- FLOWS --}}
    @if ($tab === 'flows')
        <section class="{{ $card }}" aria-label="New flow">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="{{ $h2 }}">Flows</h2>
                <button type="button" wire:click="installStarters" class="{{ $btn }}">Add starter flows</button>
            </div>
            <p class="{{ $hint }}">A flow starts on a trigger and runs its steps for you. New flows are drafts: test one, then turn it on.</p>
            <form wire:submit="saveFlow" class="space-y-2">
                <input type="text" wire:model="fName" maxlength="120" placeholder="Name, for example Pricing question" aria-label="Flow name" class="{{ $inp }}">
                <div class="grid gap-2 sm:grid-cols-2">
                    <select wire:model.live="fTrigger" aria-label="Trigger" class="{{ $inp }}">@foreach ($flowTriggers as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                    @if ($fTrigger !== 'new_conversation')<input type="text" wire:model="fValue" placeholder="{{ $fTrigger === 'keyword' ? 'price, how much' : '09:00-17:00' }}" aria-label="Trigger detail" class="{{ $inp }}">@endif
                </div>
                @foreach ($fSteps as $i => $st)
                    <div wire:key="step-{{ $i }}" class="flex items-center gap-2 rounded-xl bg-[rgb(var(--nx-surface-2))] px-3 py-1.5 text-xs text-[rgb(var(--nx-text))]">
                        <b>{{ $i + 1 }}.</b> <span class="min-w-0 flex-1 truncate">{{ ['send_text' => 'Send', 'delay' => 'Wait (min)', 'set_status' => 'Set status', 'add_tag' => 'Tag', 'note' => 'Note'][$st['type']] }}: {{ $st['text'] ?? ($st['minutes'] ?? ($st['status'] ?? ($st['tag'] ?? ''))) }}</span>
                        <button type="button" wire:click="removeStep({{ $i }})" aria-label="Remove step {{ $i + 1 }}" class="text-[rgb(var(--nx-text-2))]">&times;</button>
                    </div>
                @endforeach
                <div class="grid gap-2 sm:grid-cols-[9rem_1fr_auto]">
                    <select wire:model="stepType" aria-label="Step type" class="{{ $inp }}"><option value="send_text">Send a message</option><option value="delay">Wait minutes</option><option value="set_status">Set status</option><option value="add_tag">Tag contact</option><option value="note">Private note</option></select>
                    <input type="text" wire:model="stepValue" placeholder="Text, minutes, open/pending/resolved, or tag" aria-label="Step value" class="{{ $inp }}">
                    <button type="button" wire:click="addStep" class="{{ $btn }}">Add step</button>
                </div>
                <button type="submit" wire:loading.attr="disabled" wire:target="saveFlow" class="ns-cta ns-cta--pill ns-cta--sm">Save flow</button>
            </form>
        </section>
        <section class="{{ $card }}" aria-label="Your flows">
            @forelse ($flows as $f)
                <div wire:key="flow-{{ $f->id }}" class="flex flex-wrap items-center gap-2 border-t border-[rgb(var(--nx-line)/0.6)] py-2 first:border-0">
                    <div class="min-w-0 flex-1"><b class="block truncate text-sm text-[rgb(var(--nx-text))]">{{ $f->name }}</b>
                        <small class="text-[rgb(var(--nx-text-2))]">{{ $flowTriggers[$f->trigger_type] ?? $f->trigger_type }}@if ($f->trigger_value) ({{ $f->trigger_value }})@endif · {{ count((array) $f->steps) }} steps · ran {{ $f->runs_count }}x</small></div>
                    <span @class(['rounded-full px-2 py-0.5 text-[10px] font-bold', 'bg-[rgb(var(--nx-ok)/0.15)] text-[rgb(var(--nx-ok))]' => $f->status === 'live', 'bg-[rgb(var(--nx-surface-2))] text-[rgb(var(--nx-text-2))]' => $f->status !== 'live'])>{{ $f->status === 'live' ? 'On' : 'Draft' }}</span>
                    <button type="button" wire:click="testFlow({{ $f->id }})" class="{{ $btn }}">Test</button>
                    <button type="button" wire:click="toggleFlow({{ $f->id }})" class="{{ $btn }}">{{ $f->status === 'live' ? 'Turn off' : 'Turn on' }}</button>
                    <button type="button" wire:click="deleteFlow({{ $f->id }})" wire:confirm="Delete this flow?" class="{{ $btn }}">Delete</button>
                </div>
            @empty
                <p class="text-sm text-[rgb(var(--nx-text-2))]">No flows yet. Add the starter pack or build one above.</p>
            @endforelse
            @if ($trace)
                <div class="mt-3 rounded-xl bg-[rgb(var(--nx-teal)/0.06)] p-3 text-xs text-[rgb(var(--nx-text))]" role="status" data-flow-trace>
                    <b>Test run (nothing was sent)</b>
                    @foreach ($trace as $t)<p>{{ $t['step'] }}. {{ $t['result'] }}</p>@endforeach
                </div>
            @endif
        </section>
    @endif

    {{-- BROADCASTS --}}
    @if ($tab === 'broadcasts')
        <section class="{{ $card }}" aria-label="New broadcast">
            <h2 class="{{ $h2 }}">Broadcast</h2>
            <p class="{{ $hint }}">One message to people who agreed to hear from you ({{ $optedIn }} right now). Anyone who replies STOP is removed at once. Channels that need approved templates (WhatsApp, Instagram, Messenger) open when those connect.</p>
            <form wire:submit="sendBroadcast" class="space-y-2">
                <div class="grid gap-2 sm:grid-cols-2">
                    <input type="text" wire:model="bName" maxlength="120" placeholder="Name" aria-label="Broadcast name" class="{{ $inp }}">
                    <select wire:model="bConn" aria-label="Channel" class="{{ $inp }}"><option value="">Choose a channel</option>@foreach ($connections as $c)<option value="{{ $c->id }}">{{ \App\Models\ChannelConnection::label($c->channel) }}: {{ $c->label }}</option>@endforeach</select>
                </div>
                <textarea wire:model="bBody" rows="3" maxlength="1000" placeholder="Your message" aria-label="Message" class="{{ $inp }}"></textarea>
                <div class="grid gap-2 sm:grid-cols-3">
                    <input type="text" wire:model="bTag" placeholder="Only contacts tagged..." aria-label="Tag" class="{{ $inp }}">
                    <select wire:model="bStage" aria-label="Stage" class="{{ $inp }}"><option value="">Any stage</option><option value="new">New</option><option value="lead">Lead</option><option value="customer">Customer</option></select>
                    <input type="datetime-local" wire:model="bWhen" aria-label="Send later" class="{{ $inp }}">
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="previewBroadcast" wire:loading.attr="disabled" class="{{ $btn }}">Preview who gets it</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="sendBroadcast" class="ns-cta ns-cta--pill ns-cta--sm"><span wire:loading.remove wire:target="sendBroadcast">{{ trim($bWhen) !== '' ? 'Schedule' : 'Send now' }}</span><span wire:loading wire:target="sendBroadcast">Working...</span></button>
                </div>
            </form>
            @if ($dry)
                <div class="mt-3 rounded-xl bg-[rgb(var(--nx-teal)/0.06)] p-3 text-xs text-[rgb(var(--nx-text))]" data-broadcast-preview>
                    @if ($dry['blocked'])<b class="text-[rgb(var(--nx-warn))]">{{ $dry['blocked'] }}</b>
                    @else<b>{{ $dry['recipients'] }} {{ \Illuminate\Support\Str::plural('person', $dry['recipients']) }} would receive this.</b>@if ($dry['cost'] > 0) Estimated cost {{ number_format($dry['cost'], 2) }}.@endif @if ($dry['sample']) <span class="text-[rgb(var(--nx-text-2))]">For example: {{ implode(', ', $dry['sample']) }}.</span>@endif @endif
                </div>
            @endif
        </section>
        <section class="{{ $card }}" aria-label="Past broadcasts">
            @forelse ($broadcasts as $b)
                <div wire:key="bc-{{ $b->id }}" class="flex flex-wrap items-center gap-2 border-t border-[rgb(var(--nx-line)/0.6)] py-2 text-xs first:border-0">
                    <b class="min-w-0 flex-1 truncate text-sm text-[rgb(var(--nx-text))]">{{ $b->name }}</b>
                    <span class="text-[rgb(var(--nx-text-2))]">{{ ['draft' => 'Draft', 'scheduled' => 'Scheduled '.$b->scheduled_at?->format('d M H:i'), 'sending' => 'Sending', 'done' => 'Done', 'cancelled' => 'Cancelled'][$b->status] }} · {{ (int) ($b->stats['sent'] ?? 0) }}/{{ (int) ($b->stats['total'] ?? 0) }} sent</span>
                    @if (in_array($b->status, ['scheduled', 'sending'], true))<button type="button" wire:click="cancelBroadcast({{ $b->id }})" class="{{ $btn }}">Cancel</button>@endif
                </div>
            @empty
                <p class="text-sm text-[rgb(var(--nx-text-2))]">No broadcasts yet.</p>
            @endforelse
        </section>
    @endif

    {{-- WEBHOOKS --}}
    @if ($tab === 'integrations')
        <section class="{{ $card }}" aria-label="Webhooks">
            <h2 class="{{ $h2 }}">Webhooks</h2>
            <p class="{{ $hint }}">Send events to Zapier, Make, n8n or your own server. Each call is signed with your secret (X-Naara-Signature) so you can check it came from us.</p>
            <form wire:submit="addWebhook" class="space-y-2">
                <input type="url" wire:model="whUrl" placeholder="https://hooks.example.com/naara" aria-label="Webhook address" class="{{ $inp }}">
                <div class="flex flex-wrap gap-3 text-xs text-[rgb(var(--nx-text))]">@foreach ($events as $e)<label class="inline-flex items-center gap-1.5"><input type="checkbox" wire:model="whEvents" value="{{ $e }}"> {{ $e }}</label>@endforeach</div>
                <button type="submit" wire:loading.attr="disabled" wire:target="addWebhook" class="ns-cta ns-cta--pill ns-cta--sm">Add webhook</button>
            </form>
            @if ($newSecret)<p class="mt-3 rounded-xl bg-[rgb(var(--nx-warn)/0.12)] px-3 py-2 text-xs text-[rgb(var(--nx-text))]" role="status">Signing secret (shown once): <code class="break-all">{{ $newSecret }}</code></p>@endif
        </section>
        <section class="{{ $card }}" aria-label="Your webhooks">
            @forelse ($webhooks as $h)
                <div wire:key="wh-{{ $h->id }}" class="flex items-center gap-2 border-t border-[rgb(var(--nx-line)/0.6)] py-2 text-xs first:border-0">
                    <div class="min-w-0 flex-1"><b class="block truncate text-sm text-[rgb(var(--nx-text))]">{{ $h->url }}</b><span class="text-[rgb(var(--nx-text-2))]">{{ implode(', ', (array) $h->events) }} · {{ $h->active ? 'On' : 'Switched off after repeated failures' }}@if ($h->last_status) · last answer {{ $h->last_status ?: 'none' }}@endif</span></div>
                    <button type="button" wire:click="deleteWebhook({{ $h->id }})" wire:confirm="Delete this webhook?" class="{{ $btn }}">Delete</button>
                </div>
            @empty
                <p class="text-sm text-[rgb(var(--nx-text-2))]">No webhooks yet.</p>
            @endforelse
        </section>
    @endif

    {{-- INSIGHTS --}}
    @if ($tab === 'insights' && $stats)
        <section class="{{ $card }}" aria-label="Numbers">
            <div class="mb-3 flex items-center justify-between gap-2"><h2 class="{{ $h2 }} mb-0">Last {{ $days === 1 ? '24 hours' : $days.' days' }}</h2>
                <div class="flex gap-1.5">@foreach ([1 => '24h', 7 => '7d', 30 => '30d'] as $d => $l)<button type="button" wire:click="setDays({{ $d }})" aria-pressed="{{ $days === $d ? 'true' : 'false' }}" @class(['rounded-full border px-2.5 py-1 text-xs font-semibold', 'border-[rgb(var(--nx-teal))] bg-[rgb(var(--nx-teal)/0.18)] text-[rgb(var(--nx-text))]' => $days === $d, 'border-[rgb(var(--nx-line-strong))] text-[rgb(var(--nx-text))]' => $days !== $d])>{{ $l }}</button>@endforeach</div></div>
            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ([['Conversations', $stats['conversations']], ['Resolved', $stats['resolved']], ['Customer messages', $stats['messages_in']], ['Replies sent', $stats['messages_out']], ['Median first reply', $stats['median_first_response_min'] === null ? 'n/a' : $stats['median_first_response_min'].' min'], ['AI answered', $stats['ai']['answered']], ['AI suggested', $stats['ai']['suggested']], ['Handed to team', $stats['ai']['handoff']]] as [$l, $v])
                    <div class="rounded-xl bg-[rgb(var(--nx-surface-2))] p-3"><dt class="text-[11px] text-[rgb(var(--nx-text-2))]">{{ $l }}</dt><dd class="font-display text-xl font-extrabold text-[rgb(var(--nx-text))]">{{ $v }}</dd></div>
                @endforeach
            </dl>
            @if ($stats['failed_out'] > 0)<p class="mt-3 text-xs text-[rgb(var(--nx-warn))]">{{ $stats['failed_out'] }} reply(ies) could not be sent. See Channel health below.</p>@endif
        </section>
        <section class="{{ $card }}" aria-label="Channel health">
            <h2 class="{{ $h2 }}">Channel health</h2>
            @forelse ($health as $h)
                <div wire:key="h-{{ $h['id'] }}" class="flex items-center gap-3 border-t border-[rgb(var(--nx-line)/0.6)] py-2 first:border-0" data-health="{{ $h['level'] }}">
                    <span @class(['h-2.5 w-2.5 shrink-0 rounded-full', 'bg-[rgb(var(--nx-ok))]' => $h['level'] === 'green', 'bg-[rgb(var(--nx-warn))]' => $h['level'] === 'amber', 'bg-[rgb(var(--nx-bad))]' => $h['level'] === 'red']) aria-hidden="true"></span>
                    <div class="min-w-0 flex-1"><b class="block truncate text-sm text-[rgb(var(--nx-text))]">{{ $h['name'] }}: {{ $h['label'] }}</b><small class="text-[rgb(var(--nx-text-2))]">{{ ['green' => 'Healthy', 'amber' => 'Needs a look', 'red' => 'Not working'][$h['level']] }}. {{ $h['note'] }}@if ($h['last_event_at']) Last message {{ $h['last_event_at']->diffForHumans() }}.@endif</small></div>
                </div>
            @empty
                <p class="text-sm text-[rgb(var(--nx-text-2))]">No channels connected yet.</p>
            @endforelse
        </section>
    @endif
</div>
</x-nx.page>
</div>
