{{-- Ecommet header (Figma 1:411): dark announcement bar 56 + red nav 80. --}}
@props(['mobileTitle' => null, 'back' => null])
@php
    $links = [
        ['route' => 'storefront.best-sellers', 'label' => 'shell.nav.best_sellers', 'icon' => 'star'],
        ['route' => 'storefront.top-rated', 'label' => 'shell.nav.top_rated', 'icon' => 'star'],
        ['route' => 'storefront.new-in', 'label' => 'shell.nav.new_in'],
        ['route' => 'storefront.categories.index', 'label' => 'shell.nav.categories'],
    ];
@endphp
<header data-sticky-header class="hidden lg:block">
    <div class="h-14 bg-sf-topbar text-white">
        <div class="mx-auto flex h-full max-w-[1280px] items-center justify-end gap-2 px-6 text-sm tracking-wide">
            <x-ui::icon name="key" :size="20" />{{ __st('shell.secure_checkout') }}
        </div>
    </div>
    <div class="h-20 bg-sf-header shadow-[0_5px_4px_rgba(162,88,88,0.16)]">
        <div class="mx-auto flex h-full max-w-[1280px] items-center gap-6 px-6">
            <x-sf::logo tone="light" data-header-shrink />
            <nav class="flex items-center gap-4" aria-label="{{ __st('shell.main_nav') }}"><x-sf::header.links :links="$links" /></nav>
            <x-sf::header.search variant="ecommet" class="ms-auto" />
            <div class="ms-auto flex items-center gap-5">
                <x-sf::lang-switch tone="light" />
                <x-sf::account-link tone="light" />
                <x-sf::header.cart tone="light" />
            </div>
        </div>
    </div>
</header>
<div class="lg:hidden">
    @if ($mobileTitle)
        <x-sf::mobile-bar :title="$mobileTitle" :back="$back" />
    @else
        <div class="flex h-14 items-center gap-3 bg-sf-header px-4">
            <x-sf::logo tone="light" size="sm" />
            <div class="ms-auto flex items-center gap-4"><x-sf::lang-switch tone="light" compact /><x-sf::account-link tone="light" compact /></div>
        </div>
    @endif
</div>
