{{-- Custom Twig theme 500 page bridge (decision #121): the theme's template, else the built-in 500 view. --}}
{!! \App\Modules\Storefront\Support\StoreErrorPage::customHtml(500) !!}
