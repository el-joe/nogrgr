{{-- Payment state of an order; $refunded (Money) turns a part refund into "Partially refunded". --}}
@if (\App\Modules\Commerce\Pricing\RefundCalculator::isPartiallyRefunded($order->money('grand_total'), $refunded))
    @include('commerce::partials.status-badge', ['value' => 'partially_refunded', 'label' => __('commerce::orders_admin.partially_refunded')])
@else
    @include('commerce::partials.status-badge', ['value' => $order->payment_status])
@endif
