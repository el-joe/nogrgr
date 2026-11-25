{{--
    WEB-018 Complete registration — account (Figma Central Website additions 125:11277,
    mobile 125:11433): owner name + password; e-mail (and phone) from the sign-up are
    read-only. Inputs: $registration, $token, $accountSaved.
--}}
@extends('platform::central.register._page', ['title' => __('platform::register.page_title'), 'page' => 'register-account'])

@php
    $meta = $registration->meta ?? [];
    $provider = $meta['social_provider'] ?? ($meta['provider'] ?? null);
    $providerName = is_string($provider) && $provider !== '' ? \Illuminate\Support\Str::ucfirst($provider) : null;
    $termsUrl = \Illuminate\Support\Facades\Route::has('central.terms') ? route('central.terms') : null;
    $privacyUrl = \Illuminate\Support\Facades\Route::has('central.privacy') ? route('central.privacy') : null;
@endphp

@section('content')
    <div class="flex flex-col items-center gap-6 px-4 pt-6 pb-16 sm:gap-8 sm:px-6 lg:px-[120px] lg:pt-14 lg:pb-24">
        @include('platform::central.register.partials.steps', ['current' => 1])

        <section class="reg-enter grid w-full max-w-[560px] gap-4 rounded-[20px] bg-white p-5 shadow-(--reg-card-shadow) sm:rounded-[24px] sm:p-10" aria-labelledby="reg-account-title">
            <h1 id="reg-account-title" class="text-[22px] leading-8 font-semibold text-ink sm:text-[26px] sm:leading-9">{{ __('platform::register.account.title') }}</h1>

            @if ($providerName)
                <p class="flex items-center gap-2.5 rounded-xl bg-(--reg-tint) px-3.5 py-2.5 text-sm leading-5 text-ink">
                    <x-ui::icon :name="strtolower($providerName) === 'apple' ? 'apple' : 'google'" :size="20" class="shrink-0" />
                    <span class="min-w-0 flex-1 break-words">{{ __('platform::register.account.social_banner', ['provider' => $providerName, 'email' => $registration->email]) }}</span>
                </p>
            @endif

            <form method="POST" action="{{ route('central.register.complete.account') }}" class="grid gap-4" data-register-form novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                @include('platform::central.register.partials.field', [
                    'name' => 'name',
                    'label' => __('platform::register.account.name'),
                    'value' => $registration->owner_name,
                    'required' => true,
                    'autocomplete' => 'name',
                    'maxlength' => 120,
                ])

                @include('platform::central.register.partials.field', [
                    'name' => 'email',
                    'label' => __('platform::register.account.email'),
                    'type' => 'email',
                    'value' => $registration->email,
                    'readonly' => true,
                    'required' => true,
                    'dir' => 'ltr',
                    'hint' => $providerName ? __('platform::register.account.email_from_provider', ['provider' => $providerName]) : __('platform::register.account.email_hint'),
                ])

                @if ($registration->phone)
                    @include('platform::central.register.partials.field', [
                        'name' => 'phone',
                        'label' => __('platform::register.account.phone'),
                        'type' => 'tel',
                        'value' => $registration->phone,
                        'readonly' => true,
                        'required' => true,
                        'icon' => 'globe',
                        'dir' => 'ltr',
                    ])
                @endif

                @include('platform::central.register.partials.field', [
                    'name' => 'password',
                    'label' => __('platform::register.account.password'),
                    'type' => 'password',
                    'required' => true,
                    'autocomplete' => 'new-password',
                    'toggle' => true,
                    'hint' => __('platform::register.account.password_hint'),
                ])

                @include('platform::central.register.partials.field', [
                    'name' => 'password_confirmation',
                    'label' => __('platform::register.account.password_confirmation'),
                    'type' => 'password',
                    'required' => true,
                    'autocomplete' => 'new-password',
                    'toggle' => true,
                ])

                <div class="grid gap-1.5" data-field="terms">
                    <label class="flex cursor-pointer items-start gap-2.5 text-sm leading-5 text-ink">
                        <input type="checkbox" name="terms" value="1" class="peer sr-only" @checked(old('terms', $accountSaved)) required aria-describedby="reg-terms-message">
                        <span class="mt-px grid size-5 shrink-0 place-items-center rounded-[5px] border border-(--reg-border) bg-white text-transparent transition peer-checked:border-primary peer-checked:bg-primary peer-checked:text-white peer-focus-visible:ring-3 peer-focus-visible:ring-primary/25" aria-hidden="true">
                            <x-ui::icon name="check" :size="14" />
                        </span>
                        <span>
                            {{ __('platform::register.account.terms_prefix') }}
                            @if ($termsUrl)
                                <a href="{{ $termsUrl }}" class="text-primary hover:underline" target="_blank" rel="noopener">{{ __('platform::register.account.terms') }}</a>
                            @else
                                {{ __('platform::register.account.terms') }}
                            @endif
                            {{ __('platform::register.account.and') }}
                            @if ($privacyUrl)
                                <a href="{{ $privacyUrl }}" class="text-primary hover:underline" target="_blank" rel="noopener">{{ __('platform::register.account.privacy') }}</a>
                            @else
                                {{ __('platform::register.account.privacy') }}
                            @endif
                        </span>
                    </label>
                    <p id="reg-terms-message" class="text-[13px] leading-[18px] text-danger" data-message-for="terms" role="status" @error('terms') @else hidden @enderror>@error('terms'){{ $message }}@enderror</p>
                </div>

                <button type="submit" class="group inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary px-7 text-base leading-[22px] font-medium text-white transition hover:bg-primary-hover active:scale-[0.99] disabled:cursor-wait disabled:opacity-80" data-submit>
                    <span class="hidden size-4 animate-spin rounded-full border-2 border-white/40 border-t-white" data-spinner aria-hidden="true"></span>
                    <span>{{ __('platform::register.account.continue') }}</span>
                    <x-ui::icon name="chevron-right" :size="18" class="transition-transform group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5" />
                </button>
            </form>
        </section>
    </div>
@endsection
