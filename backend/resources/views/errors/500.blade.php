{{-- Central host: the central website 500 page (WEB-029); other hosts: the framework minimal page. --}}
@include('errors._locale')
@if (in_array(request()->getHost(), \App\Shared\Tenancy\CentralDomains::all(), true))
    @include('content::central.errors.500')
@else
    @include('errors._fallback', ['code' => '500', 'message' => __('Server Error')])
@endif
