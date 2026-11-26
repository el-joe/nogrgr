{{--
    Souqify navbar (Figma 1:81294 / instance 1:73211).
    Desktop: #101010 utility strip — order tracking + shop links at the start, delivery place,
    currency and language (preferences panel) at the end; white bar — store logo in the
    variant accent, "Shop By Categories" button (category mega menu incl. store-own
    categories), grey search box with an "All Category" scope (cat[] filter) + autocomplete,
    Cart (count pill, mini cart) and ACCOUNT.
    Phone home (m 1:77852 …): accent gradient header — categories menu, logo, round cart,
    white search pill. Other phone pages: compact logo + search + cart bar, or the "‹ Title"
    app bar when $mobileTitle is set.
--}}
@props(['mobileTitle' => null, 'back' => null])
@php
    $menu = app(\App\Modules\Storefront\Services\Catalog\CategoryCatalog::class)->menu();
    $context = app(\App\Modules\Storefront\Services\StorefrontContext::class);
    $customer = auth('customer')->user();
    $home = request()->routeIs('storefront.home');
    $account = $customer ? (Route::has('storefront.account.profile') ? route('storefront.account.profile') : null) : (Route::has('storefront.account.login') ? route('storefront.account.login') : null);
    $country = $context->country();
@endphp
<header data-sticky-header class="sq-header sticky top-0 z-40 bg-white max-lg:static">
    <div class="hidden lg:block">
        <div class="bg-sf-topbar text-[#d1d5dc]">
            <div class="mx-auto flex h-10 max-w-[1440px] items-center justify-between gap-4 px-8 text-xs tracking-[0.5px]">
                <nav class="flex items-center gap-4" aria-label="{{ __st('souqify.header.quick_links') }}">
                    @if (Route::has('storefront.orders.index'))
                        <a href="{{ route('storefront.orders.index') }}" class="inline-flex items-center gap-1 hover:text-white"><x-ui::icon name="truck" :size="12" class="text-sf-accent" />{{ __st('souqify.header.order_tracking') }}</a>
                    @endif
                    @if (Route::has('storefront.categories.index'))
                        <a href="{{ route('storefront.categories.index') }}" class="inline-flex items-center gap-1 hover:text-white"><x-ui::icon name="store" :size="12" class="text-sf-accent" />{{ __st('souqify.header.shop') }}</a>
                    @endif
                    @if (Route::has('storefront.offers'))
                        <a href="{{ route('storefront.offers') }}" class="inline-flex items-center gap-1 hover:text-white"><x-ui::icon name="percent" :size="12" class="text-sf-accent" />{{ __st('souqify.header.offers') }}</a>
                    @endif
                </nav>
                <div class="flex items-center gap-4">
                    @if ($country)
                        <button type="button" data-sheet-open="sf-preferences" class="inline-flex items-center gap-1 text-white" aria-haspopup="dialog"><x-ui::icon name="map-pin" :size="12" class="text-sf-accent" />{{ __st('souqify.header.deliver_to', ['place' => $country->name]) }}</button>
                    @endif
                    <button type="button" data-sheet-open="sf-preferences" class="inline-flex items-center gap-1 hover:text-white" aria-haspopup="dialog" aria-label="{{ __st('shell.preferences.open', ['language' => strtoupper(app()->getLocale()), 'currency' => $context->currencyCode()]) }}">{{ $context->currencyCode() }}<x-ui::icon name="chevron-down" :size="10" /></button>
                    <button type="button" data-sheet-open="sf-preferences" class="inline-flex items-center gap-1 hover:text-white" aria-haspopup="dialog"><x-ui::icon name="globe" :size="12" />{{ app()->getLocale() === 'ar' ? 'العربية' : 'English' }}<x-ui::icon name="chevron-down" :size="10" /></button>
                </div>
            </div>
        </div>
        <div class="sq-header-bar relative mx-auto flex h-[94px] max-w-[1440px] items-center gap-8 px-8" data-sq-menu>
            <x-sf::logo tone="accent" size="lg" class="sq-logo" data-header-shrink />
            <div class="flex min-w-0 flex-1 items-center gap-2">
                @if ($menu !== [])
                    <button type="button" class="sq-hcat-btn mo-press" aria-expanded="false" aria-controls="sq-mega" data-sq-menu-toggle>
                        <x-ui::icon name="menu" :size="20" />{{ __st('shell.nav.shop_by_categories') }}<x-ui::icon name="chevron-down" :size="18" class="sq-hcat-btn-caret" />
                    </button>
                @endif
                <x-sf::header.search variant="souqify" class="min-w-0 flex-1" />
            </div>
            <div class="flex shrink-0 items-center gap-[38px]">
                <x-sf::header.cart class="sq-header-cart" />
                @if ($account)
                    <a href="{{ $account }}" class="inline-flex items-center gap-2 text-sm uppercase tracking-[0.5px] text-[#4b4b4b] hover:text-sf-accent"><x-ui::icon name="user" :size="24" /><span class="max-w-[140px] truncate">{{ $customer ? $customer->name : __st('shell.account') }}</span></a>
                @endif
            </div>

            @if ($menu !== [])
                <div id="sq-mega" class="sq-mega" data-sq-mega hidden>
                    <div class="flex gap-6 p-4">
                        <ul class="grid w-[220px] shrink-0 content-start gap-0.5" role="tablist" aria-orientation="vertical" aria-label="{{ __st('shell.nav.categories') }}">
                            @foreach ($menu as $index => $node)
                                <li role="presentation">
                                    <a href="{{ route('storefront.category', $node['category']->slug) }}" role="tab" id="sq-mega-tab-{{ $node['category']->id }}" aria-controls="sq-mega-panel-{{ $node['category']->id }}" aria-selected="{{ $index === 0 ? 'true' : 'false' }}" class="sq-mega-tab" data-sq-mega-tab>
                                        <span class="truncate">{{ $node['category']->name }}</span><x-ui::icon name="chevron-right" :size="16" class="shrink-0 rtl:-scale-x-100" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        @foreach ($menu as $index => $node)
                            <div id="sq-mega-panel-{{ $node['category']->id }}" role="tabpanel" aria-labelledby="sq-mega-tab-{{ $node['category']->id }}" class="sq-mega-panel" @if ($index !== 0) hidden @endif>
                                <a href="{{ route('storefront.category', $node['category']->slug) }}" class="col-span-full font-medium text-sf-accent">{{ __st('shell.nav.shop_all', ['category' => $node['category']->name]) }}</a>
                                @foreach ($node['children'] as $child)
                                    <a href="{{ route('storefront.category', $child->slug) }}" class="hover:text-sf-accent">{{ $child->name }}</a>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="lg:hidden">
        @if ($mobileTitle)
            <x-sf::mobile-bar :title="$mobileTitle" :back="$back" />
        @elseif ($home)
            <div class="sq-mobile-head px-4 pb-5 pt-4">
                <div class="mb-4 flex items-center gap-3 text-white">
                    @if (Route::has('storefront.categories.index'))
                        <a href="{{ route('storefront.categories.index') }}" class="grid size-9 place-items-center" aria-label="{{ __st('shell.nav.categories') }}"><x-ui::icon name="menu" :size="26" /></a>
                    @endif
                    <x-sf::logo tone="light" size="md" />
                    <x-sf::header.cart compact tone="light" class="ms-auto grid size-10 place-items-center rounded-full bg-white !text-sf-accent" />
                </div>
                <x-sf::header.search variant="souqify-mobile" />
            </div>
        @else
            <div class="flex h-14 items-center gap-3 border-b border-sf-border bg-white px-4">
                <x-sf::logo tone="accent" size="sm" />
                <x-sf::header.search variant="souqify-mobile" class="min-w-0 flex-1 [&_.sq-search-pill]:h-9 [&_.sq-search-pill]:bg-[#f1f1f1] [&_.sq-search-pill]:shadow-none" />
                <x-sf::header.cart compact />
            </div>
        @endif
    </div>
</header>
<x-sf::preferences />
