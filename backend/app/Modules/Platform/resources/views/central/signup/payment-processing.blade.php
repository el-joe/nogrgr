{{--
    WEB-019a while the gateway has not confirmed yet (async method, approval still
    pending): spinner, reloads itself every few seconds (JS) until paid or failed.
    Inputs: $registration, $log, $refreshUrl.
--}}
@extends('platform::central.register._page', ['title' => __('platform::signup.payment.processing_title'), 'page' => 'register-payment-processing'])

@section('content')
    <div class="flex flex-col items-center gap-6 px-4 pt-6 pb-16 sm:gap-8 sm:px-6 lg:pt-14 lg:pb-24">
        @include('platform::central.register.partials.steps', ['current' => 3])

        <section class="reg-enter grid w-full max-w-[544px] justify-items-center gap-4 rounded-[24px] bg-white p-6 text-center shadow-(--reg-card-shadow) sm:p-12" aria-live="polite" data-payment-processing data-refresh-url="{{ $refreshUrl }}">
            @include('platform::central.signup._state-icon', ['tone' => 'spinner'])
            <h1 class="text-2xl leading-8 font-semibold text-ink">{{ __('platform::signup.payment.processing_title') }}</h1>
            <p class="text-sm leading-6 text-(--reg-gray)">{{ __('platform::signup.payment.processing_text') }}</p>
            <p class="text-xs text-(--reg-gray)" dir="ltr">{{ $log->reference() }} · {{ $log->amount()->format() }}</p>
            <a href="{{ $refreshUrl }}" class="text-sm font-medium text-primary hover:underline">{{ __('platform::signup.payment.processing_refresh') }}</a>
        </section>
    </div>
@endsection
