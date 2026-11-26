{{-- Elora: Store not launched yet (SF-ALL-001, Figma 114:108727 / m 114:108755): "Opening soon" + newsletter "Notify me" (sf-data endpoint). --}}
@extends('storefront::themes.elora.pages.errors.layout', ['title' => __st('errors.not_launched.title')])

@section('content')
    <span class="grid size-[72px] place-items-center rounded-full bg-sf-accent-subtle text-elo-primary" aria-hidden="true"><x-ui::icon name="clock" :size="32" /></span>
    <h1 class="mt-2 text-2xl font-semibold text-sf-ink lg:text-[28px]">{{ __st('errors.not_launched.title') }}</h1>
    <p class="text-sm leading-6 text-sf-muted">{{ __st('errors.not_launched.body') }}</p>
    @if (Route::has('storefront.newsletter.store'))
        <form method="POST" action="{{ route('storefront.newsletter.store') }}" data-ajax data-reset-on-success novalidate class="mt-2 flex w-full flex-col gap-3 sm:flex-row sm:items-start">
            @csrf
            <x-sf::form.input name="email" type="email" :label="__st('errors.not_launched.email')" placeholder="name@example.com" autocomplete="email" maxlength="160" dir="ltr" required class="flex-1 text-start [&_label]:sr-only" :server-error="false" />
            <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
            <x-sf::form.button type="submit" :busy="__st('errors.not_launched.sending')" class="sm:h-11">{{ __st('errors.not_launched.notify') }}</x-sf::form.button>
        </form>
    @endif
@endsection
