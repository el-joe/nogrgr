{{-- Order progress (placed → paid → processing → shipped → delivered) built from the order snapshot. --}}
@php
    $o = $order;
    $fmt = fn ($date) => $date?->translatedFormat('j M, H:i');
    $current = match ($o->status) {
        \App\Modules\Commerce\Enums\OrderStatus::PendingPayment => 1,
        \App\Modules\Commerce\Enums\OrderStatus::Paid => 3,
        \App\Modules\Commerce\Enums\OrderStatus::Processing => 3,
        \App\Modules\Commerce\Enums\OrderStatus::Shipped => 4,
        default => 5,
    };
    $complete = in_array($o->status, [\App\Modules\Commerce\Enums\OrderStatus::Delivered, \App\Modules\Commerce\Enums\OrderStatus::Completed, \App\Modules\Commerce\Enums\OrderStatus::Returned, \App\Modules\Commerce\Enums\OrderStatus::Refunded], true) && $o->delivered_at !== null;
    $failed = in_array($o->status, [\App\Modules\Commerce\Enums\OrderStatus::Cancelled, \App\Modules\Commerce\Enums\OrderStatus::Refunded], true) && $o->delivered_at === null;
    $estimate = $o->delivery_min_date ? __('commerce::orders_admin.steps.est', ['date' => $o->deliveryWindowLabel()]) : null;
    if ($failed) { $current = $o->paid_at ? 3 : 2; }
@endphp
@include('commerce::partials.progress', [
    'steps' => [
        [__('commerce::orders_admin.steps.placed'), $fmt($o->placed_at)],
        [$o->payment_status === \App\Modules\Commerce\Enums\PaymentStatus::CashOnDelivery ? __('commerce::orders_admin.steps.confirmed') : __('commerce::orders_admin.steps.paid'), $fmt($o->paid_at)],
        [$failed ? $o->status->label() : __('commerce::orders_admin.steps.processing'), $failed ? $fmt($o->cancelled_at ?? $o->refunded_at) : null],
        [__('commerce::orders_admin.steps.shipped'), $o->shipped_at ? $fmt($o->shipped_at) : $estimate],
        [__('commerce::orders_admin.steps.delivered'), $o->delivered_at ? $fmt($o->delivered_at) : null],
    ],
    'current' => $current,
    'complete' => $complete,
    'failed' => $failed,
])
