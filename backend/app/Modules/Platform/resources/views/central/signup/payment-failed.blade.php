{{--
    WEB-019b Payment failed / cancelled (Figma Central Website additions 125:12321):
    reason from the gateway when it gives one, "Try again" (payment step) and "Change
    plan"; an invalid / expired return link shows its own state. Inputs: $registration
    (null when invalid), $cancelled, $reason, $invalid.
--}}
@extends('platform::central.register._page', ['title' => __('platform::signup.payment.failed_title'), 'page' => 'register-payment-failed'])

@php
    $invalid ??= false;
    $key = $invalid ? 'invalid' : ($cancelled ? 'cancelled' : 'failed');
    $contactUrl = \Illuminate\Support\Facades\Route::has('central.contact') ? route('central.contact') : null;
@endphp

@section('content')
    <div class="flex flex-col items-center gap-6 px-4 pt-6 pb-16 sm:gap-8 sm:px-6 lg:pt-14 lg:pb-24">
        @unless ($invalid)
            @include('platform::central.register.partials.steps', ['current' => 3])
        @endunless

        <section class="reg-enter grid w-full max-w-[584px] justify-items-center gap-4 rounded-[24px] bg-white p-6 text-center shadow-(--reg-card-shadow) sm:p-12" role="alert">
            @include('platform::central.signup._state-icon', ['tone' => 'danger', 'icon' => 'x-circle'])
            <h1 class="text-2xl leading-8 font-semibold text-ink sm:text-[28px]">{{ __('platform::signup.payment.'.$key.'_title') }}</h1>
            <p class="text-sm leading-6 text-(--reg-gray)">{{ __('platform::signup.payment.'.$key.'_text') }}</p>
            @if ($reason)
                <p class="flex w-full items-center gap-2 rounded-xl bg-danger-bg px-4 py-3 text-start text-sm text-ink">
                    <x-ui::icon name="x-circle" :size="18" class="shrink-0 text-danger" />{{ __('platform::signup.payment.failed_reason', ['reason' => $reason]) }}
                </p>
            @endif
            @if ($invalid)
                <a href="{{ route('central.register') }}" class="inline-flex h-12 w-full items-center justify-center rounded-xl bg-primary px-6 text-base font-medium text-white transition hover:bg-primary-hover">{{ __('platform::signup.payment.start_over') }}</a>
            @else
                <div class="grid w-full gap-3 sm:grid-cols-2">
                    <a href="{{ route('central.register.payment') }}" class="inline-flex h-12 items-center justify-center rounded-xl bg-primary px-6 text-base font-medium text-white transition hover:bg-primary-hover active:scale-[0.99]">{{ __('platform::signup.payment.try_again') }}</a>
                    <a href="{{ route('central.register.plan') }}" class="inline-flex h-12 items-center justify-center rounded-xl border border-(--reg-border) bg-white px-6 text-base font-medium text-ink transition hover:bg-(--reg-readonly)">{{ __('platform::signup.payment.change_plan') }}</a>
                </div>
            @endif
            @if ($contactUrl)
                <a href="{{ $contactUrl }}" class="text-sm font-medium text-primary hover:underline">{{ __('platform::signup.payment.contact') }}</a>
            @endif
        </section>
    </div>
@endsection
