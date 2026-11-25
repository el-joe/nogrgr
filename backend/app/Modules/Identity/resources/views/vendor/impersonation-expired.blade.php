{{-- Impersonation accept link expired / already used (VEN-005 error state). Input: $adminUrl. --}}
@extends('identity::vendor._frame', ['title' => __('identity::vendor_auth.impersonation.expired_title')])

@section('card')
    <span class="mb-4 grid size-11 place-items-center rounded-control bg-danger-bg text-danger">
        <x-ui::icon name="clock" :size="22" />
    </span>
    <h1 id="auth-title" class="text-2xl font-semibold leading-8 text-ink">{{ __('identity::vendor_auth.impersonation.expired_title') }}</h1>
    <p class="mt-3 text-sm leading-6 text-ink-secondary" data-impersonation-expired>{{ __('identity::vendor_auth.impersonation.expired_body') }}</p>
    @if ($adminUrl)
        <x-ui::button :href="$adminUrl" size="lg" block class="mt-6">{{ __('identity::vendor_auth.impersonation.back_to_admin') }}</x-ui::button>
    @endif
@endsection
