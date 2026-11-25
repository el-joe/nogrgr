{{-- One status pill for orders, payments, shipping steps, returns and refunds: $value (enum or string), optional $label. --}}
@php
    $key = $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    $text = $label ?? ($value instanceof \BackedEnum && method_exists($value, 'label') ? $value->label() : \Illuminate\Support\Str::headline($key));
    $tone = match ($key) {
        'paid', 'delivered', 'completed', 'approved', 'refunded_done', 'exchanged', 'closed_ok', 'ready', 'connected', 'accepted' => 'success',
        'pending', 'pending_payment', 'processing', 'packed', 'awaiting_info', 'testing', 'needs_attention', 'to_ship', 'cod' => 'warning',
        'cancelled', 'failed', 'rejected', 'delayed', 'key_rejected', 'returned' => 'danger',
        'shipped', 'in_transit', 'out_for_delivery', 'item_received', 'inspected', 'exchange_shipped' => 'info',
        'refunded' => ($context ?? '') === 'refund' ? 'success' : 'danger',
        default => 'neutral',
    };
@endphp
<x-ui::badge :tone="$tone">{{ $text }}</x-ui::badge>
