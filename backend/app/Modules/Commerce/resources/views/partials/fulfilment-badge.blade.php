{{-- Shipment status of an order; a cancelled shipment never reads "To ship". --}}
@if ($order->isShipmentCancelled())
    @include('commerce::partials.status-badge', ['value' => 'cancelled', 'label' => __('commerce::fulfilment.not_shipping')])
@else
    @include('commerce::partials.status-badge', ['value' => $order->fulfilment_status])
@endif
