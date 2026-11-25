{{-- Vendor › Orders › Returns (VEN-022, Figma Vendor Panel 84:103852): the store's returns. --}}
<x-vendor::layout :title="__('after-sales::returns.title')" page="vendor-orders">
    @include('after-sales::returns._list', ['surface' => 'vendor'])
</x-vendor::layout>
