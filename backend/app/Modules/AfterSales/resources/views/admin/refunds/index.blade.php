{{-- Admin › Returns & refunds › Refunds (ADM-024, Figma Admin Panel 70:38484 / 70:41386). --}}
<x-layouts.panel surface="admin" :title="__('after-sales::refunds.title')" data-page="admin-orders">
    @include('after-sales::refunds._list', ['surface' => 'admin'])
</x-layouts.panel>
