{{--
    Affiliate panel error page (AFF-015, Figma Affiliate & Owner 403 119:100939 · 404
    119:100989 · 419 119:101040 · 500 119:101091): code, title, text and the way back.
    Inputs: $code (403|404|419|500), optional $message (a specific 403 reason).
--}}
@php
    $primary = $code === '419'
        ? [route('affiliate.login'), __('affiliates::panel.errors.login')]
        : [route('affiliate.dashboard'), __('affiliates::panel.errors.dashboard')];
    $icons = ['403' => 'lock', '404' => 'search', '419' => 'timer', '500' => 'server'];
@endphp
@extends('identity::auth._panel', ['surface' => 'affiliate', 'title' => __('affiliates::panel.errors.'.$code.'_title')])

@section('card')
    <div class="grid justify-items-center text-center" data-error-code="{{ $code }}">
        <span class="mb-4 grid size-14 place-items-center rounded-full bg-primary-subtle text-ink-brand">
            <x-ui::icon :name="$icons[$code] ?? 'info'" :size="28" />
        </span>
        <p class="text-5xl font-semibold tracking-tight text-ink-brand" dir="ltr">{{ $code }}</p>
        <h1 id="auth-title" class="mt-3 text-2xl font-semibold leading-8 text-ink">{{ __('affiliates::panel.errors.'.$code.'_title') }}</h1>
        <p class="mt-2 text-sm text-ink-secondary">{{ ! empty($message) ? $message : __('affiliates::panel.errors.'.$code.'_text') }}</p>
    </div>
    <div class="mt-6 grid gap-3">
        <x-ui::button :href="$primary[0]" size="lg" block>{{ $primary[1] }}</x-ui::button>
        @if ($code !== '419')
            <x-ui::button :href="route('affiliate.notifications.index')" variant="ghost" block>{{ __('affiliates::panel.errors.notifications') }}</x-ui::button>
        @endif
    </div>
@endsection
