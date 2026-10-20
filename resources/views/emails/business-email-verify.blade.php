<x-mail.layout heading="Verify your business email">
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;">Confirm this address for your {{ config('app.name') }} business workspace</p>
    <p style="margin:0 0 4px;">This is just a trust signal for your workspace — it doesn't change your login. Click below to confirm you own this address.</p>
    <x-mail.button :url="$url">Verify email</x-mail.button>
</x-mail.layout>
