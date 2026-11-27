{{--
    WEB-016 Sign up (Figma Central Website 12:42223, mobile 12:43467): heading + card with
    e-mail, phone (intl-tel-input, visitor country pre-selected), compliance accept and
    "Sign up with Google / Apple" (only providers enabled in Integrations). A signed-in
    owner signs up with the owner e-mail (read-only). Inputs: $draft, $owner, $providers,
    $socialError, $theme (?Theme picked on the central theme preview, WEB-004).
--}}
@extends('platform::central.register._page', ['title' => __('platform::signup.page_title'), 'page' => 'register-signup'])

@php
    $termsUrl = \Illuminate\Support\Facades\Route::has('central.terms') ? route('central.terms') : null;
    $privacyUrl = \Illuminate\Support\Facades\Route::has('central.privacy') ? route('central.privacy') : null;
    $emailValue = $owner?->email ?? old('email', $draft?->email);
    $phoneError = $errors->first('phone') ?: $errors->first('phone_country');
    $appName = config('app.name');
@endphp

@section('content')
    <div class="mx-auto grid w-full max-w-[1200px] items-center gap-8 px-4 pt-8 pb-16 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-[56px] lg:pt-16 lg:pb-24">
        <div class="grid gap-4 max-lg:text-center" data-reveal="fade-up">
            <h1 class="text-[28px] leading-9 font-semibold text-ink sm:text-[34px] sm:leading-[44px]">
                {{ __('platform::signup.form.heading_plain') }} <span class="text-primary">{{ $appName }}</span>
            </h1>
            <p class="max-w-[440px] text-lg leading-7 text-(--reg-gray) max-lg:mx-auto">{{ __('platform::signup.form.intro') }}</p>
        </div>

        <section class="reg-enter grid w-full max-w-[506px] gap-5 justify-self-center rounded-[24px] bg-white p-5 shadow-(--reg-card-shadow) sm:p-10 lg:justify-self-end" aria-labelledby="reg-signup-title">
            <h2 id="reg-signup-title" class="text-xl leading-7 font-medium text-ink">{{ __('platform::signup.form.title') }}</h2>

            @if ($owner)
                <p class="flex items-center gap-2.5 rounded-xl bg-(--reg-tint) px-3.5 py-2.5 text-sm leading-5 text-ink">
                    <x-ui::icon name="users" :size="18" class="shrink-0 text-primary" />
                    <span class="min-w-0 flex-1 break-words">{{ __('platform::signup.form.signed_in_as', ['email' => $owner->email]) }}</span>
                </p>
            @endif

            @if (! empty($theme))
                <p class="flex items-center gap-2.5 rounded-xl bg-(--reg-tint) px-3.5 py-2.5 text-sm leading-5 text-ink" data-preselected-theme="{{ $theme->slug }}">
                    <x-ui::icon name="layers" :size="18" class="shrink-0 text-primary" />
                    <span class="min-w-0 flex-1 break-words">{{ __('platform::signup.form.theme_picked', ['theme' => $theme->name]) }}</span>
                </p>
            @endif

            @if ($socialError || session('error'))
                <p class="flex items-start gap-2 rounded-xl bg-danger-bg px-3.5 py-2.5 text-sm leading-5 text-danger" role="alert">
                    <x-ui::icon name="x-circle" :size="18" class="mt-px shrink-0" />{{ session('error') ?? __('platform::signup.form.social_error') }}
                </p>
            @endif

            <form method="POST" action="{{ route('central.register.store') }}" class="grid gap-4" data-register-form data-signup-form novalidate>
                @csrf

                @include('platform::central.register.partials.field', [
                    'name' => 'email',
                    'label' => __('platform::signup.form.email'),
                    'type' => 'email',
                    'value' => $emailValue,
                    'required' => ! $owner,
                    'readonly' => (bool) $owner,
                    'autocomplete' => 'email',
                    'inputmode' => 'email',
                    'dir' => 'ltr',
                    'maxlength' => 191,
                ])

                <div class="grid gap-1.5" data-field="phone">
                    <label for="reg-phone" @class(['reg-field reg-phone flex h-14 items-center rounded-lg border bg-white px-2', 'border-(--reg-border)' => ! $phoneError]) @if ($phoneError) data-state="error" @endif>
                        <span class="sr-only">{{ __('platform::signup.form.phone') }} *</span>
                        <input id="reg-phone" name="phone" type="tel" data-phone autocomplete="tel" dir="ltr" required
                            value="{{ old('phone', $draft?->phone ?? $owner?->phone) }}"
                            placeholder="{{ __('platform::signup.form.phone') }} *"
                            aria-describedby="reg-phone-hint reg-phone-message" @if ($phoneError) aria-invalid="true" @endif
                            class="w-full min-w-0 bg-transparent p-0 text-[15px] leading-[21px] text-ink outline-none placeholder:text-(--reg-muted)">
                    </label>
                    <p id="reg-phone-hint" class="text-[13px] leading-[18px] text-(--reg-muted)">{{ __('platform::signup.form.phone_hint') }}</p>
                    <p id="reg-phone-message" class="text-[13px] leading-[18px] text-danger" data-message-for="phone" role="status" @if (! $phoneError) hidden @endif>{{ $phoneError }}</p>
                </div>

                <div class="grid gap-1.5" data-field="compliance">
                    <label class="flex cursor-pointer items-start gap-2.5 text-sm leading-5 text-ink">
                        <input type="checkbox" name="compliance" value="1" class="peer sr-only" @checked(old('compliance', $draft !== null)) required aria-describedby="reg-compliance-message" data-compliance>
                        <span class="mt-px grid size-5 shrink-0 place-items-center rounded-[5px] border border-(--reg-border) bg-white text-transparent transition peer-checked:border-primary peer-checked:bg-primary peer-checked:text-white peer-focus-visible:ring-3 peer-focus-visible:ring-primary/25" aria-hidden="true">
                            <x-ui::icon name="check" :size="14" />
                        </span>
                        <span>
                            {{ __('platform::signup.form.compliance_prefix') }}
                            @if ($termsUrl)<a href="{{ $termsUrl }}" class="text-primary hover:underline" target="_blank" rel="noopener">{{ __('platform::signup.form.terms') }}</a>@else{{ __('platform::signup.form.terms') }}@endif
                            {{ __('platform::signup.form.and') }}
                            @if ($privacyUrl)<a href="{{ $privacyUrl }}" class="text-primary hover:underline" target="_blank" rel="noopener">{{ __('platform::signup.form.privacy') }}</a>@else{{ __('platform::signup.form.privacy') }}@endif{{ __('platform::signup.form.compliance_suffix') }}
                        </span>
                    </label>
                    <p id="reg-compliance-message" class="text-[13px] leading-[18px] text-danger" data-message-for="compliance" role="status" @error('compliance') @else hidden @enderror>@error('compliance'){{ $message }}@enderror</p>
                </div>

                <button type="submit" class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary px-7 text-base leading-[22px] font-medium text-white transition hover:bg-primary-hover active:scale-[0.99] disabled:cursor-wait disabled:opacity-80" data-submit>
                    <span class="hidden size-4 animate-spin rounded-full border-2 border-white/40 border-t-white" data-spinner aria-hidden="true"></span>
                    <span>{{ __('platform::signup.form.submit') }}</span>
                </button>

                @if (! $owner && $providers !== [])
                    <div class="flex items-center gap-4 text-base text-(--reg-gray)" aria-hidden="true">
                        <span class="h-px flex-1 bg-(--reg-rail)"></span>{{ __('platform::signup.form.or_with') }}<span class="h-px flex-1 bg-(--reg-rail)"></span>
                    </div>
                    <div @class(['grid gap-3', 'sm:grid-cols-2' => count($providers) > 1])>
                        @foreach ($providers as $provider)
                            <button type="submit" name="provider" value="{{ $provider->value }}" class="inline-flex h-11 items-center justify-center gap-2.5 rounded-lg border border-(--reg-border) bg-white px-4 text-sm font-medium text-ink transition hover:border-ink/40 hover:bg-(--reg-readonly) active:scale-[0.99]" data-social-submit title="{{ __('platform::signup.form.social_needs_terms', ['provider' => $provider->brand()]) }}">
                                <x-ui::icon :name="$provider->value" :size="20" class="shrink-0" />
                                <span>{{ __('platform::signup.form.social', ['provider' => $provider->brand()]) }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif

                <p class="text-center text-sm text-(--reg-gray)">
                    {{ __('platform::signup.form.have_account') }}
                    <a href="{{ route('central.tenant.login') }}" class="font-medium text-primary hover:underline">{{ __('platform::signup.form.login') }}</a>
                </p>
            </form>
        </section>
    </div>
@endsection
