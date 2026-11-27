{{-- Central host: maintenance page (WEB-028) with the "back at" time from Retry-After; other hosts: framework page. --}}
@php
    $retrySeconds = isset($exception) && method_exists($exception, 'getHeaders') ? ($exception->getHeaders()['Retry-After'] ?? null) : null;
    $retryAfter = is_numeric($retrySeconds) ? now()->addSeconds((int) $retrySeconds) : null;
@endphp
@include('errors._locale')
@if ($panelSurface = \App\Shared\Http\PanelErrorPage::surface())
    @include(\App\Shared\Http\PanelErrorPage::VIEW, ['surface' => $panelSurface, 'code' => '503'])
@elseif (in_array(request()->getHost(), \App\Shared\Tenancy\CentralDomains::all(), true))
    @include('content::central.errors.503', ['retryAfter' => $retryAfter])
@elseif ($storeView = \App\Modules\Storefront\Support\StoreErrorPage::view('503'))
    @include($storeView)
@else
    @include('errors._fallback', ['code' => '503', 'message' => __('Service Unavailable')])
@endif
