{{--
    WEB-018a Complete registration — store (Figma Central Website additions 125:11537,
    validation 125:11701, custom domain / DNS check / profit % 133:12577, mobile
    125:11865). Categories are not part of this release and stay hidden.
    Inputs: $registration, $token, $accountUrl, $baseDomain.
--}}
@extends('platform::central.register._page', ['title' => __('platform::register.page_title'), 'page' => 'register-store'])

@php
    $domainType = old('domain_type', $registration->custom_domain ? 'custom' : 'subdomain');
    $profitDefault = $registration->profit_percent_bps !== null ? rtrim(rtrim(number_format($registration->profit_percent_bps / 100, 2, '.', ''), '0'), '.') : null;
    $config = [
        'availabilityUrl' => route('central.register.availability'),
        'token' => $token,
        'baseDomain' => $baseDomain,
        'i18n' => [
            'checking' => __('platform::register.store.checking'),
            'checkFailed' => __('platform::register.store.check_failed'),
            'dnsFound' => __('platform::register.store.dns_found'),
            'dnsMissing' => __('platform::register.store.dns_missing'),
            'dnsAfterCreation' => __('platform::register.store.dns_after_creation'),
            'dnsError' => __('platform::register.store.dns_error'),
            'dnsNote' => __('platform::register.store.dns_note', ['host' => ':host']),
        ],
    ];
@endphp

@section('content')
    <div class="flex flex-col items-center gap-6 px-4 pt-6 pb-16 sm:gap-8 sm:px-6 lg:px-[120px] lg:pt-14 lg:pb-24">
        @include('platform::central.register.partials.steps', ['current' => 4])

        <section class="reg-enter grid w-full max-w-[560px] gap-4 rounded-[20px] bg-white p-5 shadow-(--reg-card-shadow) sm:rounded-[24px] sm:p-10" aria-labelledby="reg-store-title">
            <div class="grid gap-2">
                <h1 id="reg-store-title" class="text-[22px] leading-8 font-semibold text-ink sm:text-[26px] sm:leading-9">{{ __('platform::register.store.title') }}</h1>
                <p class="text-sm leading-5 text-(--reg-muted)">{{ __('platform::register.store.intro') }}</p>
            </div>

            @if (session('success'))
                <p class="reg-pop flex items-center gap-2.5 rounded-xl bg-success-bg px-3.5 py-2.5 text-sm leading-5 text-success" role="status">
                    <x-ui::icon name="check-circle" :size="18" class="shrink-0" />{{ session('success') }}
                </p>
            @endif
            @if (session('error'))
                <p class="reg-pop flex items-center gap-2.5 rounded-xl bg-danger-bg px-3.5 py-2.5 text-sm leading-5 text-danger" role="alert">
                    <x-ui::icon name="x-circle" :size="18" class="shrink-0" />{{ session('error') }}
                </p>
            @endif

            <script type="application/json" data-register-config>@json($config)</script>

            <form method="POST" action="{{ route('central.register.complete.store') }}" class="grid gap-4" data-register-form data-store-form novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                @include('platform::central.register.partials.field', [
                    'name' => 'store_name',
                    'label' => __('platform::register.store.store_name'),
                    'value' => $registration->store_name,
                    'required' => true,
                    'autocomplete' => 'organization',
                    'maxlength' => 120,
                ])

                <fieldset class="reg-segment grid h-11 grid-cols-2 rounded-xl bg-(--reg-tint) p-1" data-segment data-value="{{ $domainType }}">
                    <legend class="sr-only">{{ __('platform::register.store.domain_type') }}</legend>
                    @foreach (['subdomain', 'custom'] as $option)
                        <label class="relative grid cursor-pointer place-items-center rounded-[10px] text-sm font-medium text-ink transition-colors has-checked:text-primary has-focus-visible:ring-2 has-focus-visible:ring-primary/30">
                            <input type="radio" name="domain_type" value="{{ $option }}" class="sr-only" @checked($domainType === $option)>
                            {{ __('platform::register.store.domain_type_'.$option) }}
                        </label>
                    @endforeach
                </fieldset>

                @include('platform::central.register.partials.field', [
                    'name' => 'slug',
                    'label' => __('platform::register.store.address'),
                    'value' => $registration->slug,
                    'required' => true,
                    'suffix' => '.'.$baseDomain,
                    'dir' => 'ltr',
                    'autocomplete' => 'off',
                    'maxlength' => 63,
                    'status' => true,
                ])

                <div class="reg-collapse" data-custom-panel @if ($domainType !== 'custom') data-collapsed inert @endif>
                    <div class="grid gap-4">
                        @include('platform::central.register.partials.field', [
                            'name' => 'custom_domain',
                            'label' => __('platform::register.store.custom_domain'),
                            'value' => $registration->custom_domain,
                            'required' => true,
                            'dir' => 'ltr',
                            'autocomplete' => 'off',
                            'inputmode' => 'url',
                            'maxlength' => 253,
                            'placeholder' => 'shop.example.com',
                            'status' => true,
                            'extra' => new \Illuminate\Support\HtmlString(
                                '<button type="button" class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg border border-primary px-3 text-sm font-medium text-primary transition hover:bg-(--reg-tint) disabled:cursor-wait disabled:opacity-70" data-dns-check>'
                                .'<span class="hidden size-3.5 animate-spin rounded-full border-2 border-primary/30 border-t-primary" data-spinner aria-hidden="true"></span>'
                                .e(__('platform::register.store.check_dns')).'</button>'
                            ),
                        ])

                        <div class="grid gap-2 rounded-xl border border-(--reg-rail) p-4" data-dns-result hidden>
                            <p class="text-[13px] leading-[18px] text-(--reg-muted)">{{ __('platform::register.store.dns_intro') }}</p>
                            <ul class="grid gap-2" data-dns-records></ul>
                            <p class="text-[13px] leading-[18px] text-(--reg-muted)" data-dns-note></p>
                        </div>
                    </div>
                </div>

                @include('platform::central.register.partials.field', [
                    'name' => 'profit_percent',
                    'label' => __('platform::register.store.profit'),
                    'value' => $profitDefault,
                    'required' => true,
                    'suffix' => '%',
                    'inputmode' => 'decimal',
                    'dir' => 'ltr',
                    'maxlength' => 6,
                    'placeholder' => '25',
                    'hint' => __('platform::register.store.profit_hint'),
                ])

                <button type="submit" class="group mt-1 inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary px-7 text-base leading-[22px] font-medium text-white transition hover:bg-primary-hover active:scale-[0.99] disabled:cursor-wait disabled:opacity-80" data-submit>
                    <span class="hidden size-4 animate-spin rounded-full border-2 border-white/40 border-t-white" data-spinner aria-hidden="true"></span>
                    <span>{{ __('platform::register.store.submit') }}</span>
                    <x-ui::icon name="chevron-right" :size="18" class="transition-transform group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5" />
                </button>

                <a href="{{ $accountUrl }}" class="justify-self-center text-[13px] text-(--reg-muted) underline-offset-4 transition-colors hover:text-primary hover:underline">{{ __('platform::register.store.back') }}</a>
            </form>
        </section>
    </div>
@endsection
