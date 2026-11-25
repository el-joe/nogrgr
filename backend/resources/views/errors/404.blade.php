{{-- Central host: the central website 404 page (WEB-027); other hosts: the framework minimal page. --}}
@include('errors._locale')
@if (in_array(request()->getHost(), \App\Shared\Tenancy\CentralDomains::all(), true))
    @include('content::central.errors.404')
@else
    @include('errors._fallback', ['code' => '404', 'message' => __('Not Found')])
@endif
