<x-mail.layout template-key="shared-plan-invite" heading="Shared plan invite">
    @if($__intro = \App\Support\MailTemplates::intro('shared-plan-invite'))<p style="margin:0 0 12px;">{{ $__intro }}</p>@endif
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;">You've been invited to a shared plan{{ $name ? ', '.$name : '' }}</p>
    <p style="margin:0 0 4px;"><strong>{{ $ownerName }}</strong> has invited you to spend from their {{ config('app.name') }} wallet, up to a cap they've set. You can review and accept (or decline) from your wallet page.</p>
    <x-mail.button template-key="shared-plan-invite" :url="$url">Review invite</x-mail.button>
</x-mail.layout>
