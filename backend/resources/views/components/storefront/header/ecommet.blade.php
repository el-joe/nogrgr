{{--
    Ecommet header (Figma 1:411 desktop, 1:2764 mobile): dark promise bar 56 + red nav 80
    (logo, Best-Selling / 5-Star Rated / New In / Categories mega-dropdown, search with
    autocomplete, locale → preferences panel, account, cart with count). Desktop keeps the
    red nav sticky; phones get the white bar (logo, search pill, locale, menu) that hides on
    scroll down. Account-style pages pass $mobileTitle for the "‹ Title 🛒" bar instead.
--}}
@props(['mobileTitle' => null, 'back' => null])
@php
    $menu = app(\App\Modules\Storefront\Services\Catalog\CategoryCatalog::class)->menu();
    $freeShipping = ($countryId = app(\App\Modules\Storefront\Services\StorefrontContext::class)->countryId()) !== null
        && app(\App\Modules\Shipping\Services\ShippingQuoteService::class)->freeShippingThreshold($countryId) !== null;
    $current = Route::currentRouteName();
    $links = [
        ['route' => 'storefront.best-sellers', 'label' => 'shell.nav.best_sellers', 'icon' => 'thumbs-up'],
        ['route' => 'storefront.top-rated', 'label' => 'shell.nav.top_rated', 'icon' => 'star'],
        ['route' => 'storefront.new-in', 'label' => 'shell.nav.new_in', 'icon' => null],
    ];
