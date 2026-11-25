{{--
    Storefront page shell (x-sf::layout) shared by every storefront page: family header,
    main slot, footer, mobile bottom nav, toast region. The theme family comes from
    App\Modules\Storefront\Support\StorefrontTheme unless :family is given.
    <x-sf::layout :title="…" mobile-title="My profile" :back="…" nav="account"> … </x-sf::layout>
--}}
@props(['title' => null, 'family' => null, 'mobileTitle' => null, 'back' => null, 'nav' => null, 'page' => null])
@php
    $family ??= \App\Modules\Storefront\Support\StorefrontTheme::family();
@endphp
<x-layouts.storefront :family="$family" :title="$title">
    <div class="sf-page flex min-h-screen flex-col" @if ($page) data-sf-page="{{ $page }}" @endif>
        <x-sf::header :family="$family" :mobile-title="$mobileTitle" :back="$back" />
        <main id="main" {{ $attributes->class(['flex-1']) }}>
            {{ $slot }}
        </main>
        <x-sf::footer :family="$family" />
        <x-sf::mobile-nav :family="$family" :active="$nav" />
    </div>
    <x-ui::toasts />
</x-layouts.storefront>
