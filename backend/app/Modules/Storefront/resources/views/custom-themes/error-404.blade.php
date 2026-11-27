{{-- Custom Twig theme 404 page bridge (decision #121): the theme's template, else the built-in 404 view. --}}
{!! \App\Modules\Storefront\Support\StoreErrorPage::customHtml(404) !!}
