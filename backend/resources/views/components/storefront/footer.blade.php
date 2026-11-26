{{--
    Storefront footer per family (Ecommet 1:573, Elora rebuilt in Elora style, Souqify 1:81429).
    Shows the tenant's own name / logo. Link columns come from the store pages (sf-data)
    plus catalog / account links; a column disappears when it has no links.
--}}
@props(['family'])
@php
    $links = app(\App\Modules\Storefront\Services\Catalog\StorefrontShell::class)->footerLinks();
    $columns = array_map(fn (array $items) => array_map(fn (array $link) => ['url' => $link['url'], 'title' => $link['title']], $items), $links['columns']);
    $name = \App\Modules\Storefront\Support\StorefrontTheme::storeName();
@endphp
<x-dynamic-component :component="'sf::footer.'.$family" :columns="$columns" :legal="$links['legal']" :name="$name" />
