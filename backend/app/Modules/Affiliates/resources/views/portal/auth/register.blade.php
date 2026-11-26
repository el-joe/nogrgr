{{--
    Affiliate sign-up (AFF-002, Figma Affiliate & Owner 119:100207 · validation 119:100321 ·
    mobile 119:101191): name, e-mail, phone (intl-tel-input), password + confirmation,
    program terms. Creates a pending account (AFF-003). Inputs: $surface, $commission.
--}}
@extends('identity::auth._panel', ['title' => __('affiliates::panel.register.title')])

@section('card')
    <span class="mb-4 grid size-11 place-items-center rounded-control bg-primary-subtle text-ink-brand">
        <x-ui::icon name="users" :size="22" />
    </span>
    <h1 id="auth-title" class="text-2xl font-semibold leading-8 text-ink">{{ __('affiliates::panel.register.title') }}</h1>
    <p class="mt-2 text-sm text-ink-secondary">{{ __('affiliates::panel.register.intro', ['commission' => $commission]) }}</p>

    <form method="POST" action="{{ route('affiliate.register.store') }}" class="mt-6 grid gap-5" data-affiliate-register novalidate>
        @csrf
        <x-ui::input name="name" :label="__('affiliates::panel.register.name')" icon="user" autocomplete="name" required maxlength="120" autofocus />
        <x-ui::input name="email" type="email" :label="__('affiliates::panel.register.email')" icon="mail" autocomplete="email" required maxlength="191" dir="ltr" />
        <x-ui::phone name="phone" :label="__('affiliates::panel.register.phone')" :helper="__('affiliates::panel.register.phone_hint')" />
        <x-ui::input name="password" type="password" :label="__('affiliates::panel.register.password')" icon="key" autocomplete="new-password" required :helper="__('affiliates::panel.register.password_hint')" />
        <x-ui::input name="password_confirmation" type="password" :label="__('affiliates::panel.register.password_confirmation')" icon="key" autocomplete="new-password" required />
        <x-ui::checkbox name="terms" value="1" :label="__('affiliates::panel.register.terms')" :checked="(bool) old('terms')" />
        <x-ui::button type="submit" size="lg" block :loading-text="__('affiliates::panel.register.submit')">{{ __('affiliates::panel.register.submit') }}</x-ui::button>
    </form>

    <p class="mt-6 text-center text-sm text-ink-secondary">
        {{ __('affiliates::panel.register.have_account') }}
        <a href="{{ route('affiliate.login') }}" class="font-medium text-ink-brand hover:underline">{{ __('affiliates::panel.register.login') }}</a>
    </p>
@endsection
