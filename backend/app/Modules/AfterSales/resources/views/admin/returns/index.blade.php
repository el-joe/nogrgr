{{-- Admin › Returns & refunds › Return requests (ADM-023, Figma Admin Panel 70:36668). --}}
<x-layouts.panel surface="admin" :title="__('after-sales::returns.title')" data-page="admin-orders">
    @include('after-sales::returns._list', ['surface' => 'admin'])
</x-layouts.panel>
