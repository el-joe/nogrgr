{{-- Souqify: Store suspended / unavailable (SF-ALL-003, Figma 115:112140 / m(ar) 115:112163). --}}
@extends('storefront::themes.souqify.pages.errors.layout', ['title' => __st('errors.suspended.title')])

@section('content')
    <span class="grid size-[72px] place-items-center rounded-full bg-[#fff3cd] text-[#b7791f]" aria-hidden="true"><x-ui::icon name="alert-triangle" :size="32" /></span>
    <h1 class="mt-2 text-2xl font-semibold text-sf-ink lg:text-[28px]">{{ __st('errors.suspended.title') }}</h1>
    <p class="text-sm leading-6 text-sf-muted">{{ __st('errors.suspended.body') }}</p>
    @if (Route::has('vendor.login'))
        <p class="text-xs text-sf-muted">{{ __st('errors.suspended.owner') }} <a href="{{ vendor_route('vendor.login') }}" class="underline underline-offset-2 hover:text-sf-ink">{{ __st('errors.suspended.owner_link') }}</a></p>
    @endif
@endsection
