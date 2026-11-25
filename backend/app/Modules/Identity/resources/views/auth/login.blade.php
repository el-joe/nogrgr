{{--
    Panel sign-in (admin / vendor / affiliate), Figma Login 55:20387.
    Inputs: $surface, $action (POST target), optional $store (vendor central-host store
    slug) and $stats (admin: live platform counts). "Forgot password?" and "Continue with
    Google" render only when the surface registers those routes (no dead links).
--}}
@extends('identity::auth._panel', ['title' => __('identity::auth.'.$surface.'_login')])

@php
    $shell = app(\App\Shared\Navigation\PanelShell::class);
    $forgotUrl = $shell->url($surface, 'password.request');
    $googleUrl = $shell->url($surface, 'auth.google');
@endphp

@section('card')
    <span class="mb-4 grid size-11 place-items-center rounded-control bg-primary-subtle text-ink-brand">
        <x-ui::icon name="shield" :size="22" />
    </span>
    <h1 id="auth-title" class="text-2xl font-semibold leading-8 text-ink">{{ __('identity::auth.'.$surface.'_login') }}</h1>
    <p class="mt-2 text-sm text-ink-secondary">{{ __('identity::auth.panel.'.$surface.'.intro') }}</p>

    <form method="POST" action="{{ $action }}" class="mt-6 grid gap-5" data-login-form novalidate>
        @csrf
        @if (! empty($store))
            <input type="hidden" name="store" value="{{ $store }}">
        @endif
        <x-ui::input name="email" type="email" :label="__('identity::auth.email')" icon="mail" autocomplete="username" required autofocus dir="ltr" />
        <x-ui::input name="password" type="password" :label="__('identity::auth.password')" icon="key" autocomplete="current-password" required />
        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-ui::checkbox name="remember" value="1" :label="__('identity::auth.remember')" />
            @if ($forgotUrl)
                <a href="{{ $forgotUrl }}" class="text-[13px] font-medium text-ink-brand hover:underline">{{ __('identity::auth.forgot') }}</a>
            @endif
        </div>
        <x-ui::button type="submit" size="lg" block :loading-text="__('identity::auth.login')">{{ __('identity::auth.login') }}</x-ui::button>
    </form>

    @if ($googleUrl)
        <div class="my-5 flex items-center gap-3 text-xs text-ink-muted">
            <span class="h-px flex-1 bg-line"></span>{{ __('identity::auth.or') }}<span class="h-px flex-1 bg-line"></span>
        </div>
        <x-ui::button :href="$googleUrl" variant="secondary" size="lg" block>{{ __('identity::auth.google') }}</x-ui::button>
    @endif

    @if ($surface === 'admin')
        <p class="mt-6 flex items-center justify-center gap-1.5 text-xs text-ink-muted">
            <x-ui::icon name="shield" :size="14" />{{ __('identity::auth.audit_note') }}
        </p>
    @endif
@endsection
