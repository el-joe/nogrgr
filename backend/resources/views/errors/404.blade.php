{{-- Panels (admin / vendor / owner / affiliate): their own error page; central host: the central website 404 page (WEB-027); store hosts: the themed store page; other hosts: the framework minimal page. --}}
@include('errors._locale')
@if (\App\Modules\Affiliates\Support\AffiliateErrorPage::applies())
    @include('affiliates::errors.page', ['code' => '404'])
@elseif ($panelSurface = \App\Shared\Http\PanelErrorPage::surface())
    @include(\App\Shared\Http\PanelErrorPage::VIEW, ['surface' => $panelSurface, 'code' => '404'])
@elseif (in_array(request()->getHost(), \App\Shared\Tenancy\CentralDomains::all(), true))
    @include('content::central.errors.404')
@elseif ($storeView = \App\Modules\Storefront\Support\StoreErrorPage::view('404'))
    @include($storeView)
@else
    @include('errors._fallback', ['code' => '404', 'message' => __('Not Found')])
@endif
