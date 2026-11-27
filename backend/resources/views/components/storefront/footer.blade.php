{{--
    Storefront footer per family (Ecommet 1:573, Elora rebuilt in Elora style, Souqify 1:81429).
    Shows the tenant's own name / logo. Link columns come from the store pages (sf-data),
    the store's own footer links (VEN-034e) plus catalog / account links; a column disappears
    when it has no links. Copyright line / about text / social icons from Appearance › Footer (copyright
    falls back to the theme's footer template part, ADM-088).
--}}
@props(['family'])
@php
    $links = app(\App\Modules\Storefront\Services\Catalog\StorefrontShell::class)->footerLinks($family === 'ecommet');
    $columns = array_map(fn (array $items) => array_map(fn (array $link) => ['url' => $link['url'], 'title' => $link['title']], $items), $links['columns']);
    $name = \App\Modules\Storefront\Support\StorefrontTheme::storeName();
    $copyright = app(\App\Modules\Storefront\Services\Appearance\StorefrontAppearance::class)->copyright();
    /* No store-written line: the theme's default footer part (ADM-088), :year = this year. */
    if ($copyright === null && tenancy()->initialized) {
        $footerPart = app(\App\Modules\Storefront\Services\Themes\ThemePartService::class)->applied(app(\App\Modules\Storefront\Services\StorefrontContext::class)->theme(), \App\Modules\Storefront\Enums\ThemePartType::Footer);
        $line = $footerPart?->setting('copyright');
        $copyright = is_string($line) ? str_replace(':year', (string) now()->year, $line) : null;
    }
@endphp
<x-dynamic-component :component="'sf::footer.'.$family" :columns="$columns" :legal="$links['legal']" :name="$name" :copyright="$copyright" />
