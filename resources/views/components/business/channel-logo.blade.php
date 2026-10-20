{{-- nx:converted --}}
{{-- The real mark of a channel (committed assets in public/images/channels, never a hotlink). SMS has no brand mark, so it uses the platform's own message icon. --}}
@props(['channel' => 'sms'])
@if (in_array($channel, ['whatsapp', 'telegram', 'instagram'], true))
    <img src="{{ asset('images/channels/'.$channel.'.svg') }}" alt="" width="24" height="24" draggable="false" decoding="async" {{ $attributes->merge(['class' => 'bz-logo']) }}>
@else
    <x-nx.icon name="message-circle" {{ $attributes->merge(['class' => 'bz-logo bz-logo--sms']) }} />
@endif
