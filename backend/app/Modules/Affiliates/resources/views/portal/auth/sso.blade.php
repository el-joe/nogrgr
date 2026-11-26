{{--
    SSO landing error (AFF-007, Figma Affiliate & Owner 119:100873): the one-time link from
    the vendor panel is invalid, already used or expired. Inputs: $surface, $failure.
--}}
@extends('identity::auth._panel', ['title' => __('affiliates::panel.sso.'.$failure->value.'_title')])

@section('card')
    <div class="grid justify-items-center text-center" data-sso-failure="{{ $failure->value }}">
        <span class="mb-4 grid size-14 place-items-center rounded-full bg-danger-bg text-danger">
            <x-ui::icon :name="$failure === \App\Modules\Affiliates\Enums\SsoFailure::Expired ? 'timer' : 'lock'" :size="28" />
        </span>
        <h1 id="auth-title" class="text-2xl font-semibold leading-8 text-ink">{{ __('affiliates::panel.sso.'.$failure->value.'_title') }}</h1>
        <p class="mt-2 text-sm text-ink-secondary">{{ __('affiliates::panel.sso.'.$failure->value.'_text') }}</p>
    </div>
    <p class="mt-6 flex items-start gap-2 rounded-control bg-info-bg px-4 py-3 text-xs text-ink">
        <x-ui::icon name="info" :size="16" class="shrink-0 text-info" />{{ __('affiliates::panel.sso.hint') }}
    </p>
    <x-ui::button :href="route('affiliate.login')" size="lg" block class="mt-6">{{ __('affiliates::panel.sso.login') }}</x-ui::button>
@endsection
