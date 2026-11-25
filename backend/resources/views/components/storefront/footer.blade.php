{{--
    Storefront footer per family (Ecommet 1:573, Elora rebuilt in Elora style, Souqify 1:81429).
    Shows the tenant's own name / logo. Link columns, social icons and policy links only
    render entries whose routes exist, so a column disappears until its pages ship.
--}}
@props(['family'])
@php
    $columns = [
        'company' => [['route' => 'storefront.pages.about', 'label' => 'shell.footer.about'], ['route' => 'storefront.pages.contact', 'label' => 'shell.footer.contact']],
        'service' => [['route' => 'storefront.pages.returns', 'label' => 'shell.footer.returns_policy'], ['route' => 'storefront.pages.shipping', 'label' => 'shell.footer.shipping_info']],
        'help' => [['route' => 'storefront.pages.faq', 'label' => 'shell.footer.faq'], ['route' => 'storefront.account.orders', 'label' => 'shell.footer.track_order']],
    ];
    $columns = collect($columns)->map(fn ($links) => array_values(array_filter($links, fn ($link) => Route::has($link['route']))))->filter()->all();
    $legal = array_values(array_filter([
        ['route' => 'storefront.pages.terms', 'label' => 'shell.footer.terms'],
        ['route' => 'storefront.pages.privacy', 'label' => 'shell.footer.privacy'],
    ], fn ($link) => Route::has($link['route'])));
    $name = \App\Modules\Storefront\Support\StorefrontTheme::storeName();
@endphp
<x-dynamic-component :component="'sf::footer.'.$family" :columns="$columns" :legal="$legal" :name="$name" />
