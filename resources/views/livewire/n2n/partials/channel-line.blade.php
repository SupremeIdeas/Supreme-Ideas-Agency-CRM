{{-- One SMS or call with this person, drawn in their Naara thread from the unified timeline (MASTER-ONLY). --}}
@php
    $isCall = $l['channel'] === 'call';
    $out = $l['direction'] === 'out';
    $failed = in_array($l['status'], ['failed', 'no-answer'], true);
    $label = $isCall ? ($out ? 'You called' : 'Call from them') : ($out ? 'You sent an SMS' : 'SMS from them');
@endphp
<div class="nc__chline" data-channel-line="{{ $l['channel'] }}" role="note">
    <span class="nc__chline__ic" aria-hidden="true"><x-nx.icon name="{{ $isCall ? 'phone' : 'message-circle' }}" /></span>
    <span class="nc__chline__tx">
        <b>{{ $label }}</b>@if ($failed) <em>({{ $l['status'] === 'no-answer' ? 'no answer' : 'not delivered' }})</em>@endif
        @if (! $isCall && filled($l['summary']))<span class="nc__chline__sum">{{ \Illuminate\Support\Str::limit($l['summary'], 140) }}</span>@elseif ($isCall && filled($l['summary']) && $l['summary'] !== 'Call')<span class="nc__chline__sum">{{ $l['summary'] }}</span>@endif
    </span>
    <time class="nc__chline__t" datetime="{{ $l['at']->toIso8601String() }}">{{ $l['at']->format('H:i') }}</time>
</div>
