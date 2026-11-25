{{--
    WEB-019 Registration payment redirect (Figma Central Website additions 125:11977):
    spinner, plan · term · amount, auto-redirect to the gateway's hosted page (JS) with a
    manual link. Inputs: $registration, $quote, $checkoutUrl.
--}}
@extends('platform::central.register._page', ['title' => __('platform::signup.payment.redirect_title'), 'page' => 'register-payment-redirect'])

@section('content')
    <div class="flex flex-col items-center px-4 pt-8 pb-16 sm:px-6 lg:pt-14 lg:pb-24">
        <section class="reg-enter grid w-full max-w-[544px] justify-items-center gap-4 rounded-[24px] bg-white p-6 text-center shadow-(--reg-card-shadow) sm:p-12" aria-live="polite" data-payment-redirect data-url="{{ $checkoutUrl }}">
            @include('platform::central.signup._state-icon', ['tone' => 'spinner'])
            <h1 class="text-2xl leading-8 font-semibold text-ink">{{ __('platform::signup.payment.redirect_title') }}</h1>
            <p class="text-sm leading-6 text-(--reg-gray)">{{ __('platform::signup.payment.redirect_text') }}</p>
            @if ($quote)
                <p class="flex w-full items-center gap-2 rounded-xl bg-info-bg px-4 py-3 text-start text-sm text-ink">
                    <x-ui::icon name="info" :size="18" class="shrink-0 text-info" />
                    {{ __('platform::signup.payment.redirect_amount', ['plan' => $quote->plan->name, 'term' => mb_strtolower(__('platform::signup.plan.'.$quote->term->value)), 'amount' => $quote->total->format()]) }}
                </p>
            @endif
            <a href="{{ $checkoutUrl }}" rel="noopener" class="text-sm font-medium text-primary hover:underline">{{ __('platform::signup.payment.redirect_link') }}</a>
        </section>
    </div>
@endsection
