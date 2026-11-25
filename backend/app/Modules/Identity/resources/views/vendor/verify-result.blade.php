{{-- Vendor e-mail verification result: verified / expired (VEN-004, Figma 82:99909). --}}
@extends('identity::vendor._frame', ['title' => $staff ? __('identity::vendor_auth.verify.verified_title') : __('identity::vendor_auth.verify.expired_title')])

@section('card')
    @if ($staff)
        <span class="mb-4 grid size-11 place-items-center rounded-control bg-primary-subtle text-ink-brand">
            <x-ui::icon name="check-circle" :size="22" />
        </span>
        <h1 id="auth-title" class="text-2xl font-semibold leading-8 text-ink">{{ __('identity::vendor_auth.verify.verified_title') }}</h1>
        <p class="mt-3 text-sm leading-6 text-ink-secondary">{{ __('identity::vendor_auth.verify.verified_body', ['name' => \Illuminate\Support\Str::before(trim($staff->name), ' ') ?: $staff->name]) }}</p>
        <p class="mt-5 flex items-center gap-2 rounded-control bg-success-bg px-4 py-3 text-[13px] text-ink" data-verify-state="verified">
            <x-ui::icon name="check-circle" :size="18" class="shrink-0 text-success" />
            <span><span dir="ltr">{{ $staff->email }}</span> {{ __('identity::vendor_auth.verify.verified_at', ['date' => $staff->email_verified_at?->locale(app()->getLocale())->translatedFormat('j M Y H:i')]) }}</span>
        </p>
        <x-ui::button :href="$continueUrl" size="lg" block class="mt-6">{{ __('identity::vendor_auth.verify.continue') }}</x-ui::button>
    @else
        <span class="mb-4 grid size-11 place-items-center rounded-control bg-danger-bg text-danger">
            <x-ui::icon name="alert-triangle" :size="22" />
        </span>
        <h1 id="auth-title" class="text-2xl font-semibold leading-8 text-ink">{{ __('identity::vendor_auth.verify.expired_title') }}</h1>
        <p class="mt-3 text-sm leading-6 text-ink-secondary" data-verify-state="expired">{{ __('identity::vendor_auth.verify.expired_body') }}</p>
        <div class="mt-6 grid gap-3">
            @if ($resendUrl)
                <form method="POST" action="{{ $resendUrl }}">
                    @csrf
                    <x-ui::button type="submit" size="lg" block>{{ __('identity::vendor_auth.verify.resend') }}</x-ui::button>
                </form>
            @else
                <x-ui::button :href="$loginUrl" size="lg" block>{{ __('identity::vendor_auth.verify.sign_in') }}</x-ui::button>
            @endif
        </div>
    @endif
@endsection
