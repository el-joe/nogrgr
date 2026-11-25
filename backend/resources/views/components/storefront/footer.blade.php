{{--
    Storefront footer per family (Ecommet 1:573, Elora rebuilt in Elora style, Souqify 1:81429).
    Shows the tenant's own name / logo. Link columns, social icons and policy links only
    render entries whose routes exist, so a column disappears until its pages ship.
--}}
@props(['family'])
@php
    $columns = [
        'company' => [['route' => 'storefront.pages.about', 'label' => 'storefront::shell.footer.about'], ['route' => 'storefront.pages.contact', 'label' => 'storefront::shell.footer.contact']],
        'service' => [['route' => 'storefront.pages.returns', 'label' => 'storefront::shell.footer.returns_policy'], ['route' => 'storefront.pages.shipping', 'label' => 'storefront::shell.footer.shipping_info']],
        'help' => [['route' => 'storefront.pages.faq', 'label' => 'storefront::shell.footer.faq'], ['route' => 'storefront.account.orders', 'label' => 'storefront::shell.footer.track_order']],
    ];
    $columns = collect($columns)->map(fn ($links) => array_values(array_filter($links, fn ($link) => Route::has($link['route']))))->filter()->all();
    $legal = array_values(array_filter([
        ['route' => 'storefront.pages.terms', 'label' => 'storefront::shell.footer.terms'],
        ['route' => 'storefront.pages.privacy', 'label' => 'storefront::shell.footer.privacy'],
    ], fn ($link) => Route::has($link['route'])));
    $name = \App\Modules\Storefront\Support\StorefrontTheme::storeName();
@endphp
<x-dynamic-component :component="'sf::footer.'.$family" :columns="$columns" :legal="$legal" :name="$name" />
