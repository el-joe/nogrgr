{{--
    WEB-016b Plan step (Figma Central Website additions 125:10149, mobile 125:10421):
    Monthly / Yearly switch, plan cards (selected = outlined), then free trial or pay
    now. Inputs: $plans, $term, $selectedId, $quotes (plan id => monthly / yearly quote).
--}}
@extends('platform::central.register._page', ['title' => __('platform::signup.page_title'), 'page' => 'register-plan'])

@php
    $initialTerm = old('term', $term->value);
    $initialPlan = (int) old('plan_id', $selectedId);
    $selectedPlan = $plans->firstWhere('id', $initialPlan);
    $initialChoice = old('choice', $selectedPlan && $selectedPlan->trial_days > 0 ? 'trial' : 'paid');
    $trialPlans = $plans->filter(fn ($plan) => $plan->trial_days > 0)->pluck('id')->values();
    $state = ['term' => $initialTerm, 'plan' => $initialPlan, 'choice' => $initialChoice, 'trialPlans' => $trialPlans];
@endphp

@section('content')
    <div class="flex flex-col items-center gap-6 px-4 pt-6 pb-16 sm:gap-8 sm:px-6 lg:px-[120px] lg:pt-14 lg:pb-24">
        @include('platform::central.register.partials.steps', ['current' => 2])

        <header class="grid gap-2 text-center" data-reveal="fade-up">
            <h1 class="text-[28px] leading-9 font-semibold text-ink sm:text-[40px] sm:leading-[52px]">{{ __('platform::signup.plan.title') }} <span class="text-primary">{{ __('platform::signup.plan.title_accent') }}</span></h1>
            <p class="text-base leading-6 text-(--reg-gray)">{{ __('platform::signup.plan.intro_trial') }}</p>
        </header>

        @if (session('error'))
            <p class="flex w-full max-w-[1020px] items-start gap-2 rounded-xl bg-danger-bg px-3.5 py-2.5 text-sm leading-5 text-danger" role="alert">
                <x-ui::icon name="x-circle" :size="18" class="mt-px shrink-0" />{{ session('error') }}
            </p>
        @endif

        @if ($plans->isEmpty())
            <section class="reg-enter grid w-full max-w-[560px] justify-items-center gap-3 rounded-[24px] bg-white p-10 text-center shadow-(--reg-card-shadow)">
                <span class="grid size-16 place-items-center rounded-full bg-(--reg-tint) text-primary"><x-ui::icon name="layers" :size="28" /></span>
                <h2 class="text-xl font-semibold text-ink">{{ __('platform::signup.plan.empty_title') }}</h2>
                <p class="text-sm text-(--reg-gray)">{{ __('platform::signup.plan.empty_text') }}</p>
                <a href="{{ route('central.register') }}" class="mt-2 inline-flex h-11 items-center rounded-xl border border-(--reg-border) px-6 text-sm font-medium text-ink hover:bg-(--reg-readonly)">{{ __('platform::signup.plan.back') }}</a>
            </section>
        @else
            <form method="POST" action="{{ route('central.register.plan.store') }}" class="grid w-full max-w-[1020px] gap-6 sm:gap-8" data-register-form data-plan-form x-data="{{ json_encode($state) }}" novalidate>
                @csrf
                <input type="hidden" name="term" :value="term" value="{{ $initialTerm }}">

                <div class="flex justify-center">
                    <div class="inline-flex rounded-full bg-(--reg-tint) p-1" role="radiogroup" aria-label="{{ __('platform::signup.plan.term_attribute') }}">
                        @foreach (['monthly', 'yearly'] as $option)
                            <button type="button" role="radio" class="h-9 rounded-full px-5 text-sm font-medium transition"
                                :class="term === '{{ $option }}' ? 'bg-primary text-white shadow-sm' : 'text-ink hover:text-primary'"
                                :aria-checked="(term === '{{ $option }}').toString()" @click="term = '{{ $option }}'"
                                aria-checked="{{ $initialTerm === $option ? 'true' : 'false' }}">{{ __('platform::signup.plan.'.$option) }}</button>
                        @endforeach
                    </div>
                </div>

                <fieldset class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <legend class="sr-only">{{ __('platform::signup.plan.plan_attribute') }}</legend>
                    @foreach ($plans as $index => $plan)
                        @php
                            $monthly = $quotes[$plan->id]['monthly'];
                            $yearly = $quotes[$plan->id]['yearly'];
                        @endphp
                        <label class="reg-plan reg-enter relative flex cursor-pointer flex-col gap-4 rounded-[20px] border-2 bg-white p-6 shadow-(--reg-card-shadow) transition"
                            :class="plan === {{ $plan->id }} ? 'border-primary' : 'border-transparent hover:border-(--reg-ring)'">
                            <input type="radio" name="plan_id" value="{{ $plan->id }}" class="sr-only" x-model.number="plan" @checked($initialPlan === $plan->id)
                                @change="if (! trialPlans.includes({{ $plan->id }})) choice = 'paid'">
                            <span class="flex items-start justify-between gap-3">
                                <span class="text-xl font-semibold text-ink">{{ $plan->name }}</span>
                                <span class="rounded-full bg-(--reg-tint) px-2.5 py-1 text-xs font-medium text-primary">
                                    {{ $plan->trial_days > 0 ? __('platform::signup.plan.trial_days', ['days' => $plan->trial_days]) : __('platform::signup.plan.no_trial') }}
                                </span>
                            </span>
                            @if ($plan->description)
                                <span class="text-sm text-(--reg-gray)">{{ $plan->description }}</span>
                            @endif
                            <span class="grid gap-1">
                                <span class="flex items-baseline gap-1.5" x-show="term === 'monthly'" @if ($initialTerm !== 'monthly') x-cloak @endif>
                                    <span class="text-[34px] leading-10 font-semibold text-ink" dir="ltr">{{ $monthly->subtotal->isZero() ? __('platform::signup.plan.free') : $monthly->subtotal->format() }}</span>
                                    <span class="text-sm text-(--reg-gray)">{{ __('platform::signup.plan.per_month') }}</span>
                                </span>
                                <span class="text-xs text-(--reg-gray)" x-show="term === 'monthly'" @if ($initialTerm !== 'monthly') x-cloak @endif>{{ __('platform::signup.plan.billed_monthly') }}</span>
                                <span class="flex items-baseline gap-1.5" x-show="term === 'yearly'" @if ($initialTerm !== 'yearly') x-cloak @endif>
                                    <span class="text-[34px] leading-10 font-semibold text-ink" dir="ltr">{{ $yearly->subtotal->isZero() ? __('platform::signup.plan.free') : $yearly->subtotal->format() }}</span>
                                    <span class="text-sm text-(--reg-gray)">{{ __('platform::signup.plan.per_year') }}</span>
                                </span>
                                <span class="text-xs text-(--reg-gray)" x-show="term === 'yearly'" @if ($initialTerm !== 'yearly') x-cloak @endif>{{ __('platform::signup.plan.billed_yearly', ['amount' => $yearly->subtotal->divide(12)->format()]) }}</span>
                            </span>
                            @if ($plan->highlightLines() !== [])
                                <ul class="grid gap-3 border-t border-(--reg-rail) pt-4 text-sm text-ink">
                                    @foreach ($plan->highlightLines() as $line)
                                        <li class="flex items-start gap-2.5"><x-ui::icon name="check-circle" :size="18" class="mt-px shrink-0 text-primary" />{{ $line }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            <span class="mt-auto inline-flex h-11 items-center justify-center gap-2 rounded-xl border text-sm font-medium transition"
                                :class="plan === {{ $plan->id }} ? 'border-primary bg-primary text-white' : 'border-primary text-primary'">
                                <span x-show="plan === {{ $plan->id }}" class="inline-flex items-center gap-2"><x-ui::icon name="check" :size="16" />{{ __('platform::signup.plan.selected') }}</span>
                                <span x-show="plan !== {{ $plan->id }}" x-cloak>{{ __('platform::signup.plan.choose', ['plan' => $plan->name]) }}</span>
                            </span>
                        </label>
                    @endforeach
                </fieldset>
                <p class="text-center text-sm text-danger" data-message-for="plan_id" role="status" @error('plan_id') @else hidden @enderror>@error('plan_id'){{ $message }}@enderror</p>

                <fieldset class="mx-auto grid w-full max-w-[640px] gap-3">
                    <legend class="mb-3 text-center text-base font-medium text-ink">{{ __('platform::signup.plan.how_to_start') }}</legend>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border-2 bg-white p-4 transition"
                            :class="[choice === 'trial' ? 'border-primary' : 'border-(--reg-rail)', trialPlans.includes(plan) ? '' : 'pointer-events-none opacity-50']">
                            <input type="radio" name="choice" value="trial" class="mt-1 accent-primary" x-model="choice" :disabled="! trialPlans.includes(plan)" @checked($initialChoice === 'trial')>
                            <span class="grid gap-0.5"><span class="text-sm font-medium text-ink">{{ __('platform::signup.plan.start_trial') }}</span><span class="text-xs text-(--reg-gray)">{{ __('platform::signup.plan.start_trial_hint') }}</span></span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border-2 bg-white p-4 transition" :class="choice === 'paid' ? 'border-primary' : 'border-(--reg-rail)'">
                            <input type="radio" name="choice" value="paid" class="mt-1 accent-primary" x-model="choice" @checked($initialChoice === 'paid')>
                            <span class="grid gap-0.5">
                                <span class="text-sm font-medium text-ink">{{ __('platform::signup.plan.pay_now') }}</span>
                                <span class="text-xs text-(--reg-gray)" x-show="term === 'monthly'">{{ __('platform::signup.plan.pay_now_hint', ['term' => mb_strtolower(__('platform::signup.plan.monthly'))]) }}</span>
                                <span class="text-xs text-(--reg-gray)" x-show="term === 'yearly'" x-cloak>{{ __('platform::signup.plan.pay_now_hint', ['term' => mb_strtolower(__('platform::signup.plan.yearly'))]) }}</span>
                            </span>
                        </label>
                    </div>
                    <p class="text-center text-sm text-danger" data-message-for="choice" role="status" @error('choice') @else hidden @enderror>@error('choice'){{ $message }}@enderror</p>
                </fieldset>

                <div class="flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ route('central.register') }}" class="inline-flex h-12 items-center rounded-xl border border-(--reg-border) bg-white px-7 text-base font-medium text-ink transition hover:bg-(--reg-readonly)">{{ __('platform::signup.plan.back') }}</a>
                    <button type="submit" class="group inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-primary px-7 text-base font-medium text-white transition hover:bg-primary-hover active:scale-[0.99] disabled:cursor-wait disabled:opacity-80" data-submit>
                        <span class="hidden size-4 animate-spin rounded-full border-2 border-white/40 border-t-white" data-spinner aria-hidden="true"></span>
                        <span x-show="choice === 'paid'">{{ __('platform::signup.plan.continue_payment') }}</span>
                        <span x-show="choice !== 'paid'" x-cloak>{{ __('platform::signup.plan.continue') }}</span>
                        <x-ui::icon name="chevron-right" :size="18" class="transition-transform group-hover:translate-x-0.5 rtl:rotate-180 rtl:group-hover:-translate-x-0.5" />
                    </button>
                </div>
            </form>
        @endif
    </div>
@endsection
