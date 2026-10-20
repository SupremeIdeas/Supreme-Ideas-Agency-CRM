{{-- MASTER ONLY. The content lock for images and videos (Admin > Content protection). $surface = marketing | app. Renders nothing when the admin has it off. See ContentLock for what it does and does not do. --}}
@php($lock = \App\Support\MasterMarketing\ContentLock::settings())
@if (\App\Support\MasterMarketing\ContentLock::active($surface ?? 'marketing'))
<style>
    html.nx-lock-img img, html.nx-lock-img picture, html.nx-lock-img canvas, html.nx-lock-img [data-protect] { -webkit-user-drag: none; user-select: none; -webkit-user-select: none; -webkit-touch-callout: none; }
    html.nx-lock-vid video { -webkit-touch-callout: none; }
    @media print { html.nx-lock-keys img, html.nx-lock-keys picture, html.nx-lock-keys video, html.nx-lock-keys canvas { visibility: hidden !important; } }
    html.nx-shield img, html.nx-shield picture, html.nx-shield video, html.nx-shield canvas { filter: blur(26px) !important; }
</style>
<script>
    (function (c) {
        var root = document.documentElement;
        if (c.images) root.classList.add('nx-lock-img');
        if (c.videos) root.classList.add('nx-lock-vid');
        if (c.keys) root.classList.add('nx-lock-keys');
        var media = 'IMG,PICTURE,VIDEO,CANVAS';
        function protectedTarget(t) { return t && t.closest && t.closest(media + ',[data-protect]'); }
        function hardenVideo(v) { if (!c.videos) return; v.setAttribute('controlsList', 'nodownload noremoteplayback'); v.setAttribute('disablePictureInPicture', ''); v.setAttribute('disableRemotePlayback', ''); }
        function harden(scope) {
            if (c.images) scope.querySelectorAll('img').forEach(function (i) { i.setAttribute('draggable', 'false'); });
            scope.querySelectorAll('video').forEach(hardenVideo);
        }
        document.addEventListener('contextmenu', function (e) {
            var t = e.target;
            if (t && t.closest && t.closest('input,textarea,[contenteditable="true"]')) return;   // never take the menu away from a text field
            var m = protectedTarget(t);
            if (m && ((c.images && !/VIDEO/.test(m.tagName)) || (c.videos && /VIDEO/.test(m.tagName)))) e.preventDefault();
        }, true);
        if (c.images) document.addEventListener('dragstart', function (e) { if (e.target && e.target.tagName === 'IMG') e.preventDefault(); }, true);
        if (c.keys) document.addEventListener('keydown', function (e) {
            var mod = e.ctrlKey || e.metaKey, k = (e.key || '').toLowerCase();
            if (mod && (k === 's' || k === 'p') && !(e.target && e.target.closest && e.target.closest('input,textarea,[contenteditable="true"]'))) e.preventDefault();
        }, true);
        if (c.shield) {
            var on = function () { root.classList.add('nx-shield'); }, off = function () { root.classList.remove('nx-shield'); };
            document.addEventListener('visibilitychange', function () { document.hidden ? on() : off(); });
            window.addEventListener('blur', on); window.addEventListener('focus', off);
            document.addEventListener('keyup', function (e) { if (e.key === 'PrintScreen') { try { navigator.clipboard && navigator.clipboard.writeText(' '); } catch (x) {} on(); setTimeout(off, 1500); } });
        }
        harden(document);
        if ('MutationObserver' in window) new MutationObserver(function (ms) { ms.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType === 1) { if (n.tagName === 'VIDEO') hardenVideo(n); else if (n.tagName === 'IMG' && c.images) n.setAttribute('draggable', 'false'); if (n.querySelectorAll) harden(n); } }); }); }).observe(document.documentElement, { childList: true, subtree: true });
    })(@js($lock));
</script>
@endif
