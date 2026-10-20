<x-mail.layout heading="Your sign-in code">
    <p style="margin:0 0 12px;font-size:18px;font-weight:700;">Your {{ $brand }} sign-in code</p>
    <p style="margin:0 0 12px;">Enter this code to see your orders. It works for {{ $minutes }} minutes.</p>
    <p style="margin:16px 0;font-size:32px;font-weight:800;letter-spacing:6px;">{{ $code }}</p>
    <p style="margin:0;font-size:13px;color:#64748b;">If you did not ask for this, you can ignore this email. Nobody can see your orders without the code.</p>
</x-mail.layout>
