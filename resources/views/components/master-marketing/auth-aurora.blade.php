@php($artCfg = \App\Support\SiteChrome::art())
@if ($artCfg['aurora'] !== 'off')
    <div class="auth-aurora" data-aurora="{{ $artCfg['aurora'] }}" aria-hidden="true"><i></i><i></i><i></i></div>
@endif
