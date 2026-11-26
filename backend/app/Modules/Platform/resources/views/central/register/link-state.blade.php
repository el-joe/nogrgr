{{--
    WEB-018 invalid / expired / already-used completion link, in the language of the
    client's "Link expired" frame (Figma Central Website additions 127:12867).
    Inputs: $state (invalid|expired|used), $registration (nullable), $hours.
--}}
@extends('platform::central.register._page', ['title' => __('platform::register.page_title'), 'page' => 'register-link'])

@php
    $supportEmail = app(\App\Modules\Platform\Services\OwnerSupportService::class)->supportEmail();
    $mailto = $supportEmail !== '' ? 'mailto:'.$supportEmail.'?subject='.rawurlencode(__('platform::register.link.support_subject')) : null;
    $requestUrl = \Illuminate\Support\Facades\Route::has('central.register') ? route('central.register') : null;

    if ($state === 'used') {
        $primaryUrl = route('central.tenant.login', array_filter(['store' => $registration?->slug]));
        $primaryLabel = __('platform::register.link.sign_in');
    } elseif ($requestUrl) {
        $primaryUrl = $requestUrl;
        $primaryLabel = __('platform::register.link.request_new');
    } else {
        $primaryUrl = $mailto ?? route('central.tenant.login');
        $primaryLabel = $mailto ? __('platform::register.link.contact_support') : __('platform::register.link.sign_in');
    }

    $secondary = $state !== 'used' && $requestUrl && $mailto;
@endphp

@section('content')
    <div class="mx-auto grid max-w-[1200px] items-center gap-8 px-4 pt-10 pb-16 sm:px-6 md:grid-cols-2 md:gap-16 lg:pt-14 lg:pb-24" data-link-state="{{ $state }}">
        <div class="reg-enter grid gap-4">
            <h1 class="text-[30px] leading-tight font-medium text-ink sm:text-[36px]">
                {{ __('platform::register.link.'.$state.'.title') }}
                <span class="text-primary">{{ __('platform::register.link.'.$state.'.accent') }}</span>
            </h1>
            <p class="max-w-[420px] text-base leading-[30px] text-(--reg-gray)">{{ __('platform::register.link.'.$state.'.text', ['hours' => $hours]) }}</p>
        </div>

        <div class="reg-enter grid w-full max-w-[420px] gap-4 rounded-[24px] bg-white p-4 shadow-(--reg-card-shadow) [--reg-delay:120ms] md:justify-self-end">
            <p @class([
                'flex items-center gap-2.5 rounded-xl px-3.5 py-2.5 text-[13px] leading-[18px]',
                'bg-danger-bg text-danger' => $state !== 'used',
                'bg-success-bg text-success' => $state === 'used',
            ]) role="status">
                <x-ui::icon :name="$state === 'used' ? 'check-circle' : 'x-circle'" :size="18" class="shrink-0" />
                {{ __('platform::register.link.'.$state.'.alert') }}
            </p>
            <a href="{{ $primaryUrl }}" class="inline-flex h-12 items-center justify-center rounded-xl bg-primary px-6 text-sm font-medium text-white transition hover:bg-primary-hover active:scale-[0.99]">{{ $primaryLabel }}</a>
            @if ($secondary)
                <a href="{{ $mailto }}" class="justify-self-center text-[13px] text-(--reg-muted) underline-offset-4 hover:text-primary hover:underline">{{ __('platform::register.link.contact_support') }}</a>
            @endif
        </div>
    </div>
@endsection
