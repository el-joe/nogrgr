{{-- Central host: the central website 419 page (WEB-030); other hosts: the framework minimal page. --}}
@include('errors._locale')
@if (in_array(request()->getHost(), \App\Shared\Tenancy\CentralDomains::all(), true))
    @include('content::central.errors.419')
@else
    @include('errors._fallback', ['code' => '419', 'message' => __('Page Expired')])
@endif
