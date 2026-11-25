{{--
    Gateway list badge: Inactive | Test mode (any enabled config in test) | Active | Not set.
    @param \App\Modules\Integrations\Models\PaymentGateway $gateway
--}}
@php
    $enabled = $gateway->configs->where('is_enabled', true);
    [$tone, $label] = match (true) {
        ! $gateway->is_active => ['neutral', __('integrations::admin.gateways.badges.inactive')],
        ! $gateway->code->isOffline() && $enabled->isEmpty() => ['neutral', __('integrations::admin.gateways.badges.not_set')],
        $enabled->contains(fn ($config) => $config->mode === \App\Modules\Integrations\Enums\GatewayMode::Test) => ['warning', __('integrations::admin.gateways.badges.test_mode')],
        default => ['success', __('integrations::admin.gateways.badges.active')],
    };
@endphp
<x-ui::badge :tone="$tone">{{ $label }}</x-ui::badge>
