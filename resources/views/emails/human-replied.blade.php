<x-mail.layout template-key="support-reply" heading="A reply from our support team">
    @if($__intro = \App\Support\MailTemplates::intro('support-reply'))<p style="margin:0 0 12px;">{{ $__intro }}</p>@endif
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;">Our team has replied</p>
    <p style="margin:0 0 4px;">A member of the {{ config('app.name') }} support team has responded to your request. Open the chat to read (or hear) their reply and continue the conversation.</p>
    <x-mail.button template-key="support-reply" :url="$url">Open my support chat</x-mail.button>
</x-mail.layout>
