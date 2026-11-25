{{-- Central host: maintenance page (WEB-028) with the "back at" time from Retry-After; other hosts: framework page. --}}
@php
    $retrySeconds = isset($exception) && method_exists($exception, 'getHeaders') ? ($exception->getHeaders()['Retry-After'] ?? null) : null;
    $retryAfter = is_numeric($retrySeconds) ? now()->addSeconds((int) $retrySeconds) : null;
@endphp
@include('errors._locale')
@if (in_array(request()->getHost(), \App\Shared\Tenancy\CentralDomains::all(), true))
    @include('content::central.errors.503', ['retryAfter' => $retryAfter])
@else
    @include('errors._fallback', ['code' => '503', 'message' => __('Service Unavailable')])
@endif
