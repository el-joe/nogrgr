{{-- Store hosts: the themed store 403 page (SF-ALL-004); other hosts: the framework minimal page. --}}
@include('errors._locale')
@if ($storeView = \App\Modules\Storefront\Support\StoreErrorPage::view('403'))
    @include($storeView)
@else
    @include('errors._fallback', ['code' => '403', 'message' => __($exception?->getMessage() ?: 'Forbidden')])
@endif
