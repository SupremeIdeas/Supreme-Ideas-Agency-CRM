{{-- The "your site, the Naara widget and the infrastructure behind it" illustration (MASTER-ONLY, Naara Widgets). One SVG, shared with the WordPress plugin
     (resources/merchant-widget/wordpress/naara-widgets/assets/infrastructure.svg), inlined so it follows the active skin and light/dark. Ids are made unique
     so two copies on one page never share a gradient. --}}
@php
    $svg = (string) @file_get_contents(base_path('resources/merchant-widget/wordpress/naara-widgets/assets/infrastructure.svg'));
    $u = 'ni'.substr(md5(uniqid('', true)), 0, 6);
    $svg = str_replace(['ni-t', 'ni-d', 'ni-bg', 'ni-flow', 'ni-core', 'ni-mark', 'ni-sh', 'ni-ar'], [$u.'t', $u.'d', $u.'bg', $u.'flow', $u.'core', $u.'mark', $u.'sh', $u.'ar'], $svg);
@endphp
@if ($svg !== '')
    <div {{ $attributes->merge(['class' => 'nx-mw-illus']) }}>{!! $svg !!}</div>
@endif
