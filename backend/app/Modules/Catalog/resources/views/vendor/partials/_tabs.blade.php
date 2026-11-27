{{--
    Products / Own products / New products / Categories pill tabs (Tenant Panel Layout 12:12968 "Frame 73").
    @include('catalog::vendor.partials._tabs', ['active' => 'products'])
--}}
@php
    $staff = auth('vendor')->user();
    $tabs = array_filter([
        'products' => $staff?->can('catalog.products.view') ? [__('catalog::vendor.tabs.products'), vendor_route('catalog.products.index')] : null,
        'own' => $staff?->can('catalog.own-products.view') ? [__('catalog::vendor.tabs.own'), vendor_route('catalog.own-products.index')] : null,
        'new' => $staff?->can('catalog.products.manage') ? [__('catalog::vendor.tabs.new'), vendor_route('catalog.products.new')] : null,
        'categories' => $staff?->can('catalog.categories.view') ? [__('catalog::taxonomy.tab'), vendor_route('catalog.categories.index')] : null,
    ]);
@endphp
<nav class="vnd-tabs-pill" aria-label="{{ __('catalog::vendor.tabs.label') }}" data-reveal="fade-up">
    @foreach ($tabs as $key => [$label, $url])
        <a href="{{ $url }}" @if ($key === $active) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
