{{--
    E-mail confirmation result (AFF-006, Figma Affiliate & Owner 119:100720 · expired
    119:100776). $affiliate = the confirmed account, or null for an expired / invalid link
    (then the "send a new link" form). Input: $surface.
--}}
@extends('identity::auth._panel', ['title' => $affiliate ? __('affiliates::panel.verify.done_title') : __('affiliates::panel.verify.expired_title')])

@section('card')
    <div class="grid justify-items-center text-center">
        <span @class(['mb-4 grid size-14 place-items-center rounded-full', 'bg-success-bg text-success' => $affiliate, 'bg-danger-bg text-danger' => ! $affiliate])>
            <x-ui::icon :name="$affiliate ? 'check-circle' : 'alert-triangle'" :size="28" />
        </span>
        <h1 id="auth-title" class="text-2xl font-semibold leading-8 text-ink">{{ $affiliate ? __('affiliates::panel.verify.done_title') : __('affiliates::panel.verify.expired_title') }}</h1>
        <p class="mt-2 text-sm text-ink-secondary">
            @if ($affiliate)
                {{ $affiliate->isActive() ? __('affiliates::panel.verify.done_active') : __('affiliates::panel.verify.done_pending') }}
            @else
                {{ __('affiliates::panel.verify.expired_text') }}
            @endif
        </p>
    </div>

    @if ($affiliate)
        <x-ui::button :href="$affiliate->isActive() ? route('affiliate.dashboard') : route('affiliate.login')" size="lg" block class="mt-6">{{ $affiliate->isActive() ? __('affiliates::panel.verify.continue') : __('affiliates::panel.register.login') }}</x-ui::button>
    @else
        <form method="POST" action="{{ route('affiliate.verification.resend') }}" class="mt-6 grid gap-4" novalidate>
            @csrf
            <x-ui::input name="email" type="email" :label="__('affiliates::panel.register.email')" icon="mail" autocomplete="email" required dir="ltr" />
            <x-ui::button type="submit" size="lg" block icon="mail">{{ __('affiliates::panel.verify.resend') }}</x-ui::button>
        </form>
        <x-ui::button :href="route('affiliate.login')" variant="ghost" block class="mt-3">{{ __('affiliates::panel.register.login') }}</x-ui::button>
    @endif
@endsection
