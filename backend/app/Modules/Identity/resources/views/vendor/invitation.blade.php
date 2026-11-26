{{--
    Store team invitation landing (VEN-076a, QA-P8-008), in the vendor auth frame
    (82:99866): "Welcome to <store>", set a password, join. Same one-time broker token as
    a reset; posts to password.store with invitation=1. Invalid / expired invitation state.
--}}
@extends('identity::vendor._frame', ['title' => __('identity::vendor_auth.invitation.title', ['store' => $store])])

@php
    $invalid = $invalid || session('vendor_reset_invalid');
@endphp

@section('card')
    <span class="mb-4 grid size-11 place-items-center rounded-control bg-primary-subtle text-ink-brand">
        <x-ui::icon name="users" :size="22" />
    </span>
    <h1 id="auth-title" class="text-2xl font-semibold leading-8 text-ink" data-invitation-title>{{ __('identity::vendor_auth.invitation.title', ['store' => $store]) }}</h1>
    <p class="mt-3 text-sm leading-6 text-ink-secondary">
        @if ($email)
            {!! __('identity::vendor_auth.invitation.intro_for', ['store' => e($store), 'email' => '<span dir="ltr">'.e($email).'</span>']) !!}
        @else
            {{ __('identity::vendor_auth.invitation.intro', ['store' => $store]) }}
        @endif
    </p>

    <form method="POST" action="{{ $action }}" class="mt-6 grid gap-5" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ old('email', $email) }}">
        <input type="hidden" name="invitation" value="1">
        <x-ui::input name="password" type="password" :label="__('identity::vendor_auth.invitation.password')" autocomplete="new-password" required autofocus :disabled="$invalid" />
        <x-ui::input name="password_confirmation" type="password" :label="__('identity::vendor_auth.invitation.confirm_password')" autocomplete="new-password" required :disabled="$invalid" />

        @if ($invalid)
            <div class="flex items-start gap-3 rounded-control bg-danger-bg px-4 py-3 text-[13px] text-ink" role="alert" data-invitation-invalid>
                <x-ui::icon name="alert-triangle" :size="18" class="mt-0.5 shrink-0 text-danger" />
                <span>{{ __('identity::vendor_auth.invitation.invalid') }}</span>
            </div>
        @endif

        <x-ui::button type="submit" size="lg" block :disabled="$invalid" :loading-text="__('identity::vendor_auth.invitation.submit')">{{ __('identity::vendor_auth.invitation.submit') }}</x-ui::button>
    </form>

    <p class="mt-6 text-center text-[13px] text-ink-secondary">
        {{ __('identity::vendor_auth.invitation.have_account') }}
        <a href="{{ $loginUrl }}" class="font-medium text-ink-brand hover:underline">{{ __('identity::vendor_auth.invitation.sign_in') }}</a>
    </p>
@endsection
