{{-- Vendor › Orders › Refunds (VEN-023, Figma Vendor Panel 84:105181). --}}
<x-vendor::layout :title="__('after-sales::refunds.title')" page="vendor-orders">
    @include('after-sales::refunds._list', ['surface' => 'vendor'])
</x-vendor::layout>
