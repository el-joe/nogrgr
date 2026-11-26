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
        'home' => ['route' => 'storefront.home', 'icon' => $family === 'souqify' ? 'store' : 'home', 'label' => 'shell.mobile.home'],
        'categories' => ['route' => 'storefront.categories.index', 'icon' => 'grid', 'label' => 'shell.mobile.categories'],
        'favorites' => ['route' => 'storefront.favorites', 'icon' => $family === 'souqify' ? 'star' : 'heart', 'label' => 'shell.mobile.favorites'],
        'offers' => ['route' => 'storefront.offers', 'icon' => 'percent', 'label' => 'shell.mobile.offers'],
        'orders' => ['route' => 'storefront.orders.index', 'icon' => 'box', 'label' => 'shell.mobile.orders'],
        'cart' => ['route' => 'storefront.cart', 'icon' => 'cart', 'label' => 'shell.mobile.cart'],
        'account' => ['route' => $account, 'icon' => $family === 'ecommet' ? 'user-circle' : ($family === 'elora' ? 'user' : 'users'), 'label' => 'shell.mobile.profile'],
    ];
    $order = ['ecommet' => ['home', 'cart', 'favorites', 'account'], 'elora' => ['home', 'favorites', 'orders', 'account', 'cart'], 'souqify' => ['home', 'favorites', 'offers', 'orders', 'account']][$family] ?? ['home', 'account'];
    $items = collect($order)->mapWithKeys(fn ($key) => [$key => $catalog[$key]])->filter(fn ($item) => Route::has($item['route']))->all();
@endphp
@if (count($items) > 1)
    <x-dynamic-component :component="'sf::mobile-nav.'.$family" :items="$items" :active="$active" />
@endif
