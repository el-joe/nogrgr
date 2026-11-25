{{-- Vendor sign-in on the store host (VEN-001, Figma 82:99816). Inputs: $action, $storeName, $panelHost. --}}
@extends('identity::vendor._frame', ['title' => __('identity::vendor_auth.login.title')])

@section('card')
    <span class="mb-4 grid size-11 place-items-center rounded-control bg-primary-subtle text-ink-brand">
        <x-ui::icon name="store" :size="22" />
    </span>
    <h1 id="auth-title" class="text-2xl font-semibold leading-8 text-ink">{{ __('identity::vendor_auth.login.title') }}</h1>
    @if ($storeName)
        <p class="mt-3 text-[13px] text-ink-secondary">{{ $storeName }} · <span dir="ltr">{{ $panelHost }}</span></p>
    @endif

    <form method="POST" action="{{ $action }}" class="mt-6 grid gap-5" data-login-form novalidate>
        @csrf
        <x-ui::input name="email" type="email" :label="__('identity::auth.email')" autocomplete="username" required autofocus dir="ltr" />
        <x-ui::input name="password" type="password" :label="__('identity::auth.password')" autocomplete="current-password" required />
        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-ui::checkbox name="remember" value="1" :label="__('identity::auth.remember')" />
            <a href="{{ vendor_route('password.request') }}" class="text-[13px] font-medium text-ink-brand hover:underline">{{ __('identity::auth.forgot') }}</a>
        </div>
        <x-ui::button type="submit" size="lg" block :loading-text="__('identity::vendor_auth.login.submit')">{{ __('identity::vendor_auth.login.submit') }}</x-ui::button>
    </form>
@endsection