@endphp
<header data-sticky-header class="eco-header">
    {{-- Desktop --}}
    <div class="hidden lg:block">
        <div class="eco-topbar">
            <div class="eco-container flex h-full items-center justify-between">
                @if ($freeShipping)
                    <button type="button" class="inline-flex items-center gap-2 text-center" data-sheet-open="sf-free-shipping">
                        <x-ui::icon name="truck" :size="24" />
                        <span class="grid underline underline-offset-2">{{ __st('shell.topbar.free_shipping') }}<span>{{ __st('shell.topbar.limited_offer') }}</span></span>
                    </button>
                @else
                    <span class="inline-flex items-center gap-2"><x-ui::icon name="truck" :size="24" />{{ __st('shell.topbar.fast_delivery') }}</span>
                @endif
                <a href="{{ route('storefront.offers') }}" class="inline-flex items-center gap-2 text-center hover:underline">
                    <x-ui::icon name="flame" :size="24" />
                    <span class="grid">{{ __st('shell.topbar.offers') }}<span>{{ __st('shell.topbar.dont_miss') }}</span></span>
                </a>
                <span class="inline-flex items-center gap-2"><x-ui::icon name="lock" :size="21" />{{ __st('shell.secure_checkout') }}</span>
            </div>
        </div>
        <div class="eco-nav">
            <div class="eco-container flex h-full items-center gap-4 xl:gap-6">
                <x-sf::logo tone="light" />
                <nav class="flex items-center gap-4" aria-label="{{ __st('shell.main_nav') }}">
                    @foreach ($links as $link)
                        <a href="{{ route($link['route']) }}" class="eco-nav-link" @if ($current === Str::after($link['route'], '.') || $current === $link['route']) aria-current="page" @endif>
                            @if ($link['icon'])<x-ui::icon :name="$link['icon']" :size="20" />@endif{{ __st($link['label']) }}
                        </a>
                    @endforeach
                    @if ($menu !== [])
                        <div class="relative" data-mega>
                            <button type="button" class="eco-nav-link" aria-expanded="false" aria-controls="sf-mega" data-mega-toggle>
                                {{ __st('shell.nav.categories') }}<x-ui::icon name="chevron-down" :size="20" class="transition-transform duration-200 [[aria-expanded=true]_&]:rotate-180" />
                            </button>
                            <div id="sf-mega" class="eco-dropdown eco-dropdown-arrow [--arrow-x:40px] start-0 top-[calc(100%+22px)] flex w-[760px] max-w-[calc(100vw-48px)] overflow-hidden" data-mega-panel>
                                <ul class="max-h-[440px] w-[240px] shrink-0 overflow-y-auto border-e border-eco-stroke py-2" role="tablist" aria-orientation="vertical">
                                    @foreach ($menu as $index => $node)
                                        <li><button type="button" role="tab" class="eco-mega-cat" id="sf-mega-tab-{{ $node['category']->id }}" aria-controls="sf-mega-panel-{{ $node['category']->id }}" aria-selected="{{ $index === 0 ? 'true' : 'false' }}" data-mega-tab>
                                            <span class="truncate">{{ $node['category']->name }}</span><x-ui::icon name="chevron-right" :size="16" class="shrink-0 rtl:-scale-x-100" />
                                        </button></li>
                                    @endforeach
                                </ul>
                                <div class="max-h-[440px] min-w-0 flex-1 overflow-y-auto p-5">
                                    @foreach ($menu as $index => $node)
                                        <div id="sf-mega-panel-{{ $node['category']->id }}" role="tabpanel" aria-labelledby="sf-mega-tab-{{ $node['category']->id }}" @if ($index !== 0) hidden @endif>
                                            <a href="{{ route('storefront.category', ['slug' => $node['category']->slug]) }}" class="mb-4 inline-flex items-center gap-1 text-sm font-medium underline-offset-2 hover:underline">
                                                {{ __st('shell.nav.shop_all', ['category' => $node['category']->name]) }}<x-ui::icon name="arrow-right" :size="16" class="rtl:-scale-x-100" />
                                            </a>
                                            <div class="grid grid-cols-4 gap-x-3 gap-y-5">
                                                @foreach ($node['children'] !== [] ? $node['children'] : [$node['category']] as $child)
                                                    <a href="{{ route('storefront.category', ['slug' => $child->slug]) }}" class="eco-cat-tile">
                                                        <x-sf::category-image :category="$child" :size="82" />
                                                        <span class="line-clamp-2">{{ $child->name }}</span>
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </nav>
                <x-sf::header.search variant="ecommet" class="mx-auto w-full max-w-[383px] min-w-[220px] flex-1" />
                <div class="flex shrink-0 items-center gap-4">
                    <x-sf::locale-button />
                    <x-sf::account-link tone="light" />
                    <x-sf::header.cart tone="light" />
                </div>
            </div>
        </div>
    </div>

    {{-- Phones / tablets --}}
    <div class="lg:hidden">
        @if ($mobileTitle)
            <x-sf::mobile-bar :title="$mobileTitle" :back="$back" />
        @else
            <div class="flex h-[60px] items-center gap-3 bg-white px-4 shadow-[0_2px_6px_rgba(0,0,0,0.06)]">
                <x-sf::logo size="sm" />
                <x-sf::header.search variant="ecommet-mobile" class="min-w-0 flex-1" />
                <x-sf::locale-button tone="dark" compact />
                <a href="{{ route('storefront.categories.index') }}" class="grid size-8 shrink-0 place-items-center text-eco-black" aria-label="{{ __st('shell.nav.categories') }}"><x-ui::icon name="menu" :size="24" /></a>
            </div>
        @endif
    </div>
</header>
<x-sf::preferences />
@if ($freeShipping)
    <x-sf::modal id="sf-free-shipping" :title="__st('shell.free_shipping.title')" size="md">
        <ul class="grid list-disc gap-2 ps-5 text-sm text-eco-black marker:text-eco-black">
            <li>{{ __st('shell.free_shipping.line_1') }}</li>
            <li>{{ __st('shell.free_shipping.line_2') }}</li>
            <li>{{ __st('shell.free_shipping.line_3') }}</li>
        </ul>
        <button type="button" class="eco-pill-dark mx-auto mt-6 flex w-full max-w-[262px] font-medium" data-sheet-close>{{ __st('shell.ok') }}</button>
    </x-sf::modal>
@endif
