{{-- Panel reset password. Inputs: $surface, $action, $token, $email, $loginUrl. --}}
@extends('identity::auth._panel', ['title' => __('identity::auth.reset.reset_title')])

@section('card')
    <span class="mb-4 grid size-11 place-items-center rounded-control bg-primary-subtle text-ink-brand">
        <x-ui::icon name="key" :size="22" />
    </span>
    <h1 id="auth-title" class="text-2xl font-semibold leading-8 text-ink">{{ __('identity::auth.reset.reset_title') }}</h1>
    <p class="mt-2 text-sm text-ink-secondary">{{ __('identity::auth.reset.reset_intro') }}</p>

    <form method="POST" action="{{ $action }}" class="mt-6 grid gap-5" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-ui::input name="email" type="email" :value="$email" :label="__('identity::auth.email')" icon="mail" autocomplete="username" required dir="ltr" />
        <x-ui::input name="password" type="password" :label="__('identity::auth.reset.new_password')" icon="key" autocomplete="new-password" required autofocus />
        <x-ui::input name="password_confirmation" type="password" :label="__('identity::auth.reset.confirm_password')" icon="key" autocomplete="new-password" required />
        <x-ui::button type="submit" size="lg" block :loading-text="__('identity::auth.reset.submit')">{{ __('identity::auth.reset.submit') }}</x-ui::button>
    </form>

    <p class="mt-6 text-center text-[13px]">
        <a href="{{ $loginUrl }}" class="font-medium text-ink-brand hover:underline">{{ __('identity::auth.reset.back_to_login') }}</a>
    </p>
@endsection
