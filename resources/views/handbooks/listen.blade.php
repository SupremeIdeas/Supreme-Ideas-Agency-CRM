{{-- nx:converted --}}
{{-- Audiobook player (MASTER-ONLY): chapter list, speed, resume position. Streams only; saving a file needs the admin's download switch. --}}
<x-layouts.customer :title="'Listen — '.$b->title">
<x-nx.page class="ns-pg ns-pg--mid">
    <a href="{{ route('handbooks.library') }}" class="ns-pg__back"><x-nx.icon name="left" /> My Library</a>
    <h1 class="ns-h1" style="margin-top:6px">{{ $b->title }}</h1>
    <p class="ns-sub" style="margin:4px 0 0">Audiobook</p>

    <div class="ns-pg__card ns-ring" style="margin-top:16px" data-audio-player data-slug="{{ $b->slug }}" data-tracks='@json($tracks)'>
        <p class="ns-pg__hint" style="margin:0" data-now>Choose a chapter</p>
        <audio controls preload="metadata" style="width:100%;margin-top:10px" data-audio></audio>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:10px">
            <label class="ns-pg__hint" for="aud-speed" style="margin:0">Speed</label>
            <select id="aud-speed" class="ns-input" style="width:auto" data-speed>@foreach ([0.75, 1, 1.25, 1.5, 2] as $s)<option value="{{ $s }}" @selected($s == 1)>{{ $s }}x</option>@endforeach</select>
        </div>
    </div>

    <ol class="hb-tracks" data-track-list style="margin-top:14px">
        @foreach ($tracks as $i => $t)
            <li><button type="button" class="hb-track" data-track="{{ $i }}"><span>{{ $i + 1 }}. {{ $t['title'] }}</span>@if ($t['seconds'])<span class="ns-pg__hint" style="margin:0">{{ floor($t['seconds'] / 60) }}:{{ str_pad((string) ($t['seconds'] % 60), 2, '0', STR_PAD_LEFT) }}</span>@endif</button>@if ($download)<a href="{{ $t['url'] }}?dl=1" class="ns-pg__hint" style="text-decoration:underline">Download</a>@endif</li>
        @endforeach
    </ol>
    <script>
    (function () {
        var root = document.querySelector('[data-audio-player]'); if (!root) return;
        var tracks = JSON.parse(root.dataset.tracks || '[]'), audio = root.querySelector('[data-audio]'), now = root.querySelector('[data-now]'), key = 'naara-audio:' + root.dataset.slug, idx = 0;
        function save() { try { localStorage.setItem(key, JSON.stringify({ i: idx, t: Math.floor(audio.currentTime) })); } catch (e) {} }
        function load(i, t) { idx = i; audio.src = tracks[i].url; now.textContent = (i + 1) + '. ' + tracks[i].title; if (t) audio.addEventListener('loadedmetadata', function f() { audio.currentTime = t; audio.removeEventListener('loadedmetadata', f); }); document.querySelectorAll('[data-track]').forEach(function (b) { b.classList.toggle('is-on', +b.dataset.track === i); }); }
        document.querySelectorAll('[data-track]').forEach(function (b) { b.addEventListener('click', function () { load(+b.dataset.track, 0); audio.play(); }); });
        audio.addEventListener('timeupdate', function () { if (Math.floor(audio.currentTime) % 5 === 0) save(); });
        audio.addEventListener('ended', function () { if (idx + 1 < tracks.length) { load(idx + 1, 0); audio.play(); } });
        root.querySelector('[data-speed]').addEventListener('change', function (e) { audio.playbackRate = parseFloat(e.target.value); });
        var st = null; try { st = JSON.parse(localStorage.getItem(key) || 'null'); } catch (e) {}
        if (tracks.length) load(st && tracks[st.i] ? st.i : 0, st ? st.t : 0);
    })();
    </script>
</x-nx.page>
</x-layouts.customer>
