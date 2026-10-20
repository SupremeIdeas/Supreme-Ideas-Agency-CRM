{{-- A terminal window showing a real order call and its response, highlighted in the brand colours (MASTER-ONLY, developer landing mock-up). --}}
@php
    use App\Support\MasterMarketing\DeveloperCodeHighlighter as Hl;
    $cmd = "curl -X POST https://".$host."/api/v1/orders \\\n  -H \"Authorization: Bearer \$NAARA_KEY\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"type\":\"esim\",\"plan_id\":12,\"reference\":\"order-1042\"}'";
    $res = "{\n  \"reference\": \"order-1042\",\n  \"kind\": \"esim\",\n  \"status\": \"processing\",\n  \"price_usd\": 11.0,\n  \"result\": {\n    \"qr_code\": \"https://.../qr.png\",\n    \"lpa\": \"LPA:1\$rsp.example.com\$CODE\"\n  }\n}";
@endphp
<div class="overflow-hidden rounded-2xl border border-[#0A6E6E]/60 bg-[#0D1B2A] shadow-2xl shadow-[#0D1B2A]/40 {{ $class ?? '' }}" role="img" aria-label="Terminal: placing an eSIM order with curl and receiving the JSON response">
    <div class="flex items-center gap-2 border-b-2 border-[#0A6E6E] bg-[#12263A] px-4 py-2.5">
        <span class="h-3 w-3 rounded-full bg-[#D4A017]"></span><span class="h-3 w-3 rounded-full bg-[#0A6E6E]"></span><span class="h-3 w-3 rounded-full bg-[#4A6178]"></span>
        <span class="ml-3 font-mono text-xs font-semibold text-[#9FB3C6]">naara: order an eSIM</span>
    </div>
    <div class="space-y-3 p-5 font-mono text-[12.5px] leading-[1.7] text-[#E4ECF3]" aria-hidden="true">
        <pre class="m-0 overflow-x-auto whitespace-pre"><span class="text-[#D4A017]">$</span> {!! Hl::highlight($cmd, 'bash') !!}</pre>
        <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider"><span class="rounded bg-[#0A6E6E] px-2 py-0.5 text-white">201 Created</span><span class="text-[#6F879D]">in 412 ms</span></div>
        <pre class="m-0 overflow-x-auto whitespace-pre">{!! Hl::highlight($res, 'json') !!}<span class="dv-cursor dv-anim"></span></pre>
    </div>
</div>
