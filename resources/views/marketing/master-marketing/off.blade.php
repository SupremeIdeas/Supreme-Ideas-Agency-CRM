{{-- Shown instead of a master sales page the admin has switched off (MASTER-ONLY). Friendly, never a bare 404 to a person; noindex. --}}
<x-layouts.marketing :title="$label.' | '.\App\Support\BrandSettings::name()">
    <section class="mx-auto flex min-h-[60vh] max-w-xl flex-col items-center justify-center px-4 py-24 text-center">
        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary dark:bg-accent/15 dark:text-accent"><x-icon name="clock" class="h-7 w-7" /></span>
        <h1 class="mt-5 text-3xl font-bold text-slate-900 dark:text-white">{{ $label }}</h1>
        <p class="mt-3 text-slate-600 dark:text-slate-300">{{ __('This page is not open right now. Please check back soon.') }}</p>
        <a href="{{ route('home') }}" wire:navigate class="nx-btn nx-btn--primary mt-8 !px-7 !py-3">{{ __('Back to the home page') }}</a>
    </section>
</x-layouts.marketing>
