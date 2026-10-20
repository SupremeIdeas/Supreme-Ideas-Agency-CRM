{{-- One branded body for every notification that used to go out in Laravel's unbranded default mail look. Email Studio edits the
     subject / heading / intro / button / accent through the template key, exactly like the dedicated templates. --}}
@php($key = $templateKey ?? 'global')
<x-mail.layout :template-key="$key" :heading="$heading ?? null">
    @if($__intro = \App\Support\MailTemplates::intro($key))<p style="margin:0 0 12px;">{{ $__intro }}</p>@endif
    @if(! empty($greeting))<p style="margin:0 0 12px;font-size:18px;font-weight:700;">{{ $greeting }}</p>@endif
    @foreach(($lines ?? []) as $line)
        @if(! empty($line['code']))
            <p style="margin:16px 0;padding:14px 16px;background-color:#f1f5f9;border-radius:10px;font-size:26px;font-weight:700;letter-spacing:6px;text-align:center;">{{ $line['code'] }}</p>
        @else
            <p style="margin:0 0 12px;">{!! preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', e((string) ($line['text'] ?? $line))) !!}</p>
        @endif
    @endforeach
    @if(! empty($actionUrl))
        <x-mail.button :template-key="$key" :url="$actionUrl">{{ \App\Support\MailTemplates::buttonText($key, $actionText ?? 'Open') }}</x-mail.button>
    @endif
    @if(! empty($footnote))<p style="margin:12px 0 0;color:#64748b;font-size:13px;">{{ $footnote }}</p>@endif
</x-mail.layout>
