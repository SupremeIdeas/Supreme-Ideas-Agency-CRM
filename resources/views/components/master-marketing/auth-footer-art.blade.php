@php($artCfg = \App\Support\SiteChrome::art())
@if ($artCfg['enabled'])
    {{-- Master-only sign-in illustration (transparent WebP; the admin can replace it). Included by every login style through @includeIf, so an install
         without this file (every white-label fork) renders nothing. Lives under `master-marketing` so MasterOnlyModules keeps it out of packages. --}}
    <div class="auth-art" data-art-size="{{ $artCfg['size'] }}" data-art-overlap="{{ $artCfg['overlap'] }}" aria-hidden="true" data-auth-footer-art>
        <img src="{{ $artCfg['src'] }}" alt="" width="1400" height="415" decoding="async" loading="lazy">
    </div>
@endif
