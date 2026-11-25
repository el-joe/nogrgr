{{-- Vendor forgot password + "link sent" state (VEN-002, Figma 82:99866). Inputs: $action, $loginUrl, $sentTo. --}}
@extends('identity::vendor._frame', ['title' => __('identity::vendor_auth.forgot.title')])

@section('card')
    <span class="mb-4 grid size-11 place-items-center rounded-control bg-primary-subtle text-ink-brand">
        <x-ui::icon name="{{ $sentTo ? 'mail' : 'key' }}" :size="22" />
    </span>
    @if ($sentTo)
        <h1 id="auth-title" class="text-2xl font-semibold leading-8 text-ink">{{ __('identity::vendor_auth.forgot.sent_title') }}</h1>
        <p class="mt-3 text-sm leading-6 text-ink-secondary" data-reset-sent>{!! __('identity::vendor_auth.forgot.sent_body', ['email' => '<span class="font-medium text-ink" dir="ltr">'.e($sentTo).'</span>']) !!}</p>
        <div class="mt-5 flex items-start gap-3 rounded-control bg-success-bg px-4 py-3 text-[13px] text-ink">
            <x-ui::icon name="check-circle" :size="18" class="mt-0.5 shrink-0 text-success" />
            <span>{{ __('identity::vendor_auth.forgot.sent_hint') }}</span>
        </div>
        <div class="mt-6 grid gap-3">
            <x-ui::button :href="$loginUrl" size="lg" block>{{ __('identity::auth.reset.back_to_login') }}</x-ui::button>
            <form method="POST" action="{{ $action }}">
                @csrf
                <input type="hidden" name="email" value="{{ $sentTo }}">
                <x-ui::button type="submit" variant="secondary" size="lg" block :loading-text="__('identity::vendor_auth.forgot.resend')">{{ __('identity::vendor_auth.forgot.resend') }}</x-ui::button>
            </form>
        </div>
    @else
        <h1 id="auth-title" class="text-2xl font-semibold leading-8 text-ink">{{ __('identity::vendor_auth.forgot.title') }}</h1>
        <p class="mt-3 text-sm leading-6 text-ink-secondary">{{ __('identity::vendor_auth.forgot.intro') }}</p>

        <form method="POST" action="{{ $action }}" class="mt-6 grid gap-5" novalidate>
            @csrf
            <x-ui::input name="email" type="email" :label="__('identity::auth.email')" autocomplete="username" required autofocus dir="ltr" />
            <x-ui::button type="submit" size="lg" block :loading-text="__('identity::auth.reset.send_link')">{{ __('identity::auth.reset.send_link') }}</x-ui::button>
        </form>

        <p class="mt-6 text-center text-[13px]">
            <a href="{{ $loginUrl }}" class="font-medium text-ink-brand hover:underline">{{ __('identity::auth.reset.back_to_login') }}</a>
        </p>
    @endif
@endsection
