{{--
    Mobile bottom navigation per family (Ecommet 1:2879 dark pill, Elora rebuilt,
    Souqify 1:80723). Items whose routes do not exist yet are left out.
    $active: home|categories|cart|favorites|offers|orders|account
--}}
@props(['family', 'active' => null])
@php
    $customer = auth('customer')->user();
    $account = $customer ? 'storefront.account.profile' : 'storefront.account.login';
    $catalog = [
        'home' => ['route' => 'storefront.home', 'icon' => 'store', 'label' => 'storefront::shell.mobile.home'],
        'categories' => ['route' => 'storefront.categories.index', 'icon' => 'grid', 'label' => 'storefront::shell.mobile.categories'],
        'favorites' => ['route' => 'storefront.account.wishlist', 'icon' => 'star', 'label' => 'storefront::shell.mobile.favorites'],
        'offers' => ['route' => 'storefront.offers', 'icon' => 'percent', 'label' => 'storefront::shell.mobile.offers'],
        'orders' => ['route' => 'storefront.account.orders', 'icon' => 'box', 'label' => 'storefront::shell.mobile.orders'],
        'cart' => ['route' => 'storefront.cart', 'icon' => 'cart', 'label' => 'storefront::shell.mobile.cart'],
        'account' => ['route' => $account, 'icon' => 'users', 'label' => $family === 'souqify' ? 'storefront::shell.mobile.profile' : ($family === 'elora' ? 'storefront::shell.mobile.account' : 'storefront::shell.mobile.profile')],
    ];
    $order = ['ecommet' => ['home', 'cart', 'favorites', 'account'], 'elora' => ['home', 'categories', 'cart', 'account'], 'souqify' => ['home', 'favorites', 'offers', 'orders', 'account']][$family] ?? ['home', 'account'];
    $items = collect($order)->mapWithKeys(fn ($key) => [$key => $catalog[$key]])->filter(fn ($item) => Route::has($item['route']))->all();
@endphp
@if (count($items) > 1)
    <x-dynamic-component :component="'sf::mobile-nav.'.$family" :items="$items" :active="$active" />
@endif
