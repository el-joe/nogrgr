{{--
    WEB-019a Payment success (Figma Central Website additions 125:12081, mobile 125:12227):
    plan, amount paid, payment reference; "Set up my store" continues to the store step
    (proven e-mail) or to the e-mail check (the link was just sent). The invoice is issued
    and settled with this payment when the store is created. Inputs: $registration,
    $quote, $log.
--}}
@extends('platform::central.register._page', ['title' => __('platform::signup.payment.success_title'), 'page' => 'register-payment-success'])

@php
    $verified = $registration->isEmailVerified();
@endphp

@section('content')
    <div class="flex flex-col items-center gap-6 px-4 pt-6 pb-16 sm:gap-8 sm:px-6 lg:pt-14 lg:pb-24">
        @include('platform::central.register.partials.steps', ['current' => 4])

        <section class="reg-enter grid w-full max-w-[584px] justify-items-center gap-4 rounded-[24px] bg-white p-6 text-center shadow-(--reg-card-shadow) sm:p-12">
            @include('platform::central.signup._state-icon', ['tone' => 'success', 'icon' => 'check-circle'])
            <h1 class="text-2xl leading-8 font-semibold text-ink sm:text-[28px]">{{ __('platform::signup.payment.success_title') }}</h1>
            <p class="text-sm leading-6 text-(--reg-gray)">{{ __('platform::signup.payment.success_text', ['plan' => $quote?->plan->name ?? '', 'email' => $registration->email]) }}</p>
            <dl class="grid w-full gap-2 rounded-xl bg-(--reg-readonly) p-4 text-sm">
                @if ($quote)
                    <div class="flex justify-between gap-4"><dt class="text-(--reg-gray)">{{ __('platform::signup.payment.success_plan') }}</dt><dd class="font-medium text-ink">{{ $quote->plan->name }} · {{ mb_strtolower(__('platform::signup.plan.'.$quote->term->value)) }}</dd></div>
                @endif
                <div class="flex justify-between gap-4"><dt class="text-(--reg-gray)">{{ __('platform::signup.payment.success_paid') }}</dt><dd class="font-medium text-ink" dir="ltr">{{ $log->amount()->format() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-(--reg-gray)">{{ __('platform::signup.payment.success_reference') }}</dt><dd class="font-medium text-ink" dir="ltr">{{ $log->reference() }}</dd></div>
            </dl>
            @unless ($verified)
                <p class="flex w-full items-start gap-2 rounded-xl bg-info-bg px-4 py-3 text-start text-sm text-ink">
                    <x-ui::icon name="mail" :size="18" class="mt-px shrink-0 text-info" />{{ __('platform::signup.payment.success_next_verify') }}
                </p>
            @endunless
            <a href="{{ route($verified ? 'central.register.continue' : 'central.register.verify') }}" class="group inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary px-6 text-base font-medium text-white transition hover:bg-primary-hover active:scale-[0.99]">
                {{ $verified ? __('platform::signup.payment.setup_store') : __('platform::signup.payment.check_email') }}
                <x-ui::icon name="chevron-right" :size="18" class="transition-transform group-hover:translate-x-0.5 rtl:rotate-180 rtl:group-hover:-translate-x-0.5" />
            </a>
        </section>
    </div>
@endsection
