@php
    $status = \App\Modules\Commerce\Enums\PaymentStatus::tryFrom($fact->payment_status);
    $label = $partial ? __('commerce::orders_admin.partially_refunded') : ($status?->label() ?? $fact->payment_status);
    $gateway = $fact->payment_gateway ? (\App\Modules\Integrations\Enums\PaymentGatewayCode::tryFrom($fact->payment_gateway)?->brand() ?? $fact->payment_gateway) : null;
@endphp
@include('commerce::partials.status-badge', ['value' => $partial ? 'partially_refunded' : ($status ?? $fact->payment_status), 'label' => trim($label.($gateway && $status !== \App\Modules\Commerce\Enums\PaymentStatus::CashOnDelivery ? ' · '.$gateway : ''))])
