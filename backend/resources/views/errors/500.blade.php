{{-- Central host: the central website 500 page (WEB-029); store hosts: the themed store page (sf-checkout); other hosts: the framework minimal page. --}}
@include('errors._locale')
@if (\App\Modules\Affiliates\Support\AffiliateErrorPage::applies())
    @include('affiliates::errors.page', ['code' => '500'])
@elseif ($panelSurface = \App\Shared\Http\PanelErrorPage::surface())
    @include(\App\Shared\Http\PanelErrorPage::VIEW, ['surface' => $panelSurface, 'code' => '500'])
@elseif (in_array(request()->getHost(), \App\Shared\Tenancy\CentralDomains::all(), true))
    @include('content::central.errors.500')
@elseif ($storeView = \App\Modules\Storefront\Support\StoreErrorPage::view('500'))
    @include($storeView)
@else
    @include('errors._fallback', ['code' => '500', 'message' => __('Server Error')])
@endif
