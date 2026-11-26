{{--
    WEB-016c Payment step (Figma Central Website additions 125:10606, mobile 125:10779):
    payment method (the platform's active registration gateways, as the old project listed
    them; Stripe = inline card, decision #78 b) + order summary. Coupons are hidden until
    Commerce coupons exist (decisions.md). Inputs: $registration, $quote, $gateways.
--}}
@extends('platform::central.register._page', ['title' => __('platform::signup.page_title'), 'page' => 'register-payment'])

@php
    $selected = old('gateway', $gateways->first()?->code->value);
    $period = $quote ? __('platform::signup.plan.'.$quote->term->value) : '';
@endphp

@section('content')
    <div class="flex flex-col items-center gap-6 px-4 pt-6 pb-16 sm:gap-8 sm:px-6 lg:px-[120px] lg:pt-14 lg:pb-24">
        @include('platform::central.register.partials.steps', ['current' => 3])

        <h1 class="text-center text-[28px] leading-9 font-semibold text-ink sm:text-[40px] sm:leading-[52px]" data-reveal="fade-up">{{ __('platform::signup.payment.title') }} <span class="text-primary">{{ __('platform::signup.payment.title_accent') }}</span></h1>

        @if (session('error'))
            <p class="flex w-full max-w-[1034px] items-start gap-2 rounded-xl bg-danger-bg px-3.5 py-2.5 text-sm leading-5 text-danger" role="alert">
                <x-ui::icon name="x-circle" :size="18" class="mt-px shrink-0" />{{ session('error') }}
            </p>
        @endif

        <form method="POST" action="{{ route('central.register.payment.store') }}" class="grid w-full max-w-[1034px] items-start gap-6 lg:grid-cols-[1fr_388px]" data-register-form novalidate>
            @csrf

            <section class="reg-enter grid gap-4 rounded-[24px] bg-white p-5 shadow-(--reg-card-shadow) sm:p-8" aria-labelledby="reg-pay-method">
                <h2 id="reg-pay-method" class="text-xl font-medium text-ink">{{ __('platform::signup.payment.method') }}</h2>

                @if ($gateways->isEmpty())
                    <div class="grid justify-items-center gap-2 rounded-xl border border-dashed border-(--reg-border) bg-(--reg-readonly) px-6 py-10 text-center">
                        <x-ui::icon name="credit-card" :size="28" class="text-(--reg-muted)" />
                        <p class="text-sm font-medium text-ink">{{ __('platform::signup.payment.none_title') }}</p>
                        <p class="text-xs text-(--reg-gray)">{{ __('platform::signup.payment.none_text') }}</p>
                        <a href="{{ route('central.register.plan') }}" class="mt-2 text-sm font-medium text-primary hover:underline">{{ __('platform::signup.payment.change_plan') }}</a>
                    </div>
                @else
                    <fieldset class="grid gap-3">
                        <legend class="sr-only">{{ __('platform::signup.payment.method') }}</legend>
                        @foreach ($gateways as $gateway)
                            @php $config = $gateway->config(\App\Modules\Integrations\Enums\GatewayPurpose::TenantRegistration); @endphp
                            <label class="flex cursor-pointer items-center gap-4 rounded-xl border-2 border-(--reg-rail) bg-white p-4 transition has-checked:border-primary has-checked:bg-(--reg-tint)/40 hover:border-(--reg-ring)">
                                <input type="radio" name="gateway" value="{{ $gateway->code->value }}" class="accent-primary" @checked($selected === $gateway->code->value) required>
                                <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-(--reg-readonly) text-xs font-semibold text-ink" aria-hidden="true">{{ $gateway->code->initials() }}</span>
                                <span class="grid min-w-0 flex-1 gap-0.5">
                                    <span class="flex flex-wrap items-center gap-2 text-sm font-medium text-ink">
                                        {{ $gateway->code->brand() }}
                                        @if ($config?->mode === \App\Modules\Integrations\Enums\GatewayMode::Test)
                                            <span class="rounded-full bg-warning-bg px-2 py-0.5 text-[11px] font-medium text-warning">{{ __('platform::signup.payment.test_mode') }}</span>
                                        @endif
                                    </span>
                                    <span class="text-xs text-(--reg-gray)" dir="ltr">{{ $gateway->code->methods() }}</span>
                                </span>
                            </label>
                        @endforeach
                    </fieldset>
                    <p class="text-sm text-danger" data-message-for="gateway" role="status" @error('gateway') @else hidden @enderror>@error('gateway'){{ $message }}@enderror</p>
                    <x-ui::stripe-card :gateways="$gateways" :purpose="\App\Modules\Integrations\Enums\GatewayPurpose::TenantRegistration" />
                    <p class="flex items-start gap-2 rounded-xl bg-info-bg px-3.5 py-3 text-[13px] leading-5 text-ink" data-hosted-hint>
                        <x-ui::icon name="info" :size="18" class="mt-px shrink-0 text-info" />{{ __('platform::signup.payment.methods_hint') }}
                    </p>
                @endif
            </section>

            <aside class="reg-enter grid gap-3 rounded-[24px] bg-white p-5 shadow-(--reg-card-shadow) sm:p-7" aria-labelledby="reg-pay-summary">
                <h2 id="reg-pay-summary" class="text-xl font-medium text-ink">{{ __('platform::signup.payment.summary') }}</h2>
                @if ($quote)
                    <dl class="grid gap-3 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-(--reg-gray)">{{ __('platform::signup.payment.plan_line', ['plan' => $quote->plan->name, 'term' => $period]) }}</dt><dd class="font-medium text-ink" dir="ltr">{{ $quote->subtotal->format() }}</dd></div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-(--reg-gray)">{{ $quote->taxRateBp > 0 ? __('platform::signup.payment.tax', ['rate' => rtrim(rtrim(number_format($quote->taxRateBp / 100, 2), '0'), '.')]) : __('platform::signup.payment.tax_plain') }}</dt>
                            <dd class="font-medium text-ink" dir="ltr">{{ $quote->tax->format() }}</dd>
                        </div>
                    </dl>
                    <div class="flex items-center justify-between gap-4 border-t border-(--reg-rail) pt-4">
                        <span class="text-lg font-semibold text-ink">{{ __('platform::signup.payment.due_today') }}</span>
                        <span class="text-xl font-semibold text-ink" dir="ltr">{{ $quote->total->format() }}</span>
                    </div>
                    <p class="text-xs text-(--reg-gray)">{{ __('platform::signup.payment.then', ['amount' => $quote->total->format(), 'period' => mb_strtolower($period)]) }}</p>
                @endif
                <button type="submit" class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary px-6 text-base font-medium text-white transition hover:bg-primary-hover active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60" data-submit @disabled($gateways->isEmpty() || ! $quote)>
                    <span class="hidden size-4 animate-spin rounded-full border-2 border-white/40 border-t-white" data-spinner aria-hidden="true"></span>
                    <x-ui::icon name="shield" :size="18" />
                    <span>{{ __('platform::signup.payment.pay', ['amount' => $quote?->total->format() ?? '']) }}</span>
                </button>
                <p class="text-xs text-(--reg-gray)">{{ __('platform::signup.payment.secure') }}</p>
                <a href="{{ route('central.register.plan') }}" class="text-center text-sm font-medium text-primary hover:underline">{{ __('platform::signup.payment.change_plan') }}</a>
            </aside>
        </form>
    </div>
@endsection
