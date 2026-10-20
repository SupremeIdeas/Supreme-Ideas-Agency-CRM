{{-- NaaraBrain shell layout (MASTER-ONLY): a bare full-screen host. The page itself owns the sliding sheet, the history sidebar and the composer. --}}
@props(['title' => null])
<x-layouts.app :title="$title ?? 'NaaraBrain'" page-type="brain" :body-class="\App\Support\PlatformTheme::bodyClass()">
{{ $slot }}
@vite(['resources/css/brain.css', 'resources/js/brain/shell.js'])
</x-layouts.app>
