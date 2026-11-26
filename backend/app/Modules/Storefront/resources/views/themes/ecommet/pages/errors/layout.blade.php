{{--
    Minimal store status shell (Storefront additions 114:108727 …: store not launched,
    maintenance, suspended, 500 / 419 / 403, payment redirect): logo bar with the language
    link, centred content, "Powered by" line. Children @section('content'), set $title.
--}}
@php
    $family = \App\Modules\Storefront\Support\StorefrontTheme::family();
    $store = \App\Modules\Storefront\Support\StorefrontTheme::storeName();
@endphp
<x-layouts.storefront :family="$family" :title="$title ?? $store" :full-title="isset($title) ? $title.' | '.$store : $store">
    <x-slot:head><meta name="robots" content="noindex"></x-slot:head>
    <div class="flex min-h-screen flex-col bg-white">
        <header class="border-b border-sf-border">
            <div class="eco-container flex h-[72px] items-center justify-between gap-4">
                <x-sf::logo size="sm" />
                <x-sf::lang-switch />
            </div>
        </header>
        <main id="main" class="grid flex-1 place-items-center px-4 py-16">
            <div class="grid w-full max-w-[520px] justify-items-center gap-4 text-center" data-reveal="fade-up">
                @yield('content')
            </div>
        </main>
        <footer class="py-6 text-center text-xs text-sf-muted">{{ __st('errors.powered_by', ['platform' => config('app.name')]) }}</footer>
    </div>
    <x-ui::toasts />
</x-layouts.storefront>
