{{-- Central host: the central website 404 page (WEB-027); store hosts: the themed store page (sf-checkout); other hosts: the framework minimal page. --}}
@include('errors._locale')
@if (in_array(request()->getHost(), \App\Shared\Tenancy\CentralDomains::all(), true))
    @include('content::central.errors.404')
@elseif ($storeView = \App\Modules\Storefront\Support\StoreErrorPage::view('404'))
    @include($storeView)
@else
    @include('errors._fallback', ['code' => '404', 'message' => __('Not Found')])
@endif
