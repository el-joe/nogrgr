{{-- Central host: the central website 419 page (WEB-030); store hosts: the themed store page (sf-checkout); other hosts: the framework minimal page. --}}
@include('errors._locale')
@if (\App\Modules\Affiliates\Support\AffiliateErrorPage::applies())
    @include('affiliates::errors.page', ['code' => '419'])
@elseif ($panelSurface = \App\Shared\Http\PanelErrorPage::surface())
    @include(\App\Shared\Http\PanelErrorPage::VIEW, ['surface' => $panelSurface, 'code' => '419'])
@elseif (in_array(request()->getHost(), \App\Shared\Tenancy\CentralDomains::all(), true))
    @include('content::central.errors.419')
@elseif ($storeView = \App\Modules\Storefront\Support\StoreErrorPage::view('419'))
    @include($storeView)
@else
    @include('errors._fallback', ['code' => '419', 'message' => __('Page Expired')])
@endif
