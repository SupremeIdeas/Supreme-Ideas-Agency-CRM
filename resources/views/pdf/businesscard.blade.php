<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    body { margin: 0; font-family: DejaVu Sans, Helvetica, Arial, sans-serif; color: #0D1B2A; }
    .band { height: 118px; background: {{ $f['color'] }}; }
    .band img { width: 100%; height: 118px; }
    .wrap { padding: 0 18px; }
    .logo { width: 64px; height: 64px; margin-top: -32px; border: 3px solid #fff; border-radius: 32px; background: #fff; }
    h1 { font-size: 18px; margin: 6px 0 2px; }
    .cat { font-size: 10px; color: #666; margin: 0 0 6px; }
    .blurb { font-size: 10px; line-height: 1.45; color: #333; margin: 0 0 8px; }
    .links { font-size: 9px; color: #0A6E6E; margin: 0 0 8px; }
    .qr { width: 96px; height: 96px; }
    .foot { font-size: 8px; color: #666; }
</style>
</head>
<body>
<div class="band">@if ($hero)<img src="{{ $hero }}" alt="">@endif</div>
<div class="wrap">
    @if ($logo)<img class="logo" src="{{ $logo }}" alt="">@else<div class="logo"></div>@endif
    <h1>{{ $f['name'] }}</h1>
    @if ($f['category'])<p class="cat">{{ $f['category'] }}</p>@endif
    @if ($f['blurb'])<p class="blurb">{{ \Illuminate\Support\Str::limit($f['blurb'], 190) }}</p>@endif
    @if ($f['links'] !== [])<p class="links">@foreach (array_slice($f['links'], 0, 4) as $l){{ $l['label'] }}: {{ $l['host'] }}<br>@endforeach</p>@endif
    <table style="width:100%"><tr>
        <td style="vertical-align:bottom"><img class="qr" src="{{ $qr }}" alt="QR code"></td>
        <td style="vertical-align:bottom;text-align:right"><p class="foot">Scan to open this card on Naara<br>{{ preg_replace('#^https?://#', '', $url) }}</p></td>
    </tr></table>
</div>
</body>
</html>
