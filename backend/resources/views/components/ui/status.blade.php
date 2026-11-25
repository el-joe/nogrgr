{{--
    Status pill with the platform mapping (design-specs §3 Badge):
    draft/inactive = neutral · new/featured = brand · paid/delivered/active/healthy = success ·
    pending/processing = warning · cancelled/failed/refunded = danger · shipped/in_transit = info.
    <x-ui::status status="pending" />  (label from ui.status.<status> unless a slot is given)
--}}
@props(['status', 'variant' => 'soft'])
@php
    $key = strtolower(str_replace([' ', '-'], '_', (string) ($status instanceof \BackedEnum ? $status->value : $status)));
    $tone = match (true) {
        in_array($key, ['new', 'featured'], true) => 'brand',
        in_array($key, ['paid', 'delivered', 'active', 'healthy', 'completed', 'approved', 'verified', 'live', 'synced'], true) => 'success',
        in_array($key, ['pending', 'processing', 'pending_review', 'trial', 'degraded', 'test'], true) => 'warning',
        in_array($key, ['cancelled', 'canceled', 'failed', 'refunded', 'rejected', 'suspended', 'out_of_stock', 'down'], true) => 'danger',
        in_array($key, ['shipped', 'in_transit', 'info'], true) => 'info',
        default => 'neutral',
    };
    $translationKey = 'ui.status.'.$key;
    $label = trim((string) $slot) !== '' ? $slot : (__($translationKey) !== $translationKey ? __($translationKey) : \Illuminate\Support\Str::headline($key));
@endphp
<x-ui::badge :tone="$tone" :variant="$variant" {{ $attributes }}>{{ $label }}</x-ui::badge>
