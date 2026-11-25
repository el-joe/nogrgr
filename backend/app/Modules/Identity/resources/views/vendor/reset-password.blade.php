{{-- Vendor reset password + invalid / expired link state (VEN-003, Figma 82:99866). --}}
@extends('identity::vendor._frame', ['title' => __('identity::vendor_auth.reset.title')])

@php
    $invalid = $invalid || session('vendor_reset_invalid');
@endphp

@section('card')
    <span class="mb-4 grid size-11 place-items-center rounded-control bg-primary-subtle text-ink-brand">
        <x-ui::icon name="key" :size="22" />
    </span>
    <h1 id="auth-title" class="text-2xl font-semibold leading-8 text-ink">{{ __('identity::vendor_auth.reset.title') }}</h1>
    <p class="mt-3 text-sm leading-6 text-ink-secondary">
        @if ($email)
            {!! __('identity::vendor_auth.reset.intro_for', ['email' => '<span dir="ltr">'.e($email).'</span>']) !!}
        @else
            {{ __('identity::vendor_auth.reset.intro') }}
        @endif
    </p>

    <form method="POST" action="{{ $action }}" class="mt-6 grid gap-5" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ old('email', $email) }}">
        <x-ui::input name="password" type="password" :label="__('identity::vendor_auth.reset.new_password')" autocomplete="new-password" required autofocus :disabled="$invalid" />
        <x-ui::input name="password_confirmation" type="password" :label="__('identity::vendor_auth.reset.confirm_password')" autocomplete="new-password" required :disabled="$invalid" />

        @if ($invalid)
            <div class="flex items-start gap-3 rounded-control bg-danger-bg px-4 py-3 text-[13px] text-ink" role="alert" data-reset-invalid>
                <x-ui::icon name="alert-triangle" :size="18" class="mt-0.5 shrink-0 text-danger" />
                <span>{{ __('identity::vendor_auth.reset.invalid') }} <a href="{{ $requestUrl }}" class="font-medium text-ink-brand underline">{{ __('identity::vendor_auth.reset.request_new') }}</a></span>
            </div>
        @endif

        <x-ui::button type="submit" size="lg" block :disabled="$invalid" :loading-text="__('identity::vendor_auth.reset.submit')">{{ __('identity::vendor_auth.reset.submit') }}</x-ui::button>
    </form>

    <p class="mt-6 text-center text-[13px]">
        <a href="{{ $loginUrl }}" class="font-medium text-ink-brand hover:underline">{{ __('identity::auth.reset.back_to_login') }}</a>
    </p>
@endsection
