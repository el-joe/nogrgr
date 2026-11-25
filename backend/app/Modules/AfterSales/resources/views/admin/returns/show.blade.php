{{-- Admin › Return requests › detail (ADM-023a, Figma Admin Panel 70:37613 / 70:41386). --}}
<x-layouts.panel surface="admin" :title="__('after-sales::returns.detail_title', ['reference' => $return->reference])" data-page="admin-orders">
    @include('after-sales::returns._detail')
</x-layouts.panel>
