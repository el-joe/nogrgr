{{-- Vendor › Returns › detail (VEN-022a, Figma Vendor Panel 84:104582). --}}
<x-vendor::layout :title="__('after-sales::returns.detail_title', ['reference' => $return->reference])" page="vendor-orders">
    @include('after-sales::returns._detail')
</x-vendor::layout>
