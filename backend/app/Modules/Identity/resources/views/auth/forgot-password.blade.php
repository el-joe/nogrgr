{{-- Panel forgot password. Inputs: $surface, $action, $loginUrl. --}}
@extends('identity::auth._panel', ['title' => __('identity::auth.reset.forgot_title')])

@section('card')
    <span class="mb-4 grid size-11 place-items-center rounded-control bg-primary-subtle text-ink-brand">
        <x-ui::icon name="key" :size="22" />
    </span>
    <h1 id="auth-title" class="text-2xl font-semibold leading-8 text-ink">{{ __('identity::auth.reset.forgot_title') }}</h1>
    <p class="mt-2 text-sm text-ink-secondary">{{ __('identity::auth.reset.forgot_intro') }}</p>

    <form method="POST" action="{{ $action }}" class="mt-6 grid gap-5" novalidate>
        @csrf
        <x-ui::input name="email" type="email" :label="__('identity::auth.email')" icon="mail" autocomplete="username" required autofocus dir="ltr" />
        <x-ui::button type="submit" size="lg" block :loading-text="__('identity::auth.reset.send_link')">{{ __('identity::auth.reset.send_link') }}</x-ui::button>
    </form>

    <p class="mt-6 text-center text-[13px]">
        <a href="{{ $loginUrl }}" class="font-medium text-ink-brand hover:underline">{{ __('identity::auth.reset.back_to_login') }}</a>
    </p>
@endsection
