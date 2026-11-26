{{--
    Inline Stripe card (decision #78 b, like the old project): a Stripe.js Card Element
    box shown inside its form while the `gateway` radio/select is "stripe". The shared
    module resources/js/core/payments/stripe-card.js (the ONLY place that loads
    Stripe.js, on demand) mounts it, posts the form as JSON, confirms the
    PaymentIntent with the returned client secret and opens the return URL; card errors
    show here. Without a publishable key nothing renders (the hosted flow stays).
    Props: publishableKey (or gateways + purpose to look it up), field = gateway input name.
--}}
@props(['publishableKey' => null, 'gateways' => null, 'purpose' => null, 'field' => 'gateway'])
@php
    use App\Modules\Integrations\Enums\PaymentGatewayCode;
    use App\Modules\Integrations\Payments\PaymentDriverRegistry;

    $offersStripe = $gateways === null || collect($gateways)->contains(static fn ($gateway): bool => ($gateway->code ?? $gateway) === PaymentGatewayCode::Stripe);
    $publishableKey ??= ($purpose !== null && $offersStripe) ? app(PaymentDriverRegistry::class)->publishableKey(PaymentGatewayCode::Stripe, $purpose) : null;
@endphp
@if ($publishableKey)
    <div {{ $attributes->class(['grid gap-2 rounded-xl border border-line bg-surface p-4']) }}
        data-stripe-card data-publishable-key="{{ $publishableKey }}" data-gateway-field="{{ $field }}" data-gateway-value="stripe"
        data-locale="{{ app()->getLocale() }}" data-card-error-text="{{ __('ui.stripe_card.error') }}" hidden>
        <p class="flex items-center gap-1.5 text-[13px] font-semibold text-ink">
            <x-ui::icon name="lock" :size="14" class="shrink-0 text-success" />{{ __('ui.stripe_card.title') }}
        </p>
        <div class="min-h-[42px] rounded-lg border border-line bg-white px-4 py-3 text-ink dark:bg-surface" data-stripe-element role="group" aria-label="{{ __('ui.stripe_card.number') }}" dir="ltr"></div>
        <p class="text-xs text-danger" data-stripe-error role="alert" hidden></p>
        <p class="text-xs text-ink-secondary">{{ __('ui.stripe_card.secure') }}</p>
    </div>
@endif
