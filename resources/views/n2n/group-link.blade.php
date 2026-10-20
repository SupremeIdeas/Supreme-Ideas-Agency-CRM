{{-- nx:converted --}}
{{-- Group-call link landing (N2N Batch 11, MASTER-ONLY). Public: a visitor sees who is calling and is sent through sign-up with the HOST's referral code. --}}
<x-layouts.marketing :title="$info ? 'Join '.$info['title'] : 'Call link'" :description="$info ? $info['host_name'].' invited you to a Naara group call.' : 'This call link is not available.'">
    <main class="mx-auto max-w-md px-4 py-14 text-center">
        @if (! $info)
            <h1 class="font-display text-2xl font-extrabold text-[rgb(var(--nx-text))]">This call link is not available</h1>
            <p class="mt-2 text-[rgb(var(--nx-text-2))]">It may have expired, been turned off by the host, or the call has ended. Ask the host for a new link.</p>
        @else
            <p class="text-sm font-semibold text-[rgb(var(--nx-text-2))]">{{ $info['host_name'] }} invited you to</p>
            <h1 class="mt-1 font-display text-3xl font-extrabold text-[rgb(var(--nx-text))]">{{ $info['title'] }}</h1>
            <p class="mt-2 text-sm text-[rgb(var(--nx-text-2))]">A Naara group call. Listening is free. You pay only for the minutes the host lets you speak.@if ($info['requires_approval']) The host approves each person who joins.@endif</p>

            @auth
                <div x-data="joiner(@js($token))" class="mt-8">
                    <button type="button" class="ns-cta ns-cta--pill" @click="go()" :disabled="busy"><span x-text="busy ? 'Joining...' : (pending ? 'Waiting for the host...' : 'Join call')">Join call</span></button>
                    <p class="mt-3 text-sm text-[rgb(var(--nx-bad))]" x-show="err" x-text="err" role="alert"></p>
                    <p class="mt-3 text-sm text-[rgb(var(--nx-text-2))]" x-show="pending" role="status">The host has been told. This page moves on by itself once you are let in.</p>
                </div>
                <script>
                    function joiner(token) {
                        return { busy: false, pending: false, err: '', callId: null, timer: null,
                            headers() { return { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' }; },
                            async go() {
                                this.busy = true; this.err = '';
                                try {
                                    const r = await fetch(@js(route('n2n.api.group.join')), { method: 'POST', headers: this.headers(), body: JSON.stringify({ token }), credentials: 'same-origin' });
                                    const j = await r.json().catch(() => ({}));
                                    if (!r.ok) { this.err = j.message || 'Could not join this call.'; this.busy = false; return; }
                                    this.callId = j.call_id;
                                    if (j.pending) { this.pending = true; this.busy = false; this.poll(); return; }
                                    window.location.assign(j.room_url);
                                } catch (e) { this.err = 'Network problem. Try again.'; this.busy = false; }
                            },
                            poll() {
                                clearInterval(this.timer);
                                this.timer = setInterval(async () => {
                                    const r = await fetch(@js(url('/n2n/api/group')).concat('/', this.callId, '/status'), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).catch(() => null);
                                    const j = r ? await r.json().catch(() => ({})) : {};
                                    if (j.denied) { clearInterval(this.timer); this.pending = false; this.err = 'The host did not let you in.'; }
                                    else if (j.connect) { clearInterval(this.timer); window.location.assign(@js(url('/n2n/group')).concat('/', this.callId)); }
                                }, 3000);
                            } };
                    }
                </script>
            @else
                <div class="mt-8 grid gap-3">
                    <a class="ns-cta ns-cta--pill" href="{{ route('register', ['ref' => $ref]) }}">Create your Naara account</a>
                    <a class="text-sm font-semibold text-[rgb(var(--nx-teal-ink))] underline" href="{{ route('login') }}">I already have an account</a>
                    <p class="text-xs text-[rgb(var(--nx-text-2))]">You will come straight back to this call after signing in.</p>
                </div>
            @endauth
        @endif
    </main>
</x-layouts.marketing>
