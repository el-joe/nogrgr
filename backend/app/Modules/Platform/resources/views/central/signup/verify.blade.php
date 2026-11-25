{{--
    WEB-017 Verify your e-mail (Figma Central Website additions 125:10925, resent 125:11066,
    mobile 125:11200): resend with a countdown (server-enforced cooldown + send limit) and
    "Wrong e-mail? Change it". Inputs: $registration, $cooldown, $resendsLeft, $canChangeEmail.
--}}
@extends('platform::central.register._page', ['title' => __('platform::signup.verify.title'), 'page' => 'register-verify'])

@php
    $domain = \Illuminate\Support\Str::after($registration->email, '@');
    $mailApps = ['gmail.com' => 'https://mail.google.com/', 'googlemail.com' => 'https://mail.google.com/', 'outlook.com' => 'https://outlook.live.com/mail/', 'hotmail.com' => 'https://outlook.live.com/mail/', 'live.com' => 'https://outlook.live.com/mail/', 'yahoo.com' => 'https://mail.yahoo.com/', 'icloud.com' => 'https://www.icloud.com/mail'];
    $mailUrl = $mailApps[$domain] ?? 'mailto:';
@endphp

@section('content')
    <div class="flex flex-col items-center gap-6 px-4 pt-6 pb-16 sm:gap-8 sm:px-6 lg:pt-14 lg:pb-24">
        @include('platform::central.register.partials.steps', ['current' => 1, 'done' => ['plan', 'payment']])

        <section class="reg-enter grid w-full max-w-[544px] justify-items-center gap-4 rounded-[24px] bg-white p-6 text-center shadow-(--reg-card-shadow) sm:p-12" x-data="{ editing: {{ $errors->has('email') ? 'true' : 'false' }} }">
            @include('platform::central.signup._state-icon', ['tone' => 'primary', 'icon' => 'mail'])
            <h1 class="text-2xl leading-8 font-semibold text-ink sm:text-[28px]">{{ __('platform::signup.verify.title') }}</h1>
            <p class="text-sm leading-6 text-(--reg-gray)">{{ __('platform::signup.verify.text', ['email' => $registration->email]) }}</p>

            @if (session('resent'))
                <p class="reg-pop flex w-full items-center gap-2 rounded-xl bg-success-bg px-4 py-3 text-start text-[13px] text-ink" role="status">
                    <x-ui::icon name="check-circle" :size="18" class="shrink-0 text-success" />{{ __('platform::signup.verify.resent') }}
                </p>
            @endif
            @if (session('error'))
                <p class="flex w-full items-center gap-2 rounded-xl bg-danger-bg px-4 py-3 text-start text-[13px] text-danger" role="alert">
                    <x-ui::icon name="x-circle" :size="18" class="shrink-0" />{{ session('error') }}
                </p>
            @endif

            <a href="{{ $mailUrl }}" @if ($mailUrl !== 'mailto:') target="_blank" rel="noopener" @endif class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary px-6 text-base font-medium text-white transition hover:bg-primary-hover active:scale-[0.99]">
                <x-ui::icon name="mail" :size="18" />{{ __('platform::signup.verify.open_app') }}
            </a>

            <form method="POST" action="{{ route('central.register.verify.resend') }}" class="flex flex-wrap items-center justify-center gap-1.5 text-[13px]" data-resend-form data-cooldown="{{ $cooldown }}" data-label-wait="{{ __('platform::signup.verify.resend_in', ['time' => '__TIME__']) }}" data-label-ready="{{ __('platform::signup.verify.resend') }}">
                @csrf
                <span class="text-(--reg-gray)">{{ __('platform::signup.verify.didnt_get') }}</span>
                @if ($resendsLeft > 0)
                    <button type="submit" class="font-medium text-primary hover:underline disabled:cursor-default disabled:text-ink disabled:no-underline" data-resend-button @disabled($cooldown > 0)>
                        <span data-resend-label>{{ $cooldown > 0 ? __('platform::signup.verify.resend_in', ['time' => sprintf('00:%02d', $cooldown)]) : __('platform::signup.verify.resend') }}</span>
                    </button>
                @else
                    <span class="text-ink">{{ __('platform::signup.verify.limit_reached') }}</span>
                @endif
            </form>

            @if ($canChangeEmail && $resendsLeft > 0)
                <button type="button" class="text-[13px] font-medium text-primary hover:underline" x-show="! editing" @click="editing = true; $nextTick(() => $refs.email.focus())">{{ __('platform::signup.verify.wrong_email') }}</button>
                <form method="POST" action="{{ route('central.register.verify.email') }}" class="grid w-full gap-3 text-start" x-show="editing" x-cloak data-register-form novalidate>
                    @csrf
                    <div class="grid gap-1.5">
                        <label for="reg-new-email" @class(['reg-field flex h-14 flex-col justify-center gap-0.5 rounded-lg border bg-white px-4', 'border-(--reg-border)' => ! $errors->has('email')]) @error('email') data-state="error" @enderror>
                            <span class="text-[11px] leading-[15px] text-(--reg-muted)">{{ __('platform::signup.verify.new_email') }} *</span>
                            <input id="reg-new-email" x-ref="email" name="email" type="email" dir="ltr" autocomplete="email" required maxlength="191" value="{{ old('email') }}" class="w-full bg-transparent p-0 text-[15px] text-ink outline-none" aria-describedby="reg-new-email-message">
                        </label>
                        <p id="reg-new-email-message" class="text-[13px] text-danger" role="status" @error('email') @else hidden @enderror>@error('email'){{ $message }}@enderror</p>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" class="inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-primary px-5 text-sm font-medium text-white hover:bg-primary-hover disabled:opacity-80" data-submit>
                            <span class="hidden size-4 animate-spin rounded-full border-2 border-white/40 border-t-white" data-spinner aria-hidden="true"></span>{{ __('platform::signup.verify.save_email') }}
                        </button>
                        <button type="button" class="inline-flex h-11 items-center rounded-xl border border-(--reg-border) px-5 text-sm font-medium text-ink hover:bg-(--reg-readonly)" @click="editing = false">{{ __('platform::signup.verify.cancel') }}</button>
                    </div>
                </form>
            @endif
        </section>
    </div>
@endsection
